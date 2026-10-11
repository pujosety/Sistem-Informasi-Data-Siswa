<?php

namespace Tests\Feature;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceWopiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
        $this->fakeCleanWorkspaceScanner();
        Cache::forget('workspace.collabora.discovery.'.sha1('http://collabora:9980/hosting/discovery'));
    }

    public function test_owner_can_issue_scoped_session_and_collabora_can_read_file_info_and_bytes(): void
    {
        $this->fakeNoProofDiscovery();
        $owner = $this->makeUser('siswa');
        $schoolId = app(SchoolContext::class)->id();

        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('kelas.docx', 'document bytes'),
            ])
            ->assertRedirect(route('workspace.drive.index'));

        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $session = $this->actingAs($owner)
            ->postJson('/workspace/drive/files/'.$file->public_id.'/wopi-session')
            ->assertCreated()
            ->assertJsonStructure(['access_token', 'access_token_ttl']);
        $token = $session->json('access_token');
        $tokenHash = hash('sha256', $token);

        $this->assertDatabaseHas('workspace_wopi_tokens', [
            'workspace_file_id' => $file->id,
            'school_id' => $schoolId,
            'user_id' => $owner->id,
            'token_hash' => $tokenHash,
            'can_write' => 1,
        ]);
        $this->assertGreaterThan((int) now()->valueOf(), $session->json('access_token_ttl'));
        $this->assertDatabaseMissing('workspace_wopi_tokens', ['token_hash' => $token]);

        $query = http_build_query(['access_token' => $token]);
        $info = $this->get('/wopi/files/'.$file->public_id.'?'.$query)
            ->assertOk()
            ->assertJsonPath('BaseFileName', 'kelas.docx')
            ->assertJsonPath('OwnerId', (string) $owner->id)
            ->assertJsonPath('UserId', (string) $owner->id)
            ->assertJsonPath('UserCanWrite', true)
            ->assertJsonPath('SupportsLocks', true);
        $this->assertStringContainsString('no-store', (string) $info->headers->get('Cache-Control'));

        $content = $this->get('/wopi/files/'.$file->public_id.'/contents?'.$query)
            ->assertOk();
        $this->assertSame('document bytes', $content->streamedContent());
    }

    public function test_wopi_putfile_requires_the_current_lock_and_creates_a_new_drive_version(): void
    {
        $this->fakeNoProofDiscovery();
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('tugas.docx', 'versi satu'),
            ])
            ->assertRedirect(route('workspace.drive.index'));
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $firstVersion = $file->versions()->firstOrFail();
        $token = $this->actingAs($owner)
            ->postJson('/workspace/drive/files/'.$file->public_id.'/wopi-session')
            ->assertCreated()
            ->json('access_token');
        $url = '/wopi/files/'.$file->public_id.'?'.http_build_query(['access_token' => $token]);
        $contentsUrl = '/wopi/files/'.$file->public_id.'/contents?'.http_build_query(['access_token' => $token]);

        $this->call('POST', $url, [], [], [], [
            'HTTP_X_WOPI_OVERRIDE' => 'LOCK',
            'HTTP_X_WOPI_LOCK' => 'lock-a',
        ])->assertOk()->assertHeaderMissing('X-WOPI-Lock');

        $this->call('POST', $contentsUrl, [], [], [], [
            'HTTP_X_WOPI_OVERRIDE' => 'PUT',
            'HTTP_X_WOPI_LOCK' => 'lock-a',
            'CONTENT_TYPE' => 'application/octet-stream',
        ], str_repeat('x', ((int) config('workspace.max_file_kb') * 1024) + 1))
            ->assertStatus(413);
        $this->assertSame(1, $file->fresh()->current_version);

        $this->call('POST', $contentsUrl, [], [], [], [
            'HTTP_X_WOPI_OVERRIDE' => 'PUT',
            'HTTP_X_WOPI_LOCK' => 'wrong-lock',
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'ditolak')->assertStatus(409)->assertHeader('X-WOPI-Lock', 'lock-a');
        $this->assertSame(1, $file->fresh()->current_version);

        $this->call('POST', $contentsUrl, [], [], [], [
            'HTTP_X_WOPI_OVERRIDE' => 'PUT',
            'HTTP_X_WOPI_LOCK' => 'lock-a',
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'versi dua')->assertOk()->assertHeader('X-WOPI-ItemVersion', '2');

        $file->refresh();
        $this->assertSame(2, $file->current_version);
        $this->assertCount(2, $file->versions()->get());
        $secondVersion = $file->versions()->where('version_number', 2)->firstOrFail();
        $this->assertSame($owner->id, $secondVersion->created_by);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'action' => 'workspace.file.wopi_saved',
            'subject_type' => 'WorkspaceFile',
            'subject_id' => $file->id,
        ]);
        Storage::disk('workspace')->assertExists($firstVersion->path);

        $download = $this->actingAs($owner)
            ->get(route('workspace.drive.files.download', $file))
            ->assertOk();
        $this->assertSame('versi dua', $download->streamedContent());
    }

    public function test_owner_can_launch_a_document_in_the_collabora_frame_from_discovery(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('materi.docx', 'document bytes'),
            ])
            ->assertRedirect(route('workspace.drive.index'));
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $this->actingAs($owner)
            ->get(route('workspace.drive.index'))
            ->assertOk()
            ->assertSee(route('workspace.drive.files.collabora', $file), false);

        config([
            'workspace.collabora.internal_url' => 'http://collabora:9980',
            'workspace.collabora.public_url' => 'http://localhost:9980',
            'workspace.wopi.host_url' => 'http://host.docker.internal:8000',
        ]);
        \Illuminate\Support\Facades\Cache::forget('workspace.collabora.discovery.'.sha1('http://collabora:9980/hosting/discovery'));
        \Illuminate\Support\Facades\Http::fake([
            'http://collabora:9980/hosting/discovery' => \Illuminate\Support\Facades\Http::response(
                '<wopi-discovery><net-zone name="external-http"><app name="writer"><action name="edit" ext="docx" urlsrc="http://collabora:9980/browser/abc/cool.html?" /></app></net-zone></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $response = $this->actingAs($owner)
            ->get('/workspace/drive/files/'.$file->public_id.'/collabora')
            ->assertOk()
            ->assertSee('name="collabora-editor"', false)
            ->assertSee('action="http://localhost:9980/browser/abc/cool.html?"', false)
            ->assertSee('http://host.docker.internal:8000/wopi/files/'.$file->public_id, false)
            ->assertSee('name="access_token"', false)
            ->assertSee('referrerpolicy="no-referrer"', false);

        $this->assertDatabaseCount('workspace_wopi_tokens', 1);
    }

    public function test_wopi_tokens_are_bound_to_one_file_owner_school_and_expiry(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('milik.docx', 'owned'),
            ])
            ->assertRedirect(route('workspace.drive.index'));
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('lain.docx', 'other'),
            ])
            ->assertRedirect(route('workspace.drive.index'));
        $otherFile = WorkspaceFile::query()->where('owner_id', $owner->id)->where('id', '!=', $file->id)->firstOrFail();

        $token = $this->actingAs($owner)
            ->postJson('/workspace/drive/files/'.$file->public_id.'/wopi-session')
            ->assertCreated()
            ->json('access_token');
        $query = http_build_query(['access_token' => $token]);

        $this->get('/wopi/files/'.$otherFile->public_id.'?'.$query)->assertUnauthorized();
        $this->actingAs($other)
            ->postJson('/workspace/drive/files/'.$file->public_id.'/wopi-session')
            ->assertNotFound();

        DB::table('workspace_wopi_tokens')->where('token_hash', hash('sha256', $token))
            ->update(['expires_at' => now()->subMinute()]);
        $this->get('/wopi/files/'.$file->public_id.'?'.$query)->assertUnauthorized();
    }

    public function test_non_local_wopi_request_fails_closed_without_discovery_proof_keys(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('proof.docx', 'proof bytes'),
            ])
            ->assertRedirect(route('workspace.drive.index'));
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $token = $this->actingAs($owner)
            ->postJson('/workspace/drive/files/'.$file->public_id.'/wopi-session')
            ->assertCreated()
            ->json('access_token');

        config([
            'app.env' => 'production',
            'workspace.collabora.internal_url' => 'https://collabora.example.test',
        ]);
        Cache::forget('workspace.collabora.discovery.'.sha1('https://collabora.example.test/hosting/discovery'));
        Http::fake([
            'https://collabora.example.test/hosting/discovery' => Http::response(
                '<wopi-discovery><net-zone name="external-https" /></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->get('/wopi/files/'.$file->public_id.'?'.http_build_query(['access_token' => $token]))
            ->assertUnauthorized();
    }

    public function test_advertised_proof_key_requires_a_valid_proof_even_in_local_mode(): void
    {
        $owner = $this->makeUser('siswa');
        $this->actingAs($owner)
            ->post('/workspace/drive/files', [
                'file' => UploadedFile::fake()->createWithContent('signed.docx', 'signed bytes'),
            ])
            ->assertRedirect(route('workspace.drive.index'));
        $file = WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
        $token = $this->actingAs($owner)
            ->postJson('/workspace/drive/files/'.$file->public_id.'/wopi-session')
            ->assertCreated()
            ->json('access_token');

        config(['app.env' => 'local']);
        Cache::forget('workspace.collabora.discovery.'.sha1('http://collabora:9980/hosting/discovery'));
        Http::fake([
            'http://collabora:9980/hosting/discovery' => Http::response(
                '<wopi-discovery><proof-key modulus="invalid" exponent="AQAB" /></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->get('/wopi/files/'.$file->public_id.'?'.http_build_query(['access_token' => $token]))
            ->assertUnauthorized();
    }

    private function fakeNoProofDiscovery(): void
    {
        config([
            'app.env' => 'testing',
            'workspace.collabora.internal_url' => 'http://collabora:9980',
            'workspace.wopi.host_url' => 'http://host.docker.internal:8000',
        ]);
        Cache::forget('workspace.collabora.discovery.'.sha1('http://collabora:9980/hosting/discovery'));
        Http::fake([
            'http://collabora:9980/hosting/discovery' => Http::response(
                '<wopi-discovery><net-zone name="external-http" /></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);
    }
}
