<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceWopiLock;
use App\Models\WorkspaceWopiToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceWopiTokenService
{
    public function __construct(private readonly WorkspaceDriveService $drive) {}

    /** @return array{record: WorkspaceWopiToken, plaintext: string} */
    public function issue(User $user, WorkspaceFile $file): array
    {
        $this->drive->assertOwner($user, $file);
        $this->assertCurrentClean($file);
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, config('workspace.wopi.supported_extensions', []), true), 415);

        $plaintext = Str::random(64);
        $expiresAt = now()->addMinutes((int) config('workspace.wopi.token_ttl_minutes', 120));
        $record = DB::transaction(function () use ($user, $file, $plaintext, $expiresAt): WorkspaceWopiToken {
            $token = WorkspaceWopiToken::create([
                'workspace_file_id' => $file->id,
                'school_id' => $file->school_id,
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plaintext),
                'can_write' => true,
                'expires_at' => $expiresAt,
            ]);

            WorkspaceWopiLock::query()->firstOrCreate(
                ['workspace_file_id' => $file->id],
                ['lock_value' => null, 'expires_at' => null],
            );

            return $token;
        });

        return ['record' => $record, 'plaintext' => $plaintext];
    }

    private function assertCurrentClean(WorkspaceFile $file): void
    {
        $version = $file->versions()->where('version_number', $file->current_version)->firstOrFail();
        app(WorkspaceMalwareScanService::class)->assertClean($version);
    }

    public function revoke(User $user, WorkspaceFile $file, int $sessionId): void
    {
        $this->drive->assertOwner($user, $file);

        $revoked = WorkspaceWopiToken::query()
            ->whereKey($sessionId)
            ->where('workspace_file_id', $file->id)
            ->where('school_id', $file->school_id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        abort_unless($revoked === 1, 404);
    }

    public function resolve(string $filePublicId, mixed $plaintext): WorkspaceWopiToken
    {
        abort_unless(
            is_string($plaintext) && preg_match('/\A[A-Za-z0-9]{64}\z/D', $plaintext) === 1,
            401,
        );

        $token = WorkspaceWopiToken::query()
            ->where('token_hash', hash('sha256', $plaintext))
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->whereHas('file', function ($query) use ($filePublicId): void {
                $query->withoutGlobalScopes()
                    ->where('public_id', $filePublicId)
                    ->whereNull('deleted_at');
            })
            ->first();

        abort_unless($token !== null, 401);

        $file = WorkspaceFile::withoutGlobalScopes()
            ->whereKey($token->workspace_file_id)
            ->where('public_id', $filePublicId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_unless(
            (int) $file->school_id === (int) $token->school_id
                && (int) $file->owner_id === (int) $token->user_id,
            401,
        );

        $user = User::query()->find($token->user_id);
        abort_unless($user !== null && $user->isActive(), 401);

        $this->assertCurrentClean($file);
        $token->setRelation('file', $file);
        $token->setRelation('user', $user);
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $token;
    }
}
