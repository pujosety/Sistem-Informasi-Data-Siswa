<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\WorkspaceDesignAsset;
use App\Models\WorkspaceDesignProject;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceVersionScan;
use App\Services\ClamdWorkspaceScanner;
use App\Services\SchoolContext;
use App\Services\WorkspaceDriveService;
use App\Services\WorkspaceMalwareScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Deferred regression suite: intentionally not executed while Docker is unavailable. */
class WorkspaceDesignProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
    }

    private function createProject(): array
    {
        $response = $this->postJson(route('workspace.design.store'), ['name' => 'School poster'])->assertCreated();
        return $response->json();
    }

    private function endpoint(array $project): string
    {
        return route('workspace.design.show', $project['id']);
    }

    private function scanner(string $status): void
    {
        $this->mock(ClamdWorkspaceScanner::class)->shouldReceive('scan')->andReturnUsing(
            fn ($stream, int $size) => new WorkspaceMalwareScanResult($status, '', hash('sha256', stream_get_contents($stream)), $size)
        );
    }

    private function image(): WorkspaceFile
    {
        // A valid, tiny PNG exercises MIME sniffing without a GD dependency.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
        $this->post(route('workspace.drive.files.store'), ['file' => UploadedFile::fake()->createWithContent('private.png', $bytes)])->assertRedirect();
        return WorkspaceFile::query()->latest('id')->firstOrFail();
    }

    public function test_guest_cannot_list_create_or_open_projects(): void
    {
        $this->get(route('workspace.design.index'))->assertRedirect(route('login'));
        $this->postJson(route('workspace.design.store'), ['name' => 'Denied'])->assertUnauthorized();
        $this->getJson(route('workspace.design.show', (string) Str::uuid()))->assertUnauthorized();
        $this->assertDatabaseCount('workspace_design_projects', 0);
    }

    public function test_create_load_and_atomic_save_increment_revision(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $project = $this->createProject();
        $this->getJson($this->endpoint($project))->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('document.width', 1080);
        $project['name'] = 'Renamed poster';
        $project['document']['pages'][0]['canvas']['objects'][] = ['type' => 'Rect', 'left' => 10, 'top' => 20, 'width' => 100, 'height' => 100, 'fill' => '#112233'];
        $this->putJson($this->endpoint($project), $project)->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('name', 'Renamed poster');
        $this->getJson($this->endpoint($project))->assertJsonPath('document.pages.0.canvas.objects.0.type', 'Rect');
    }

    public function test_stale_revision_never_overwrites_newer_document(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $project = $this->createProject();
        $latest = [...$project, 'name' => 'Saved by first tab'];
        $this->putJson($this->endpoint($project), $latest)->assertOk()->assertJsonPath('revision', 2);
        $project['name'] = 'Stale tab';
        $this->putJson($this->endpoint($project), $project)->assertConflict()->assertJsonPath('revision', 2);
        $this->getJson($this->endpoint($project))->assertJsonPath('name', 'Saved by first tab')->assertJsonPath('revision', 2);
    }

    public function test_other_owner_including_super_admin_cannot_read_write_or_attach(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $project = $this->createProject();
        foreach (['siswa', 'super_admin'] as $role) {
            $this->actingAs($this->makeUser($role));
            $this->getJson($this->endpoint($project))->assertNotFound();
            $this->get(route('workspace.design.editor', $project['id']))->assertNotFound();
            $this->putJson($this->endpoint($project), $project)->assertNotFound();
            $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => (string) Str::uuid()])->assertNotFound();
            $this->get(route('workspace.design.index'))->assertDontSeeText('School poster');
        }
    }

    public function test_active_school_boundary_hides_projects_even_from_their_owner(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $project = $this->createProject();
        $other = School::create(['slug' => 'design-other', 'name' => 'Other school', 'is_active' => true, 'is_default' => false]);
        app(SchoolContext::class)->use($other);
        $this->getJson($this->endpoint($project))->assertNotFound();
        $this->putJson($this->endpoint($project), $project)->assertNotFound();
    }

    public function test_inactive_account_cannot_access_designs(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner);
        $project = $this->createProject();
        $owner->forceFill(['is_active' => false])->save();
        $this->getJson($this->endpoint($project))->assertForbidden();
    }

    public function test_invalid_documents_are_rejected_without_incrementing_revision(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $project = $this->createProject();
        $cases = [];
        $cases[] = [...$project['document'], 'pages' => []];
        $cases[] = [...$project['document'], 'width' => 2401];
        $cases[] = [...$project['document'], 'height' => 49];
        $cases[] = [...$project['document'], 'width' => 1080.5];
        $cases[] = [...$project['document'], 'extra' => true];
        $cases[] = [...$project['document'], 'pages' => [$project['document']['pages'][0], $project['document']['pages'][0]]];
        $cases[] = [...$project['document'], 'pages' => [['id' => (string) Str::uuid(), 'title' => '', 'canvas' => ['objects' => []]]]];
        $cases[] = [...$project['document'], 'pages' => [['id' => (string) Str::uuid(), 'title' => 'Unsafe', 'canvas' => ['objects' => [['type' => 'unknown']]]]]];
        foreach ($cases as $document) {
            $this->putJson($this->endpoint($project), [...$project, 'document' => $document])->assertUnprocessable();
        }
        $this->getJson($this->endpoint($project))->assertJsonPath('revision', 1);
    }

    public function test_external_inline_and_unattached_asset_sources_are_rejected(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $project = $this->createProject();
        foreach (['https://example.com/private.png', 'data:image/png;base64,AA==', 'blob:invalid', '/workspace/design/'.$project['id'].'/assets/'.Str::uuid()] as $src) {
            $document = $project['document'];
            $document['pages'][0]['canvas']['objects'] = [['type' => 'Image', 'src' => $src]];
            $this->putJson($this->endpoint($project), [...$project, 'document' => $document])->assertUnprocessable();
        }
        $this->assertDatabaseCount('workspace_design_assets', 0);
    }

    public function test_attachment_is_immutable_private_and_does_not_duplicate_drive_quota_bytes(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner);
        $this->scanner('clean');
        $project = $this->createProject();
        $file = $this->image();
        $old = $file->versions()->firstOrFail();
        $used = app(WorkspaceDriveService::class)->usedBytesFor($owner);
        $url = $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => $file->public_id])->assertCreated()->json('url');
        $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => $file->public_id])->assertCreated()->assertJsonPath('url', $url);
        $this->assertDatabaseCount('workspace_design_assets', 1);
        $this->assertSame($used, app(WorkspaceDriveService::class)->usedBytesFor($owner));
        $this->assertDatabaseHas('workspace_design_assets', ['workspace_file_version_id' => $old->id]);
        $document = $project['document'];
        $document['pages'][0]['canvas']['objects'] = [['type' => 'Image', 'src' => $url]];
        $this->putJson($this->endpoint($project), [...$project, 'document' => $document])->assertOk();
        $assetResponse = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertTrue($assetResponse->headers->hasCacheControlDirective('private'));
        $this->assertTrue($assetResponse->headers->hasCacheControlDirective('no-store'));
        $this->post(route('workspace.drive.files.versions.store', $file), ['file' => UploadedFile::fake()->createWithContent('replacement.png', Storage::disk('workspace')->get($old->path))])->assertRedirect();
        $this->assertSame($old->id, WorkspaceDesignAsset::query()->firstOrFail()->workspace_file_version_id);
        $this->get($url)->assertOk();
        $this->actingAs($this->makeUser('siswa'))->get($url)->assertNotFound();
    }

    public function test_asset_scanner_missing_and_infected_states_fail_closed(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $this->scanner('clean');
        $project = $this->createProject();
        $file = $this->image();
        $version = $file->versions()->firstOrFail();
        $url = $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => $file->public_id])->assertCreated()->json('url');
        DB::table('workspace_version_scans')->where('workspace_file_version_id', $version->id)->update(['status' => 'infected']);
        $this->get($url)->assertStatus(423);
        $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => $file->public_id])->assertStatus(423);
        WorkspaceVersionScan::query()->where('workspace_file_version_id', $version->id)->delete();
        $this->get($url)->assertStatus(423);
    }

    public function test_raw_upload_and_foreign_drive_files_cannot_bypass_attachment_boundary(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($other); $this->scanner('clean'); $foreign = $this->image();
        $this->actingAs($owner); $project = $this->createProject();
        $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => $foreign->public_id])->assertNotFound();
        $this->postJson(route('workspace.design.assets.attach', $project['id']), ['file' => UploadedFile::fake()->createWithContent('bypass.png', 'raw upload')])->assertUnprocessable();
        $this->assertDatabaseCount('workspace_design_assets', 0);
    }

    public function test_editor_uses_published_manifest_same_origin_config_and_non_executable_json(): void
    {
        $this->actingAs($this->makeUser('siswa'));
        $project = $this->createProject();
        // A real frontend build must precede this acceptance test; do not fabricate a manifest.
        $this->assertFileExists(public_path('workspace-design-build/.vite/manifest.json'));
        $response = $this->get(route('workspace.design.editor', $project['id']))->assertOk();
        $response->assertSee('workspace-design-config', false)->assertSee('type="application/json"', false)->assertSee('/workspace-design-build/assets/', false);
        $this->assertDoesNotMatchRegularExpression('/localhost:3000/', $response->getContent());
        $this->assertSame(1, preg_match('~<script id="workspace-design-config" type="application/json">(.+?)</script>~s', $response->getContent(), $matches));
        $config = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('/workspace/design/'.$project['id'].'/document', $config['endpoint']);
        $this->assertStringContainsString("connect-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }
}
