<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceWopiToken;
use App\Services\WorkspaceWopiDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkspaceWopiSessionOriginTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $issuedPlaintext = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
        $this->fakeCleanWorkspaceScanner();
    }

    public function test_owner_can_revoke_their_exact_wopi_session_and_it_stops_authenticating(): void
    {
        $owner = $this->makeUser('siswa');
        $file = $this->createDocx($owner);
        $session = $this->issueSession($owner, $file);

        $this->actingAs($owner)
            ->delete(route('workspace.drive.files.wopi-session.revoke', [$file, $session->id]))
            ->assertRedirect(route('workspace.drive.index'));

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->get('/wopi/files/'.$file->public_id.'?access_token='.urlencode($this->plainTokenFor($session)))
            ->assertUnauthorized();
    }

    public function test_another_owner_cannot_revoke_a_wopi_session(): void
    {
        $owner = $this->makeUser('siswa');
        $other = $this->makeUser('siswa');
        $file = $this->createDocx($owner);
        $session = $this->issueSession($owner, $file);

        $this->actingAs($other)
            ->delete(route('workspace.drive.files.wopi-session.revoke', [$file, $session->id]))
            ->assertNotFound();

        $this->assertNull($session->fresh()->revoked_at);
    }

    public function test_editor_page_exposes_an_owner_scoped_close_session_action(): void
    {
        $owner = $this->makeUser('siswa');
        $file = $this->createDocx($owner);
        $this->fakeDiscovery('<action name="edit" ext="docx" urlsrc="http://collabora:9980/browser/abc/cool.html?" />');

        $response = $this->actingAs($owner)->get(route('workspace.drive.files.collabora', $file))->assertOk();
        $session = WorkspaceWopiToken::query()->where('workspace_file_id', $file->id)->firstOrFail();

        $response->assertSee(route('workspace.drive.files.wopi-session.revoke', [$file, $session->id]), false);
        $response->assertSee('Tutup editor dan cabut sesi', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_non_local_discovery_requires_https_for_configured_collabora_urls(): void
    {
        config([
            'app.env' => 'production',
            'workspace.collabora.internal_url' => 'http://collabora:9980',
            'workspace.collabora.public_url' => 'https://office.example.test',
        ]);
        Http::fake([
            'http://collabora:9980/hosting/discovery' => Http::response(
                '<wopi-discovery><net-zone><app><action name="edit" ext="docx" urlsrc="http://collabora:9980/browser/abc/cool.html?" /></app></net-zone></wopi-discovery>',
                200,
            ),
        ]);

        try {
            app(WorkspaceWopiDiscoveryService::class)->editActionUrl('docx');
            self::fail('Expected an insecure non-local Collabora URL to be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }
    }

    public function test_discovery_action_with_userinfo_is_rejected(): void
    {
        config(['app.env' => 'local']);
        $this->fakeDiscovery('<action name="edit" ext="docx" urlsrc="http://user:pass@collabora:9980/browser/abc/cool.html?" />');

        try {
            app(WorkspaceWopiDiscoveryService::class)->editActionUrl('docx');
            self::fail('Expected an action URL containing userinfo to be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }
    }

    public function test_discovery_action_with_fragment_is_rejected(): void
    {
        config(['app.env' => 'local']);
        $this->fakeDiscovery('<action name="edit" ext="docx" urlsrc="http://collabora:9980/browser/abc/cool.html#fragment" />');

        try {
            app(WorkspaceWopiDiscoveryService::class)->editActionUrl('docx');
            self::fail('Expected an action URL containing a fragment to be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }
    }

    public function test_non_local_wopi_host_requires_https(): void
    {
        config([
            'app.env' => 'production',
            'workspace.wopi.host_url' => 'http://host.example.test',
        ]);

        try {
            app(WorkspaceWopiDiscoveryService::class)->validatedWopiHostUrl();
            self::fail('Expected an insecure non-local WOPI host URL to be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }
    }

    private function createDocx(User $owner): WorkspaceFile
    {
        $this->actingAs($owner)->post('/workspace/drive/files', [
            'file' => UploadedFile::fake()->createWithContent('lesson.docx', 'document bytes'),
        ])->assertRedirect(route('workspace.drive.index'));

        return WorkspaceFile::query()->where('owner_id', $owner->id)->firstOrFail();
    }

    private function issueSession(User $owner, WorkspaceFile $file): WorkspaceWopiToken
    {
        $response = $this->actingAs($owner)
            ->postJson(route('workspace.drive.files.wopi-session', $file))
            ->assertCreated()
            ->assertJsonStructure(['access_token', 'access_token_ttl', 'session_id']);

        $accessToken = (string) $response->json('access_token');
        $sessionId = (int) $response->json('session_id');
        $this->issuedPlaintext[$sessionId] = $accessToken;

        return WorkspaceWopiToken::query()->findOrFail($sessionId);
    }

    private function plainTokenFor(WorkspaceWopiToken $session): string
    {
        return $this->issuedPlaintext[$session->id] ?? '';
    }

    private function fakeDiscovery(string $action): void
    {
        config([
            'app.env' => 'local',
            'workspace.collabora.internal_url' => 'http://collabora:9980',
            'workspace.collabora.public_url' => 'http://localhost:9980',
            'workspace.wopi.host_url' => 'http://host.docker.internal:8000',
        ]);
        Http::fake([
            'http://collabora:9980/hosting/discovery' => Http::response(
                '<wopi-discovery><net-zone name="external-http"><app name="writer">'.$action.'</app></net-zone></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);
    }
}
