<?php

namespace Tests\Feature;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFolder;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
    }

    public function test_authenticated_student_can_store_and_download_a_private_drive_file(): void
    {
        $student = $this->makeUser('siswa');
        $anotherUser = $this->makeUser('super_admin');
        $schoolId = app(SchoolContext::class)->id();

        $upload = UploadedFile::fake()->createWithContent('catatan.txt', 'konten privat drive');
        $store = $this->actingAs($student)->post('/workspace/drive/files', [
            'file' => $upload,
            'owner_id' => $anotherUser->id,
            'school_id' => $schoolId + 1000,
            'path' => '../../public/rahasia.txt',
        ]);

        $store->assertRedirect(route('workspace.drive.index'));

        $file = WorkspaceFile::query()->where('owner_id', $student->id)->firstOrFail();
        $this->assertSame($student->id, $file->owner_id);
        $this->assertNotSame($anotherUser->id, $file->owner_id);
        $this->assertSame($schoolId, $file->school_id);
        $this->assertSame('drive', $file->source_application);
        $this->assertSame(1, $file->current_version);
        $this->assertDatabaseHas('workspace_file_versions', [
            'workspace_file_id' => $file->id,
            'version_number' => 1,
        ]);

        $version = $file->versions()->firstOrFail();
        Storage::disk('workspace')->assertExists($version->path);

        $this->actingAs($student)
            ->get(route('workspace.drive.index'))
            ->assertOk()
            ->assertSeeText('Workspace')
            ->assertSeeText('catatan.txt');

        $download = $this->actingAs($student)
            ->get(route('workspace.drive.files.download', $file))
            ->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $download->headers->get('Content-Type'));
        $download->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('konten privat drive', $download->streamedContent());
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));

        $this->actingAs($anotherUser)
            ->get(route('workspace.drive.files.download', $file))
            ->assertNotFound();
        $this->actingAs($anotherUser)
            ->get(route('workspace.drive.index'))
            ->assertOk()
            ->assertDontSeeText('catatan.txt');
    }

    public function test_active_school_scope_blocks_even_the_owner_outside_the_selected_tenant(): void
    {
        $default = \App\Models\School::query()->where('is_default', true)->firstOrFail();
        $other = \App\Models\School::firstOrCreate(
            ['slug' => 'smp-workspace-secondary'],
            [
                'name' => 'SMP Workspace Secondary',
                'short_name' => 'SWS',
                'level' => 'SMP',
                'status' => 'Negeri',
                'is_active' => true,
                'is_default' => false,
            ],
        );
        $superAdmin = $this->makeUser('super_admin');

        $this->actingAs($superAdmin)
            ->post(route('settings.school.switch', $other))
            ->assertRedirect();
        $this->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('tenant.txt', 'tenant private bytes'),
        ])->assertRedirect(route('workspace.drive.index'));

        $file = WorkspaceFile::withoutGlobalScopes()
            ->where('owner_id', $superAdmin->id)
            ->where('school_id', $other->id)
            ->firstOrFail();

        $this->post(route('settings.school.switch', $default))->assertRedirect();
        $this->get(route('workspace.drive.files.download', $file))->assertNotFound();
        $this->post(route('workspace.drive.files.restore', $file->public_id))->assertNotFound();
    }

    public function test_owner_can_create_folder_browse_it_and_upload_a_file_inside(): void
    {
        $student = $this->makeUser('siswa');

        $this->actingAs($student)
            ->post('/workspace/drive/folders', ['name' => 'Tugas IPA'])
            ->assertRedirect(route('workspace.drive.index'));

        $folder = \App\Models\WorkspaceFolder::query()
            ->where('owner_id', $student->id)
            ->where('name', 'Tugas IPA')
            ->firstOrFail();

        $this->post('/workspace/drive/folders', [
            'name' => 'Bab 1',
            'folder' => $folder->public_id,
        ])->assertRedirect(route('workspace.drive.index', ['folder' => $folder->public_id]));

        $childFolder = \App\Models\WorkspaceFolder::query()
            ->where('owner_id', $student->id)
            ->where('parent_folder_id', $folder->id)
            ->where('name', 'Bab 1')
            ->firstOrFail();

        $this->get(route('workspace.drive.index', ['folder' => $childFolder->public_id]))
            ->assertOk()
            ->assertSeeText('Tugas IPA')
            ->assertSeeText('Bab 1');

        $this->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('lembar.txt', 'isi tugas'),
            'folder' => $childFolder->public_id,
        ])->assertRedirect(route('workspace.drive.index', ['folder' => $childFolder->public_id]));

        $file = WorkspaceFile::query()->where('owner_id', $student->id)->firstOrFail();
        $this->assertSame($childFolder->id, $file->parent_folder_id);

        $this->get(route('workspace.drive.index'))
            ->assertOk()
            ->assertSeeText('Tugas IPA')
            ->assertDontSee('href="'.route('workspace.drive.index', ['folder' => $childFolder->public_id]).'"', false)
            ->assertDontSeeText('lembar.txt');
        $this->get(route('workspace.drive.index', ['folder' => $folder->public_id]))
            ->assertOk()
            ->assertSeeText('Bab 1')
            ->assertDontSeeText('lembar.txt');
        $this->get(route('workspace.drive.index', ['folder' => $childFolder->public_id]))
            ->assertOk()
            ->assertSeeText('lembar.txt');
    }

    public function test_a_folder_cannot_be_browsed_or_used_by_another_owner(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');

        $this->actingAs($owner)
            ->post('/workspace/drive/folders', ['name' => 'Folder Privat'])
            ->assertRedirect(route('workspace.drive.index'));
        $folder = \App\Models\WorkspaceFolder::query()
            ->where('owner_id', $owner->id)
            ->firstOrFail();
        $this->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('milik-pemilik.txt', 'rahasia'),
            'folder' => $folder->public_id,
        ])->assertRedirect(route('workspace.drive.index', ['folder' => $folder->public_id]));

        $this->actingAs($other)
            ->get(route('workspace.drive.index', ['folder' => $folder->public_id]))
            ->assertNotFound();
        $this->post('/workspace/drive/folders', [
            'name' => 'Sisipan',
            'folder' => $folder->public_id,
        ])->assertNotFound();
        $this->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('serangan.txt', 'harus ditolak'),
            'folder' => $folder->public_id,
        ])->assertNotFound();

        $this->assertDatabaseCount('workspace_folders', 1);
        $this->assertDatabaseCount('workspace_files', 1);
        $this->get(route('workspace.drive.index'))
            ->assertOk()
            ->assertDontSeeText('Folder Privat')
            ->assertDontSeeText('milik-pemilik.txt');
    }

    public function test_search_returns_nested_matches_from_the_current_users_drive_only(): void
    {
        $student = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');

        $this->actingAs($student)
            ->post('/workspace/drive/folders', ['name' => 'Materi'])
            ->assertRedirect(route('workspace.drive.index'));
        $folder = \App\Models\WorkspaceFolder::query()
            ->where('owner_id', $student->id)
            ->firstOrFail();
        $this->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('lesson-notes.txt', 'materi siswa'),
            'folder' => $folder->public_id,
        ])->assertRedirect(route('workspace.drive.index', ['folder' => $folder->public_id]));

        $this->actingAs($other)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('lesson-secret.txt', 'privat'),
            ])
            ->assertRedirect(route('workspace.drive.index'));

        $this->actingAs($student)
            ->get(route('workspace.drive.index', ['q' => 'lesson']))
            ->assertOk()
            ->assertSeeText('lesson-notes.txt')
            ->assertDontSeeText('lesson-secret.txt');
    }

    public function test_owner_can_replace_a_file_without_losing_its_previous_version(): void
    {
        $student = $this->makeUser('siswa');
        $this->actingAs($student)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('draf.txt', 'versi awal'),
            ])
            ->assertRedirect(route('workspace.drive.index'));

        $file = WorkspaceFile::query()->where('owner_id', $student->id)->firstOrFail();
        $firstVersion = $file->versions()->firstOrFail();
        $other = $this->makeUser('siswa');

        $this->actingAs($other)
            ->post('/workspace/drive/files/'.$file->public_id.'/versions', [
                'file' => UploadedFile::fake()->createWithContent('asing.txt', 'harus ditolak'),
            ])
            ->assertNotFound();
        $this->assertSame(1, $file->fresh()->current_version);

        $this->actingAs($student)
            ->post('/workspace/drive/files/'.$file->public_id.'/versions', [
                'file' => UploadedFile::fake()->createWithContent('koreksi.txt', 'versi perbaikan'),
            ])
            ->assertRedirect(route('workspace.drive.index'));

        $file->refresh();
        $this->assertSame(2, $file->current_version);
        $this->assertSame('draf.txt', $file->name);
        $this->assertCount(2, $file->versions()->get());
        Storage::disk('workspace')->assertExists($firstVersion->path);

        $download = $this->actingAs($student)
            ->get(route('workspace.drive.files.download', $file))
            ->assertOk();
        $this->assertSame('versi perbaikan', $download->streamedContent());
        $this->get(route('workspace.drive.index'))
            ->assertOk()
            ->assertSeeText('Versi 1')
            ->assertSeeText('Versi 2');
    }

    public function test_historical_version_download_returns_original_bytes_and_exact_version_metadata(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('original.txt', 'original version bytes'),
        ])->assertRedirect();
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $first = $file->versions()->firstOrFail();

        $this->post('/workspace/drive/files/'.$file->public_id.'/versions', [
            'file' => UploadedFile::fake()->createWithContent('replacement.txt', 'replacement version bytes'),
        ])->assertRedirect();

        $response = $this->get(route('workspace.drive.files.versions.download', [$file, $first->id]))->assertOk();
        $this->assertSame('original version bytes', $response->streamedContent());
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith($first->mime_type, (string) $response->headers->get('Content-Type'));
        $response->assertHeader('Content-Disposition', 'attachment; filename=original.txt');

        $page = $this->get(route('workspace.drive.index'))->assertOk();
        $page->assertSee(route('workspace.drive.files.versions.download', [$file, $first->id]), false);
    }

    public function test_version_download_is_owner_scoped_and_cannot_cross_files(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('one.txt', 'one')]);
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $version = $file->versions()->firstOrFail();
        $this->actingAs($other)->get(route('workspace.drive.files.versions.download', [$file, $version->id]))->assertNotFound();

        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('two.txt', 'two')]);
        $otherFile = WorkspaceFile::query()->where('owner_id', $owner->id)->whereKeyNot($file->id)->firstOrFail();
        $foreignVersion = $otherFile->versions()->firstOrFail();
        $this->get(route('workspace.drive.files.versions.download', [$file, $foreignVersion->id]))->assertNotFound();
    }

    public function test_owner_can_restore_historical_version_as_a_new_immutable_current_version(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('one.txt', 'version one')])->assertRedirect();
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $v1 = $file->versions()->where('version_number', 1)->firstOrFail();
        $this->post('/workspace/drive/files/'.$file->public_id.'/versions', ['file' => UploadedFile::fake()->createWithContent('two.txt', 'version two')])->assertRedirect();
        $v2 = $file->versions()->where('version_number', 2)->firstOrFail();

        $this->post(route('workspace.drive.files.versions.restore', [$file, $v1->id]))->assertRedirect(route('workspace.drive.index'));

        $file->refresh();
        $versions = $file->versions()->orderBy('version_number')->get();
        $this->assertSame(3, $file->current_version);
        $this->assertSame([3, 2, 1], $versions->pluck('version_number')->map(fn ($number) => (int) $number)->all());
        $this->assertSame('version one', Storage::disk('workspace')->get($v1->path));
        $this->assertSame('version two', Storage::disk('workspace')->get($v2->path));
        $this->assertSame($v1->path, $v1->fresh()->path);
        $this->assertSame($v2->path, $v2->fresh()->path);
        $this->assertSame('version one', $this->get(route('workspace.drive.files.download', $file))->streamedContent());
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.file.version.restored']);
    }

    public function test_restore_rejects_other_owner_and_foreign_file_version_without_mutation(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('one.txt', 'one')]);
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $source = $file->versions()->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('foreign.txt', 'foreign')]);
        $foreignFile = WorkspaceFile::query()->where('owner_id', $owner->id)->whereKeyNot($file->id)->firstOrFail();
        $foreignVersion = $foreignFile->versions()->firstOrFail();

        $this->actingAs($other)->post(route('workspace.drive.files.versions.restore', [$file, $source->id]))->assertNotFound();
        $this->actingAs($owner)->post(route('workspace.drive.files.versions.restore', [$file, $foreignVersion->id]))->assertNotFound();
        $this->assertSame(1, $file->fresh()->current_version);
        $this->assertCount(1, $file->versions()->get());
        $this->assertSame('one', Storage::disk('workspace')->get($source->path));
    }

    public function test_owner_can_set_favorite_idempotently_and_find_nested_favorites(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('nested-favorite.txt', 'favorite'),
        ])->assertRedirect();
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $folder = \App\Models\WorkspaceFolder::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'school_id' => app(SchoolContext::class)->id(), 'owner_id' => $owner->id,
            'name' => 'Nested',
        ]);
        $file->update(['parent_folder_id' => $folder->id]);
        $this->actingAs($owner)->post(route('workspace.drive.files.favorite', $file), ['favorite' => '1'])->assertRedirect();
        $this->post(route('workspace.drive.files.favorite', $file), ['favorite' => '1'])->assertRedirect();
        $this->assertTrue((bool) $file->fresh()->is_favorite);
        $searchResults = $this->get(route('workspace.drive.index', ['favorites' => 1, 'q' => 'nested']))
            ->assertOk()->assertSeeText('nested-favorite.txt')->assertDontSeeText('no-such-file.txt');
        $searchResults->assertSee('href="'.route('workspace.drive.index', ['favorites' => 1]).'" class="btn btn-ghost min-h-11 shrink-0">Hapus pencarian', false);
        $this->actingAs($owner)->post(route('workspace.drive.files.favorite', $file), ['favorite' => '0'])->assertRedirect();
        $this->assertFalse((bool) $file->fresh()->is_favorite);
        $this->get(route('workspace.drive.index', ['favorites' => 1]))->assertDontSeeText('nested-favorite.txt');
    }

    public function test_another_owner_cannot_favorite_file_or_see_it_in_favorites(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('private-favorite.txt', 'private'),
        ]);
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $this->actingAs($other)->post(route('workspace.drive.files.favorite', $file), ['favorite' => '1'])->assertNotFound();
        $this->assertFalse((bool) $file->fresh()->is_favorite);
        $this->get(route('workspace.drive.index', ['favorites' => 1]))->assertDontSeeText('private-favorite.txt');
    }

    public function test_favorite_action_rejects_file_outside_active_school(): void
    {
        $owner = $this->makeUser('super_admin');
        $this->actingAs($owner)->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('school-file.txt', 'private'),
        ]);
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $otherSchool = \App\Models\School::firstOrCreate(['slug' => 'favorite-secondary'], [
            'name' => 'Favorite Secondary', 'short_name' => 'FS', 'level' => 'SMP',
            'status' => 'Negeri', 'is_active' => true, 'is_default' => false,
        ]);
        $file->update(['school_id' => $otherSchool->id]);
        $this->post(route('settings.school.switch', $otherSchool))->assertRedirect();
        $this->post(route('settings.school.switch', \App\Models\School::query()->where('is_default', true)->firstOrFail()))->assertRedirect();
        $this->post(route('workspace.drive.files.favorite', $file), ['favorite' => '1'])->assertNotFound();
        $this->assertFalse((bool) $file->fresh()->is_favorite);
    }

    public function test_owner_can_rename_file_preserving_extension_versions_and_context(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('Draft.DOCX', 'private bytes')])->assertRedirect();
        $file = WorkspaceFile::query()->firstOrFail();
        $version = $file->versions()->firstOrFail();
        $path = $version->path;
        $checksum = $version->checksum_sha256;
        $this->post(route('workspace.drive.files.favorite', $file), ['favorite' => '1'])->assertRedirect();

        $this->post(route('workspace.drive.files.rename', $file), ['name' => "  Final/Draft.docx\n"])->assertRedirect();
        $this->assertSame('FinalDraft.docx', $file->fresh()->name);
        $this->assertSame($path, $version->fresh()->path);
        $this->assertSame($checksum, $version->fresh()->checksum_sha256);
        $this->assertSame('private bytes', Storage::disk('workspace')->get($path));
        $this->assertTrue((bool) $file->fresh()->is_favorite);
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.file.renamed']);
    }

    public function test_file_rename_rejects_extension_changes_and_invalid_names(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('report.docx', 'bytes')]);
        $file = WorkspaceFile::query()->firstOrFail();
        foreach (['report.exe', '', str_repeat('x', 256).'.docx'] as $name) {
            $this->post(route('workspace.drive.files.rename', $file), ['name' => $name])->assertSessionHasErrors('name');
            $this->assertSame('report.docx', $file->fresh()->name);
        }
    }

    public function test_rename_is_indistinguishably_scoped_and_folder_rename_keeps_hierarchy(): void
    {
        $owner = $this->makeUser('super_admin');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Parent'])->assertRedirect();
        $parent = \App\Models\WorkspaceFolder::query()->firstOrFail();
        $this->post('/workspace/drive/folders', ['name' => 'Child', 'folder' => $parent->public_id])->assertRedirect();
        $child = \App\Models\WorkspaceFolder::query()->where('parent_folder_id', $parent->id)->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('keep.txt', 'x'), 'folder' => $child->public_id]);
        $file = WorkspaceFile::query()->firstOrFail();
        $parentId = $file->parent_folder_id;

        $this->actingAs($other)->post(route('workspace.drive.folders.rename', $parent), ['name' => 'Hidden'])->assertNotFound();
        $this->assertSame('Parent', $parent->fresh()->name);
        $this->actingAs($owner)->post(route('workspace.drive.folders.rename', $parent), ['name' => 'Renamed'])->assertRedirect();
        $this->assertSame($parentId, $file->fresh()->parent_folder_id);
        $this->get(route('workspace.drive.index', ['folder' => $child->public_id]))->assertOk()->assertSeeText('Renamed')->assertSeeText('Child');
    }

    public function test_rename_rejects_items_outside_active_school_without_mutation(): void
    {
        $owner = $this->makeUser('super_admin');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'school-folder'])->assertRedirect();
        $folder = app('db')->table('workspace_folders')->first();
        $this->actingAs($owner)->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('school.txt', 'bytes')]);
        $file = WorkspaceFile::query()->firstOrFail();
        $otherSchool = \App\Models\School::firstOrCreate(['slug' => 'rename-secondary'], ['name' => 'Rename Secondary', 'short_name' => 'RS', 'level' => 'SMP', 'status' => 'Negeri', 'is_active' => true, 'is_default' => false]);
        $file->update(['school_id' => $otherSchool->id]);
        app('db')->table('workspace_folders')->where('id', $folder->id)->update(['school_id' => $otherSchool->id]);
        $this->post(route('settings.school.switch', $otherSchool))->assertRedirect();
        $this->post(route('settings.school.switch', app('db')->table('schools')->where('is_default', true)->value('id')))->assertRedirect();
        $this->post(route('workspace.drive.files.rename', $file), ['name' => 'other.txt'])->assertNotFound();
        $this->post(route('workspace.drive.folders.rename', $folder->public_id), ['name' => 'other-folder'])->assertNotFound();
        $this->assertSame('school.txt', app('db')->table('workspace_files')->where('id', $file->id)->value('name'));
        $this->assertSame('school-folder', app('db')->table('workspace_folders')->where('id', $folder->id)->value('name'));
    }

    public function test_move_routes_are_available_to_authenticated_drive_owners(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('workspace.drive.files.move'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('workspace.drive.folders.move'));
    }

    public function test_owner_can_move_file_between_root_and_owned_folder_without_changing_versions(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Destination'])->assertRedirect();
        $destination = app('db')->table('workspace_folders')->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('move.txt', 'move bytes')])->assertRedirect();
        $file = WorkspaceFile::query()->firstOrFail();
        $version = $file->versions()->firstOrFail();
        $path = $version->path;
        $checksum = $version->checksum_sha256;
        $this->post(route('workspace.drive.files.favorite', $file), ['favorite' => '1'])->assertRedirect();

        $this->post(route('workspace.drive.files.move', $file), ['destination' => $destination->public_id])->assertRedirect();
        $file->refresh();
        $this->assertSame($destination->id, $file->parent_folder_id);
        $this->assertSame(1, $file->current_version);
        $this->assertTrue((bool) $file->is_favorite);
        $this->assertSame($path, $version->fresh()->path);
        $this->assertSame($checksum, $version->fresh()->checksum_sha256);
        $this->assertSame('move bytes', Storage::disk('workspace')->get($path));
        $this->get(route('workspace.drive.index', ['folder' => $destination->public_id]))->assertOk()->assertSeeText('move.txt');

        $this->post(route('workspace.drive.files.move', $file), ['destination' => 'root'])->assertRedirect();
        $this->assertNull($file->fresh()->parent_folder_id);
    }

    public function test_moving_folder_preserves_descendants_and_rejects_cycles(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Source'])->assertRedirect();
        $source = app('db')->table('workspace_folders')->firstOrFail();
        $this->post('/workspace/drive/folders', ['name' => 'Target'])->assertRedirect();
        $target = app('db')->table('workspace_folders')->where('id', '!=', $source->id)->firstOrFail();
        $this->post('/workspace/drive/folders', ['name' => 'Child', 'folder' => $source->public_id])->assertRedirect();
        $child = app('db')->table('workspace_folders')->where('parent_folder_id', $source->id)->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('inside.txt', 'inside bytes'), 'folder' => $child->public_id])->assertRedirect();
        $file = WorkspaceFile::query()->firstOrFail();
        $version = $file->versions()->firstOrFail();

        $this->post(route('workspace.drive.folders.move', $source->public_id), ['destination' => $target->public_id])->assertRedirect();
        $this->assertSame($target->id, app('db')->table('workspace_folders')->where('id', $source->id)->value('parent_folder_id'));
        $this->assertSame($source->id, app('db')->table('workspace_folders')->where('id', $child->id)->value('parent_folder_id'));
        $this->assertSame($child->id, $file->fresh()->parent_folder_id);
        $this->assertSame('inside bytes', Storage::disk('workspace')->get($version->path));
        $this->get(route('workspace.drive.index', ['folder' => $target->public_id]))->assertOk()->assertSeeText('Source');

        $this->post(route('workspace.drive.folders.move', $target->public_id), ['destination' => $child->public_id])->assertSessionHasErrors('destination');
        $this->get(route('workspace.drive.index'))->assertSeeText('Folder tidak dapat dipindahkan');
        $this->post(route('workspace.drive.folders.move', $source->public_id), ['destination' => $source->public_id])->assertSessionHasErrors('destination');
        $this->assertSame($target->id, app('db')->table('workspace_folders')->where('id', $source->id)->value('parent_folder_id'));
        $this->assertNull(app('db')->table('workspace_folders')->where('id', $target->id)->value('parent_folder_id'));
    }

    public function test_move_rejects_foreign_owner_and_other_school_destinations(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Owned'])->assertRedirect();
        $owned = app('db')->table('workspace_folders')->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('owned.txt', 'owned bytes')])->assertRedirect();
        $file = WorkspaceFile::query()->firstOrFail();
        $this->actingAs($other)->post('/workspace/drive/folders', ['name' => 'Foreign'])->assertRedirect();
        $foreign = app('db')->table('workspace_folders')->where('owner_id', $other->id)->firstOrFail();

        $this->actingAs($owner)->post(route('workspace.drive.files.move', $file), ['destination' => $foreign->public_id])->assertNotFound();
        $this->post(route('workspace.drive.folders.move', $owned->public_id), ['destination' => $foreign->public_id])->assertNotFound();
        $this->assertNull($file->fresh()->parent_folder_id);
        $this->assertNull(app('db')->table('workspace_folders')->where('id', $owned->id)->value('parent_folder_id'));

        $otherSchoolId = app('db')->table('schools')->insertGetId(['slug' => 'move-school-'.$owner->id, 'name' => 'Move Other', 'is_active' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()]);
        app('db')->table('workspace_folders')->where('id', $owned->id)->update(['school_id' => $otherSchoolId]);
        $this->post(route('workspace.drive.files.move', $file), ['destination' => $owned->public_id])->assertNotFound();
        $this->assertNull($file->fresh()->parent_folder_id);
    }

    public function test_move_controls_render_for_drive_files_and_folders(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Destination'])->assertRedirect();
        $folder = app('db')->table('workspace_folders')->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('visible.txt', 'bytes')])->assertRedirect();
        $file = WorkspaceFile::query()->firstOrFail();

        $page = $this->get(route('workspace.drive.index'))->assertOk();
        $page->assertSee(route('workspace.drive.files.move', $file), false);
        $page->assertSee(route('workspace.drive.folders.move', $folder->public_id), false);
        $page->assertSee('value="'.$folder->public_id.'"', false);
        $page->assertSee('value="root"', false);
    }

    public function test_upload_limit_fails_before_creating_file_metadata(): void
    {
        $student = $this->makeUser('siswa');
        $oversized = UploadedFile::fake()->create(
            'terlalu-besar.txt',
            (int) config('workspace.max_file_kb') + 1,
            'text/plain',
        );

        $this->actingAs($student)
            ->post('/workspace/drive/files', ['file' => $oversized])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('workspace_files', 0);
        $this->assertDatabaseCount('workspace_file_versions', 0);
    }

    public function test_trash_and_restore_retains_private_versions_bytes_and_storage_usage(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Original folder'])->assertRedirect();
        $parent = WorkspaceFolder::query()->where('owner_id', $owner->id)->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('trash.txt', 'retained bytes'), 'folder' => $parent->public_id])->assertRedirect();
        $file = WorkspaceFile::query()->firstOrFail();
        $version = $file->versions()->firstOrFail();
        $bytes = app(\App\Services\WorkspaceDriveService::class)->usedBytesFor($owner);
        $this->post(route('workspace.drive.files.trash', $file))->assertRedirect();
        $this->assertSoftDeleted('workspace_files', ['id' => $file->id]);
        Storage::disk('workspace')->assertExists($version->path);
        $this->assertSame($bytes, app(\App\Services\WorkspaceDriveService::class)->usedBytesFor($owner));
        $this->get(route('workspace.drive.files.download', $file))->assertNotFound();
        $this->get(route('workspace.drive.index'))->assertDontSeeText('trash.txt');
        $this->get(route('workspace.drive.trash'))->assertOk()->assertSeeText('trash.txt');
        $this->actingAs($other)->get(route('workspace.drive.trash'))->assertDontSeeText('trash.txt');
        $this->post(route('workspace.drive.files.restore', $file->public_id))->assertNotFound();
        $this->actingAs($owner)->post(route('workspace.drive.files.restore', $file->public_id))->assertRedirect();
        $this->assertNotSoftDeleted('workspace_files', ['id' => $file->id]);
        $this->assertSame($parent->id, $file->fresh()->parent_folder_id);
        $this->get(route('workspace.drive.index', ['folder' => $parent->public_id]))->assertSeeText('trash.txt');
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.file.trashed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.file.restored']);
    }

    public function test_empty_folder_can_be_trashed_and_restored_but_any_child_blocks_trash(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)->post('/workspace/drive/folders', ['name' => 'Empty'])->assertRedirect();
        $empty = \App\Models\WorkspaceFolder::query()->firstOrFail();
        $this->post(route('workspace.drive.folders.trash', $empty))->assertRedirect();
        $this->get(route('workspace.drive.trash'))->assertSeeText('Empty');
        $other = $this->makeUser('siswa');
        $this->actingAs($other)->post(route('workspace.drive.folders.restore', $empty->public_id))->assertNotFound();
        $this->actingAs($owner)->post(route('workspace.drive.folders.restore', $empty->public_id))->assertRedirect();
        $this->get(route('workspace.drive.index'))->assertSeeText('Empty');

        $this->post('/workspace/drive/folders', ['name' => 'Parent'])->assertRedirect();
        $parent = \App\Models\WorkspaceFolder::query()->where('name', 'Parent')->firstOrFail();
        $this->post('/workspace/drive/files', ['file' => UploadedFile::fake()->createWithContent('child.txt', 'child'), 'folder' => $parent->public_id])->assertRedirect();
        $child = WorkspaceFile::query()->firstOrFail();
        $this->post(route('workspace.drive.files.trash', $child))->assertRedirect();
        $this->post(route('workspace.drive.folders.trash', $parent))->assertSessionHasErrors();
        $this->assertDatabaseHas('workspace_folders', ['id' => $parent->id, 'deleted_at' => null]);
    }
}
