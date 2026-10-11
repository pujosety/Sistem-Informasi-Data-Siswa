<?php

namespace App\Services;

use App\Models\WorkspaceWopiLock;
use Closure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceWopiLockService
{
    public function handle(int $fileId, string $override, ?string $lock, ?string $oldLock = null): Response
    {
        return DB::transaction(function () use ($fileId, $override, $lock, $oldLock): Response {
            $record = WorkspaceWopiLock::query()->whereKey($fileId)->lockForUpdate()->firstOrFail();
            $current = $this->activeLock($record);
            $expiresAt = now()->addMinutes((int) config('workspace.wopi.lock_ttl_minutes', 30));

            return match (strtoupper($override)) {
                'LOCK' => $this->lock($record, $current, $lock, $expiresAt),
                'UNLOCK' => $this->unlock($record, $current, $lock),
                'REFRESH_LOCK' => $this->refresh($record, $current, $lock, $expiresAt),
                'GET_LOCK' => $this->ok($current),
                'UNLOCK_AND_RELOCK' => $this->unlockAndRelock($record, $current, $oldLock, $lock, $expiresAt),
                default => response('Unsupported WOPI operation.', 501),
            };
        });
    }

    /** Run a save while the matching file lock row remains held. */
    public function withCurrentLock(int $fileId, ?string $lock, Closure $operation): mixed
    {
        return DB::transaction(function () use ($fileId, $lock, $operation): mixed {
            $record = WorkspaceWopiLock::query()->whereKey($fileId)->lockForUpdate()->firstOrFail();
            $current = $this->activeLock($record);

            if ($current === null || $lock === null || ! hash_equals($current, $lock)) {
                return $this->conflict($current);
            }

            return $operation();
        });
    }

    private function lock(WorkspaceWopiLock $record, ?string $current, ?string $lock, $expiresAt): Response
    {
        if (! $this->validLock($lock)) {
            return response('A valid X-WOPI-Lock header is required.', 400);
        }

        if ($current !== null && ! hash_equals($current, $lock)) {
            return $this->conflict($current);
        }

        $record->forceFill(['lock_value' => $lock, 'expires_at' => $expiresAt])->save();

        return $this->ok($lock);
    }

    private function unlock(WorkspaceWopiLock $record, ?string $current, ?string $lock): Response
    {
        if ($lock === null || $current === null || ! hash_equals($current, $lock)) {
            return $this->conflict($current);
        }

        $record->forceFill(['lock_value' => null, 'expires_at' => null])->save();

        return $this->ok(null);
    }

    private function refresh(WorkspaceWopiLock $record, ?string $current, ?string $lock, $expiresAt): Response
    {
        if ($lock === null || $current === null || ! hash_equals($current, $lock)) {
            return $this->conflict($current);
        }

        $record->forceFill(['expires_at' => $expiresAt])->save();

        return $this->ok($current);
    }

    private function unlockAndRelock(
        WorkspaceWopiLock $record,
        ?string $current,
        ?string $oldLock,
        ?string $newLock,
        $expiresAt,
    ): Response {
        if (! $this->validLock($newLock) || $oldLock === null || $current === null || ! hash_equals($current, $oldLock)) {
            return $this->conflict($current);
        }

        $record->forceFill(['lock_value' => $newLock, 'expires_at' => $expiresAt])->save();

        return $this->ok($newLock);
    }

    private function activeLock(WorkspaceWopiLock $record): ?string
    {
        if ($record->lock_value === null) {
            return null;
        }

        if ($record->expires_at === null || $record->expires_at->isPast()) {
            $record->forceFill(['lock_value' => null, 'expires_at' => null])->save();

            return null;
        }

        return (string) $record->lock_value;
    }

    private function validLock(?string $lock): bool
    {
        return is_string($lock) && $lock !== '' && strlen($lock) <= 1024;
    }

    private function ok(?string $lock): Response
    {
        return response('', 200);
    }

    private function conflict(?string $current): Response
    {
        return response('', 409)->header('X-WOPI-Lock', $current ?? '');
    }
}
