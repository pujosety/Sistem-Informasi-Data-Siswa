<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceWopiToken;
use App\Services\AuditService;
use App\Services\WorkspaceDriveService;
use App\Services\WorkspaceWopiLockService;
use App\Services\WorkspaceWopiBodyReader;
use App\Services\WorkspaceWopiDiscoveryService;
use App\Services\WorkspaceWopiRequestProofService;
use App\Services\WorkspaceWopiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceWopiController extends Controller
{
    public function __construct(
        private readonly WorkspaceDriveService $drive,
        private readonly WorkspaceWopiTokenService $tokens,
        private readonly WorkspaceWopiLockService $locks,
        private readonly WorkspaceWopiDiscoveryService $discovery,
        private readonly WorkspaceWopiRequestProofService $requestProofs,
        private readonly AuditService $audit,
    ) {}

    public function issueSession(Request $request, WorkspaceFile $workspaceFile): JsonResponse
    {
        $session = $this->createSession($request->user(), $workspaceFile);

        return response()->json($session, 201)
            ->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }

    public function edit(Request $request, WorkspaceFile $workspaceFile): Response
    {
        $owner = $request->user();
        $this->drive->assertOwner($owner, $workspaceFile);
        app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($this->drive->currentVersion($owner, $workspaceFile));
        $extension = strtolower(pathinfo($workspaceFile->name, PATHINFO_EXTENSION));
        $actionUrl = $this->discovery->editActionUrl($extension);
        $session = $this->createSession($owner, $workspaceFile);
        $folder = $this->drive->folderForFile($owner, $workspaceFile);

        return response()->view('workspace.drive.editor', [
            'file' => $workspaceFile,
            'actionUrl' => $actionUrl,
            'wopiSrc' => $session['WOPISrc'],
            'accessToken' => $session['access_token'],
            'accessTokenTtl' => $session['access_token_ttl'],
            'sessionId' => $session['session_id'],
            'backUrl' => route('workspace.drive.index', $folder ? ['folder' => $folder->public_id] : []),
        ])->withHeaders($this->privateHeaders() + ['Referrer-Policy' => 'no-referrer']);
    }

    public function revokeSession(Request $request, WorkspaceFile $workspaceFile, int $sessionId): RedirectResponse
    {
        $owner = $request->user();
        $this->drive->assertOwner($owner, $workspaceFile);
        $this->tokens->revoke($owner, $workspaceFile, $sessionId);
        $this->audit->log(
            'workspace.file.wopi_session.revoked',
            $workspaceFile,
            'Menutup sesi editor dokumen WOPI.',
            ['session_id' => $sessionId],
        );

        return redirect()->route('workspace.drive.index')
            ->with('success', 'Sesi editor ditutup dan token dicabut.');
    }

    /** @return array{WOPISrc: string, access_token: string, access_token_ttl: int, session_id: int} */
    private function createSession(User $owner, WorkspaceFile $workspaceFile): array
    {
        $this->drive->assertOwner($owner, $workspaceFile);
        $hostUrl = $this->discovery->validatedWopiHostUrl();
        $issued = $this->tokens->issue($owner, $workspaceFile);
        $expiresAt = $issued['record']->expires_at;

        $this->audit->log(
            'workspace.file.wopi_session.issued',
            $workspaceFile,
            'Membuka sesi editor dokumen WOPI.',
            ['expires_at' => $expiresAt->toIso8601String(), 'can_write' => true],
        );

        return [
            'WOPISrc' => $hostUrl.'/wopi/files/'.rawurlencode($workspaceFile->public_id),
            'access_token' => $issued['plaintext'],
            'access_token_ttl' => $expiresAt->getTimestamp() * 1000,
            'session_id' => (int) $issued['record']->getKey(),
        ];
    }

    public function checkFileInfo(Request $request, string $fileId): JsonResponse
    {
        $token = $this->resolveWopiRequestToken($request, $fileId);
        $file = $token->file;
        $version = $file->versions()->where('version_number', $file->current_version)->firstOrFail();
        app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($version);
        $user = $token->user;

        return response()->json([
            'BaseFileName' => $file->name,
            'OwnerId' => (string) $file->owner_id,
            'Size' => (int) $version->size_bytes,
            'UserId' => (string) $user->id,
            'UserFriendlyName' => $user->name ?: $user->email,
            'UserCanWrite' => (bool) $token->can_write,
            'UserCanRename' => false,
            'UserCanDelete' => false,
            'UserCanNotWriteRelative' => true,
            'SupportsLocks' => true,
            'SupportsGetLock' => true,
            'SupportsUpdate' => true,
            'Version' => (string) $version->version_number,
            'LastModifiedTime' => $file->updated_at->utc()->toIso8601String(),
        ])->withHeaders($this->privateHeaders());
    }

    public function getFile(Request $request, string $fileId): StreamedResponse
    {
        $token = $this->resolveWopiRequestToken($request, $fileId);
        $file = $token->file;
        $version = $file->versions()->where('version_number', $file->current_version)->firstOrFail();
        app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($version);
        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);
        $stream = $disk->readStream($version->path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $this->privateHeaders() + [
            'Content-Type' => $version->mime_type,
            'Content-Length' => (string) $version->size_bytes,
            'X-WOPI-ItemVersion' => (string) $version->version_number,
        ]);
    }

    public function putFile(Request $request, string $fileId): Response
    {
        if (strtoupper((string) $request->header('X-WOPI-Override')) !== 'PUT') {
            return response('Unsupported WOPI operation.', 501);
        }

        $token = $this->resolveWopiRequestToken($request, $fileId);
        abort_unless($token->can_write, 403);

        $maxBytes = (int) config('workspace.max_file_kb', 2048) * 1024;
        $contentLength = $request->headers->get('Content-Length');
        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > $maxBytes) {
            return response('File exceeds the configured upload limit.', 413);
        }

        $contents = app(WorkspaceWopiBodyReader::class)->read($request->getContent(true), $maxBytes);
        if ($contents === null) {
            return response('File exceeds the configured upload limit.', 413);
        }

        $file = $token->file;
        $result = $this->locks->withCurrentLock(
            $file->id,
            $request->header('X-WOPI-Lock'),
            fn (): WorkspaceFile => $this->drive->replaceWopiVersion(
                $token->user,
                $file,
                (int) $token->school_id,
                $contents,
            ),
        );

        if ($result instanceof Response) {
            return $result;
        }

        $savedVersion = $result->versions()->where('version_number', $result->current_version)->firstOrFail();
        if (app(\App\Services\WorkspaceMalwareScanService::class)->status($savedVersion) !== 'clean') {
            // Outside withCurrentLock transaction: retain and charge quarantined bytes.
            return response('Saved bytes quarantined; security check did not succeed.', 503)->withHeaders($this->privateHeaders());
        }

        $this->audit->logAs(
            (int) $token->user_id,
            'workspace.file.wopi_saved',
            $result,
            'Menyimpan perubahan dari Collabora Online.',
            ['version' => $result->current_version, 'size_bytes' => $result->size_bytes],
        );

        return response('', 200)->withHeaders($this->privateHeaders() + [
            'X-WOPI-ItemVersion' => (string) $result->current_version,
        ]);
    }

    public function operation(Request $request, string $fileId): Response
    {
        $token = $this->resolveWopiRequestToken($request, $fileId);
        abort_unless($token->can_write, 403);

        $override = strtoupper((string) $request->header('X-WOPI-Override'));
        if ($override === '') {
            return response('X-WOPI-Override is required.', 400);
        }

        return $this->locks->handle(
            $token->workspace_file_id,
            $override,
            $request->header('X-WOPI-Lock'),
            $request->header('X-WOPI-OldLock'),
        )->withHeaders($this->privateHeaders());
    }

    private function resolveWopiRequestToken(Request $request, string $fileId): WorkspaceWopiToken
    {
        $plaintext = $request->query('access_token');
        $token = $this->tokens->resolve($fileId, $plaintext);
        $this->requestProofs->authorize($request, $plaintext);

        return $token;
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
