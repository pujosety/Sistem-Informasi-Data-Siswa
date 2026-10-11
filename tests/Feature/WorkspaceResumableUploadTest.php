<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use App\Models\WorkspaceUploadSession;
use App\Services\SchoolContext;
use App\Services\WorkspaceDriveService;
use App\Services\WorkspaceResumableUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Deferred by request: run after all feature integrations and additive migrations. */
class WorkspaceResumableUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
        Storage::fake('workspace-upload-test');
        $this->fakeCleanWorkspaceScanner();
        $this->tempRoot = Storage::disk('workspace-upload-test')->path('');
        config([
            'workspace.quota_bytes' => 64,
            'workspace.uploads.max_bytes' => 32,
            'workspace.uploads.chunk_bytes' => 4,
            'workspace.uploads.max_chunks' => 1024,
            'workspace.uploads.temp_root' => $this->tempRoot,
            'workspace.uploads.capacity_bytes' => 1024,
            'workspace.uploads.owner_capacity_bytes' => 512,
            'workspace.uploads.max_sessions_per_owner' => 3,
            'workspace.uploads.ttl_minutes' => 60,
        ]);
        // The parent owns production require wiring. Tests exercise the exact
        // route file in a web/CSRF group even before that integration happens.
        if (! Route::has('workspace.drive.uploads.create')) {
            Route::middleware('web')->group(base_path('routes/fitur-workspace-uploads.php'));
        }
        $this->owner = $this->makeUser('siswa');
        $this->actingAs($this->owner);
    }

    public function test_upload_is_sequential_checksum_verified_private_and_finalize_is_idempotent(): void
    {
        $id = $this->begin('abcdefgh');
        $this->part($id, 0, 'abcd')->assertOk()->assertJsonPath('received_bytes', 4);
        $this->part($id, 0, 'abcd')->assertOk()->assertJsonPath('next_index', 1);
        $this->getJson($this->url($id))->assertOk()->assertJsonPath('next_index', 1);
        $this->postJson($this->url($id, '/finalize'))->assertUnprocessable()->assertJsonValidationErrors('size');
        $this->part($id, 1, 'efgh')->assertOk()->assertJsonPath('received_bytes', 8);
        $first = $this->postJson($this->url($id, '/finalize'))->assertOk()->assertJsonPath('state', 'completed');
        $again = $this->postJson($this->url($id, '/finalize'))->assertOk();
        $this->assertSame($first->json('file_id'), $again->json('file_id'));
        $this->assertSame(1, WorkspaceFile::query()->count());
        $this->assertSame(1, WorkspaceFileVersion::query()->count());
        $version = WorkspaceFileVersion::query()->firstOrFail();
        $this->assertSame('workspace', $version->disk);
        $this->assertSame(hash('sha256', 'abcdefgh'), $version->checksum_sha256);
        Storage::disk('workspace')->assertExists($version->path);
        $this->assertSame('abcdefgh', Storage::disk('workspace')->get($version->path));
        $this->assertSame('private', Storage::disk('workspace')->getVisibility($version->path));
        $this->assertArrayNotHasKey('path', $first->json());
        $this->assertArrayNotHasKey('temp_key', $first->json());
        $this->cleanup();
        $this->assertSame([], Storage::disk('workspace-upload-test')->allFiles());
        $this->assertSame(0, $this->uploadSession($id)->reserved_bytes);
    }

    public function test_real_assembled_file_larger_than_sync_limit_uses_stream_seam(): void
    {
        config([
            'workspace.quota_bytes' => 8388608, 'workspace.uploads.max_bytes' => 4194304,
            'workspace.uploads.chunk_bytes' => 1048576, 'workspace.uploads.owner_capacity_bytes' => 16777216,
            'workspace.uploads.capacity_bytes' => 16777216,
        ]);
        $bytes = str_repeat('x', 3145728).'last';
        $id = $this->begin($bytes);
        for ($i = 0; $i < 4; $i++) {
            $this->part($id, $i, substr($bytes, $i * 1048576, 1048576))->assertOk();
        }
        $this->postJson($this->url($id, '/finalize'))->assertOk();
        $version = WorkspaceFileVersion::query()->firstOrFail();
        $this->assertSame(strlen($bytes), $version->size_bytes);
        $this->assertSame(hash('sha256', $bytes), $version->checksum_sha256);
        $this->assertSame($bytes, Storage::disk('workspace')->get($version->path));
        $this->assertSame(2048, (int) config('workspace.max_file_kb'));
        $this->post(route('workspace.drive.files.store'), [
            'file' => UploadedFile::fake()->createWithContent('too-large.txt', $bytes),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(1, WorkspaceFile::query()->count());
    }

    public function test_out_of_order_short_long_and_out_of_range_chunks_are_rejected_without_advancing(): void
    {
        $id = $this->begin('abcdef');
        $this->part($id, 1, 'ef')->assertUnprocessable()->assertJsonValidationErrors('index');
        $this->part($id, 0, 'abc')->assertUnprocessable()->assertJsonValidationErrors('chunk');
        $this->part($id, 0, 'abcde')->assertUnprocessable()->assertJsonValidationErrors('chunk');
        $this->part($id, 2, 'zz')->assertUnprocessable()->assertJsonValidationErrors('index');
        $this->getJson($this->url($id))->assertOk()->assertJsonPath('received_bytes', 0);
        $this->assertSame([], Storage::disk('workspace-upload-test')->allFiles());
        $this->part($id, 0, 'abcd')->assertOk();
        $this->part($id, 1, 'efgh')->assertUnprocessable()->assertJsonValidationErrors('chunk');
        $this->part($id, 1, 'ef')->assertOk();
        $this->postJson($this->url($id, '/finalize'))->assertOk();
    }

    public function test_checksums_make_retry_idempotent_and_conflicting_retry_cannot_overwrite(): void
    {
        $id = $this->begin('abcdefgh');
        $this->part($id, 0, 'abcd', hash('sha256', 'fake'))->assertUnprocessable()->assertJsonValidationErrors('checksum');
        $this->part($id, 0, 'abcd')->assertOk();
        $this->part($id, 0, 'zzzz')->assertUnprocessable()->assertJsonValidationErrors('checksum');
        $this->part($id, 0, 'abcd')->assertOk()->assertJsonPath('received_bytes', 4);
        $session = $this->uploadSession($id);
        $this->assertSame('abcd', Storage::disk('workspace-upload-test')->get($session->temp_key.'/0.chunk'));
        $this->assertCount(1, $session->receipts);
    }

    public function test_full_checksum_mismatch_does_not_create_file_or_leave_assembly(): void
    {
        $id = $this->begin('abcd', hash('sha256', 'xxxx'));
        $this->part($id, 0, 'abcd')->assertOk();
        $this->postJson($this->url($id, '/finalize'))->assertUnprocessable()->assertJsonValidationErrors('checksum');
        $this->assertSame(0, WorkspaceFile::query()->count());
        $this->assertSame(0, WorkspaceFileVersion::query()->count());
        $this->assertSame([], Storage::disk('workspace')->allFiles());
        Storage::disk('workspace-upload-test')->assertMissing($this->uploadSession($id)->temp_key.'/assembled');
        $this->assertSame('open', $this->uploadSession($id)->state);
    }

    public function test_corrupt_stored_chunk_is_detected_and_cannot_be_finalized(): void
    {
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        Storage::disk('workspace-upload-test')->put($this->uploadSession($id)->temp_key.'/0.chunk', 'zzzz');
        $this->postJson($this->url($id, '/finalize'))->assertUnprocessable()->assertJsonValidationErrors('chunk');
        $this->assertSame(0, WorkspaceFileVersion::query()->count());
        $this->assertSame([], Storage::disk('workspace')->allFiles());
        $this->part($id, 0, 'abcd')->assertOk()->assertJsonPath('received_bytes', 4);
        $this->postJson($this->url($id, '/finalize'))->assertOk();
        $this->assertSame(1, WorkspaceFileVersion::query()->count());
    }

    public function test_every_operation_requires_authentication_and_the_session_owner(): void
    {
        $id = $this->begin('abcd');
        $other = $this->makeUser('siswa');
        $this->actingAs($other);
        $this->getJson($this->url($id))->assertNotFound();
        $this->part($id, 0, 'abcd')->assertNotFound();
        $this->postJson($this->url($id, '/finalize'))->assertNotFound();
        $this->deleteJson($this->url($id))->assertNotFound();
        auth()->logout();
        $this->getJson($this->url($id))->assertUnauthorized();
        $this->part($id, 0, 'abcd')->assertUnauthorized();
        $this->postJson($this->url($id, '/finalize'))->assertUnauthorized();
        $this->deleteJson($this->url($id))->assertUnauthorized();
        $this->postJson($this->url(), ['name' => 'a.txt', 'size' => 4, 'checksum' => hash('sha256', 'abcd')])->assertUnauthorized();
    }

    public function test_active_school_scope_applies_to_show_chunk_finalize_cancel(): void
    {
        $owner = $this->makeUser('super_admin');
        $this->actingAs($owner);
        $primary = app(SchoolContext::class)->current();
        $id = $this->begin('abcd');
        $other = School::create([
            'slug' => 'upload-secondary', 'name' => 'Upload Secondary', 'short_name' => 'US',
            'level' => 'SMP', 'status' => 'Negeri', 'is_active' => true, 'is_default' => false,
        ]);
        app(SchoolContext::class)->use($other);
        $this->getJson($this->url($id))->assertNotFound();
        $this->part($id, 0, 'abcd')->assertNotFound();
        $this->postJson($this->url($id, '/finalize'))->assertNotFound();
        $this->deleteJson($this->url($id))->assertNotFound();
        app(SchoolContext::class)->use($primary);
        $this->getJson($this->url($id))->assertOk();
    }

    public function test_untrusted_ids_paths_and_mime_are_explicitly_rejected(): void
    {
        foreach (['owner_id', 'school_id', 'parent_folder_id', 'path', 'temp_key', 'mime_type', 'target_public_id'] as $field) {
            $this->postJson($this->url(), [
                'name' => 'file.txt', 'size' => 4, 'checksum' => hash('sha256', 'abcd'), $field => 'malicious',
            ])->assertUnprocessable()->assertJsonValidationErrors('upload');
        }
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 4, 'checksum' => hash('sha256', 'abcd'), 'folder' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('folder');
        $id = $this->begin('abcd');
        $this->getJson($this->url($id).'?owner_id=1')->assertUnprocessable();
        $this->postJson($this->url($id, '/finalize'), ['path' => '/etc/passwd'])->assertUnprocessable();
    }

    public function test_folder_must_belong_to_owner_and_still_exist_at_finalize(): void
    {
        $drive = app(WorkspaceDriveService::class);
        $foreign = $drive->createFolder($this->makeUser('siswa'), 'Private');
        $this->postJson($this->url(), [
            'name' => 'file.txt', 'size' => 4, 'checksum' => hash('sha256', 'abcd'), 'folder' => $foreign->public_id,
        ])->assertNotFound();
        $folder = $drive->createFolder($this->owner, 'Destination');
        $id = $this->begin('abcd', folder: $folder->public_id);
        $this->part($id, 0, 'abcd')->assertOk();
        $drive->trashFolder($this->owner, $folder);
        $this->postJson($this->url($id, '/finalize'))->assertNotFound();
        $this->assertSame(0, WorkspaceFileVersion::query()->count());
    }

    public function test_global_staging_capacity_counts_other_owners_and_releases_after_cancel(): void
    {
        config(['workspace.uploads.capacity_bytes' => 30]);
        $id = $this->begin('abcdef'); // 18 bytes reserved, including assembly/final
        $this->part($id, 0, 'abcd')->assertOk();
        $other = $this->makeUser('siswa');
        $this->actingAs($other);
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 6, 'checksum' => hash('sha256', 'abcdef')])
            ->assertUnprocessable()->assertJsonValidationErrors('size');
        $this->actingAs($this->owner)->deleteJson($this->url($id))->assertOk()->assertJsonPath('state', 'cancelled');
        $this->cleanup();
        $this->assertSame(0, $this->uploadSession($id)->reserved_bytes);
        $this->actingAs($other);
        $this->begin('abcdef');
    }

    public function test_owner_session_and_size_limits_are_enforced_at_admission(): void
    {
        config(['workspace.uploads.max_sessions_per_owner' => 1]);
        $id = $this->begin('abcd');
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 4, 'checksum' => hash('sha256', 'abcd')])->assertUnprocessable();
        $this->deleteJson($this->url($id))->assertOk();
        $this->cleanup();
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 33, 'checksum' => hash('sha256', 'abcd')])->assertUnprocessable();
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 0, 'checksum' => hash('sha256', '')])->assertUnprocessable();
        config(['workspace.uploads.owner_capacity_bytes' => 11]);
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 4, 'checksum' => hash('sha256', 'abcd')])->assertUnprocessable();
        config(['workspace.uploads.owner_capacity_bytes' => 512, 'workspace.uploads.max_chunks' => 1]);
        $this->postJson($this->url(), ['name' => 'file.txt', 'size' => 5, 'checksum' => hash('sha256', 'abcde')])->assertUnprocessable();
    }

    public function test_expired_sessions_reject_writes_remain_charged_until_cleanup_and_remove_crash_orphans(): void
    {
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        $session = $this->uploadSession($id);
        $orphan = 'schools/'.$session->school_id.'/users/'.$session->owner_id.'/'.$session->target_public_id.'.bin';
        Storage::disk('workspace')->put($orphan, 'orphan');
        $this->travel(61)->minutes();
        $this->getJson($this->url($id))->assertOk()->assertJsonPath('state', 'expired');
        $this->part($id, 0, 'abcd')->assertGone();
        $this->postJson($this->url($id, '/finalize'))->assertGone();
        $this->assertSame(12, $session->fresh()->reserved_bytes);
        $this->cleanup();
        $this->assertSame(0, $session->fresh()->reserved_bytes);
        Storage::disk('workspace')->assertMissing($orphan);
        $this->assertSame([], Storage::disk('workspace-upload-test')->allFiles());
        $this->deleteJson($this->url($id))->assertOk();
        $this->travelBack();
    }

    public function test_cancellation_is_idempotent_and_never_deletes_a_completed_file(): void
    {
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        $this->deleteJson($this->url($id))->assertOk()->assertJsonPath('state', 'cancelled');
        $this->deleteJson($this->url($id))->assertOk();
        $this->part($id, 0, 'abcd')->assertConflict();
        $this->postJson($this->url($id, '/finalize'))->assertConflict();
        $this->cleanup();
        $this->assertSame([], Storage::disk('workspace-upload-test')->allFiles());
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        $done = $this->postJson($this->url($id, '/finalize'))->assertOk();
        $this->deleteJson($this->url($id))->assertOk()->assertJsonPath('file_id', $done->json('file_id'));
        $this->cleanup();
        $version = WorkspaceFileVersion::query()->firstOrFail();
        Storage::disk('workspace')->assertExists($version->path);
    }

    public function test_final_quota_check_counts_retained_trash_and_other_writes_after_admission(): void
    {
        config(['workspace.quota_bytes' => 8]);
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        $drive = app(WorkspaceDriveService::class);
        $file = $drive->store(UploadedFile::fake()->createWithContent('other.txt', '12345'), $this->owner);
        $drive->trashFile($this->owner, $file);
        $this->postJson($this->url($id, '/finalize'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(1, WorkspaceFileVersion::query()->count());
        $this->assertSame(1, WorkspaceFile::withTrashed()->count());
        $this->assertSame('open', $this->uploadSession($id)->state);
        $this->assertCount(1, Storage::disk('workspace')->allFiles());
        config(['workspace.quota_bytes' => 9]);
        $this->postJson($this->url($id, '/finalize'))->assertOk();
        $this->assertSame(9, $drive->usedBytesFor($this->owner));
    }

    public function test_completed_retry_survives_session_expiry_but_tombstone_retention_is_bounded(): void
    {
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        $done = $this->postJson($this->url($id, '/finalize'))->assertOk();
        $this->cleanup();
        $this->travel(2)->days();
        $this->postJson($this->url($id, '/finalize'))->assertOk()->assertJsonPath('file_id', $done->json('file_id'));
        $this->assertSame(1, WorkspaceFileVersion::query()->count());
        $this->travel(8)->days();
        $this->cleanup();
        $this->getJson($this->url($id))->assertNotFound();
        $this->assertSame(1, WorkspaceFileVersion::query()->count());
        $this->travelBack();
    }

    public function test_finalize_locks_stable_owner_before_session_and_quota_reads(): void
    {
        $id = $this->begin('abcd');
        $this->part($id, 0, 'abcd')->assertOk();
        $queries = [];
        DB::listen(function ($event) use (&$queries) { $queries[] = strtolower($event->sql); });
        $this->postJson($this->url($id, '/finalize'))->assertOk();
        $owner = $session = $quota = null;
        foreach ($queries as $index => $sql) {
            if ($owner === null && preg_match('/from [`"]?users[`"]? /', $sql)) $owner = $index;
            if ($session === null && str_contains($sql, 'workspace_upload_sessions') && str_contains($sql, 'select')) $session = $index;
            if ($quota === null && str_contains($sql, 'join') && str_contains($sql, 'workspace_file_versions')) $quota = $index;
        }
        $this->assertNotNull($owner);
        $this->assertNotNull($session);
        $this->assertNotNull($quota);
        $this->assertLessThan($session, $owner);
        $this->assertLessThan($quota, $owner);
        if (DB::getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', $queries[$session]);
            $this->assertStringContainsString('for update', $queries[$quota]);
        }
    }

    public function test_component_renders_accessible_controls_without_storage_paths(): void
    {
        $view = $this->blade('<x-workspace.resumable-upload />');
        $view->assertSee('data-workspace-resumable-upload', false)->assertSee('aria-live="polite"', false)
            ->assertSee('data-upload-cancel', false)->assertSee('data-upload-pause', false)
            ->assertDontSee($this->tempRoot, false);
    }

    private function begin(string $bytes, ?string $checksum = null, ?string $folder = null): string
    {
        return $this->postJson($this->url(), [
            'name' => 'file.txt', 'size' => strlen($bytes), 'checksum' => $checksum ?? hash('sha256', $bytes), 'folder' => $folder,
        ])->assertCreated()->json('id');
    }

    private function part(string $id, int $index, string $bytes, ?string $checksum = null): TestResponse
    {
        return $this->post($this->url($id, '/chunks'), [
            'index' => $index, 'checksum' => $checksum ?? hash('sha256', $bytes),
            'chunk' => UploadedFile::fake()->createWithContent('chunk.bin', $bytes),
        ], ['Accept' => 'application/json']);
    }

    private function url(?string $id = null, string $suffix = ''): string
    {
        return rtrim(route('workspace.drive.uploads.create'), '/').($id ? '/'.$id : '').$suffix;
    }

    private function uploadSession(string $id): WorkspaceUploadSession
    {
        return WorkspaceUploadSession::query()->where('public_id', $id)->firstOrFail();
    }

    private function cleanup(): void
    {
        // RefreshDatabase has an outer transaction: afterCommit callbacks do
        // not fire inside a test. Invoke the same maintenance path explicitly.
        app(WorkspaceResumableUploadService::class)->cleanup();
    }
}
