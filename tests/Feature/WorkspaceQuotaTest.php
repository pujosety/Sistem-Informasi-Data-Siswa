<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use App\Models\WorkspaceWopiLock;
use App\Services\SchoolContext;
use App\Services\WorkspaceDriveService;
use App\Services\WorkspaceWopiLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkspaceQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
        $this->fakeCleanWorkspaceScanner();
        config(['workspace.quota_bytes' => 20]);
    }

    public function test_usage_counts_every_retained_version_including_trash_without_counting_other_owners(): void
    {
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('123456'), $owner);
        $drive->replaceVersion($this->upload('789'), $owner, $file);
        $drive->store($this->upload('other owner'), $this->makeUser('siswa'));
        $this->assertSame(9, $drive->usedBytesFor($owner));
        $this->assertSame(3, $file->refresh()->size_bytes);

        $drive->trashFile($owner, $file);
        $this->assertSame(9, $drive->usedBytesFor($owner));
        config(['workspace.quota_bytes' => 1]);
        $drive->restoreFile($owner, $file->public_id);
        $this->assertSame(9, $drive->usedBytesFor($owner));
        $this->assertSame(2, $file->versions()->count());
    }

    public function test_initial_upload_at_exact_quota_succeeds_and_one_more_byte_is_rejected_without_orphans(): void
    {
        config(['workspace.quota_bytes' => 4]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('1234'), $owner);
        $paths = Storage::disk('workspace')->allFiles();

        $this->assertQuotaRejected(fn () => $drive->store($this->upload('5'), $owner));
        $this->assertSame(1, WorkspaceFile::query()->where('owner_id', $owner->id)->count());
        $this->assertSame(1, $file->versions()->count());
        $this->assertSame($paths, Storage::disk('workspace')->allFiles());
        $this->assertSame(4, $drive->usedBytesFor($owner));
    }

    public function test_smaller_replacement_cannot_reclaim_bytes_from_previous_versions(): void
    {
        config(['workspace.quota_bytes' => 8]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('123456'), $owner);
        $source = $file->versions()->firstOrFail();
        $paths = Storage::disk('workspace')->allFiles();

        $this->assertQuotaRejected(fn () => $drive->replaceVersion($this->upload('abc'), $owner, $file));
        $this->assertSame(1, $file->refresh()->current_version);
        $this->assertSame(6, $file->size_bytes);
        $this->assertSame(1, $file->versions()->count());
        $this->assertSame($paths, Storage::disk('workspace')->allFiles());
        $this->assertSame('123456', Storage::disk('workspace')->get($source->path));
    }

    public function test_restore_appends_a_copy_and_counts_its_full_size_at_exact_quota(): void
    {
        config(['workspace.quota_bytes' => 11]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('1234'), $owner);
        $source = $file->versions()->firstOrFail();
        $drive->replaceVersion($this->upload('abc'), $owner, $file);
        $restored = $drive->restoreVersion($owner, $file, $source->id);

        $this->assertSame(3, $restored->current_version);
        $this->assertSame(11, $drive->usedBytesFor($owner));
        $newVersion = $restored->versions()->where('version_number', 3)->firstOrFail();
        $this->assertNotSame($source->path, $newVersion->path);
        $this->assertSame('1234', Storage::disk('workspace')->get($newVersion->path));
        $this->assertSame('1234', Storage::disk('workspace')->get($source->path));
        $this->assertSame($source->checksum_sha256, $source->fresh()->checksum_sha256);
    }

    public function test_restore_rejection_preserves_original_snapshot_and_current_version(): void
    {
        config(['workspace.quota_bytes' => 10]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('1234'), $owner);
        $source = $file->versions()->firstOrFail();
        $drive->replaceVersion($this->upload('abc'), $owner, $file);
        $paths = Storage::disk('workspace')->allFiles();

        $this->assertQuotaRejected(fn () => $drive->restoreVersion($owner, $file, $source->id), 'version');
        $this->assertSame(2, $file->refresh()->current_version);
        $this->assertSame(2, $file->versions()->count());
        $this->assertSame(7, $drive->usedBytesFor($owner));
        $this->assertSame($paths, Storage::disk('workspace')->allFiles());
        $this->assertSame('1234', Storage::disk('workspace')->get($source->path));
        $this->assertSame($source->getAttributes(), $source->fresh()->getAttributes());
    }

    public function test_wopi_save_counts_full_append_and_rejects_without_touching_old_bytes(): void
    {
        config(['workspace.quota_bytes' => 8]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('12345'), $owner);
        $source = $file->versions()->firstOrFail();
        $lock = WorkspaceWopiLock::create([
            'workspace_file_id' => $file->id, 'lock_value' => 'quota-lock',
            'expires_at' => now()->addMinutes(30),
        ]);
        $locks = app(WorkspaceWopiLockService::class);
        $saved = $locks->withCurrentLock($file->id, 'quota-lock',
            fn () => $drive->replaceWopiVersion($owner, $file, (int) $file->school_id, 'abc'));
        $this->assertSame(2, $saved->current_version);
        $this->assertSame(8, $drive->usedBytesFor($owner));
        $paths = Storage::disk('workspace')->allFiles();

        $this->assertQuotaRejected(fn () => $locks->withCurrentLock($file->id, 'quota-lock',
            fn () => $drive->replaceWopiVersion($owner, $file, (int) $file->school_id, 'x')));
        $this->assertSame('quota-lock', $lock->fresh()->lock_value);
        $this->assertSame(2, $file->refresh()->current_version);
        $this->assertSame(2, $file->versions()->count());
        $this->assertSame($paths, Storage::disk('workspace')->allFiles());
        $this->assertSame('12345', Storage::disk('workspace')->get($source->path));
    }

    public function test_same_owner_gets_separate_school_budgets_and_wopi_uses_its_explicit_school(): void
    {
        config(['workspace.quota_bytes' => 4]);
        $owner = $this->makeUser('super_admin');
        $drive = app(WorkspaceDriveService::class);
        $context = app(SchoolContext::class);
        $primary = $context->current();
        $first = $drive->store($this->upload('123'), $owner);
        $other = School::create([
            'slug' => 'quota-secondary', 'name' => 'Quota Secondary', 'short_name' => 'QS',
            'level' => 'SMP', 'status' => 'Negeri', 'is_active' => true, 'is_default' => false,
        ]);
        $context->useForRequest($other);
        $drive->store($this->upload('abcd'), $owner);
        $this->assertSame(4, $drive->usedBytesFor($owner));
        $drive->replaceWopiVersion($owner, $first, $primary->id, 'x');
        $this->assertSame(4, $drive->usedBytesFor($owner));
        $this->assertQuotaRejected(fn () => $drive->replaceWopiVersion($owner, $first, $primary->id, 'y'));
        $context->useForRequest($primary);
        $this->assertSame(4, $drive->usedBytesFor($owner));
        $this->assertSame(2, $first->refresh()->current_version);
    }

    public function test_all_files_of_one_owner_share_the_budget_even_after_trashing_one(): void
    {
        config(['workspace.quota_bytes' => 8]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $first = $drive->store($this->upload('1234'), $owner);
        $second = $drive->store($this->upload('abc'), $owner);
        $drive->trashFile($owner, $first);
        $drive->replaceVersion($this->upload('x'), $owner, $second);
        $this->assertSame(8, $drive->usedBytesFor($owner));
        $this->assertQuotaRejected(fn () => $drive->replaceVersion($this->upload('y'), $owner, $second));
        $this->assertSame(2, $second->refresh()->current_version);
    }

    public function test_zero_quota_blocks_nonempty_uploads_and_lowering_quota_keeps_downloads_available(): void
    {
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('private bytes'), $owner);
        config(['workspace.quota_bytes' => 0]);
        $this->assertSame(0, $drive->quotaBytesFor($owner));
        $this->assertQuotaRejected(fn () => $drive->store($this->upload('x'), $owner));
        $this->assertQuotaRejected(fn () => $drive->replaceVersion($this->upload('x'), $owner, $file));
        $this->actingAs($owner)->get(route('workspace.drive.files.download', $file))
            ->assertOk()->assertStreamedContent('private bytes');
    }

    public function test_drive_displays_retained_usage_and_quota_and_upload_returns_validation_error(): void
    {
        config(['workspace.quota_bytes' => 4]);
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store($this->upload('123'), $owner);
        $drive->replaceVersion($this->upload('x'), $owner, $file);
        $this->actingAs($owner)->get(route('workspace.drive.index'))
            ->assertOk()->assertSeeText('Terpakai: 4 byte / Kuota: 4 byte')
            ->assertSeeText('Semua versi dan berkas di Trash tetap dihitung.')
            ->assertSeeText('Kuota penyimpanan habis.');
        $this->post(route('workspace.drive.files.store'), ['file' => $this->upload('y')])
            ->assertSessionHasErrors('file');
        $this->assertSame(2, WorkspaceFileVersion::query()->count());
    }

    public function test_each_append_path_acquires_stable_owner_lock_before_workspace_row_locks(): void
    {
        $owner = $this->makeUser('siswa');
        $drive = app(WorkspaceDriveService::class);
        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            $queries[] = strtolower($event->sql);
        });
        $queries = [];
        $file = $drive->store($this->upload('123'), $owner);
        $this->assertOwnerReadPrecedesAdmission($queries);
        $source = $file->versions()->firstOrFail();
        $operations = [
            fn () => $drive->replaceVersion($this->upload('x'), $owner, $file),
            fn () => $drive->restoreVersion($owner, $file, $source->id),
            fn () => $drive->replaceWopiVersion($owner, $file, (int) $file->school_id, 'z'),
        ];
        foreach ($operations as $operation) {
            $queries = [];
            $operation();
            $this->assertOwnerReadPrecedesAdmission($queries);
        }
    }

    private function assertOwnerReadPrecedesAdmission(array $queries): void
    {
        $ownerIndex = null;
        $admissionIndex = null;
        foreach ($queries as $index => $sql) {
            if ($ownerIndex === null && preg_match('/from [`"]?users[`"]? /', $sql)) {
                $ownerIndex = $index;
                if (DB::getDriverName() === 'mysql') {
                    $this->assertStringContainsString('for update', $sql);
                }
            }
            if (str_contains($sql, 'join') && str_contains($sql, 'workspace_file_versions')) {
                $admissionIndex = $index;
                if (DB::getDriverName() === 'mysql') {
                    $this->assertStringContainsString('for update', $sql);
                }
                break;
            }
        }
        $this->assertNotNull($ownerIndex, 'No stable owner read was issued.');
        $this->assertNotNull($admissionIndex, 'No retained-version admission read was issued.');
        $this->assertLessThan($admissionIndex, $ownerIndex);
        if (DB::getDriverName() === 'mysql') {
            foreach ($queries as $index => $sql) {
                if (str_contains($sql, 'for update') && str_contains($sql, 'workspace_')) {
                    $this->assertLessThan($index, $ownerIndex, 'Workspace row locked before the stable owner.');
                }
            }
        }
    }

    private function upload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('quota.txt', $contents);
    }

    private function assertQuotaRejected(callable $operation, string $field = 'file'): void
    {
        try {
            $operation();
            $this->fail('An append exceeding the retained-byte quota was admitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertStringContainsString('Kuota penyimpanan', $exception->errors()[$field][0]);
        }
    }
}
