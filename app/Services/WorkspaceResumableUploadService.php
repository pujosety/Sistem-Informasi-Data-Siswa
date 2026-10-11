<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceUploadSession;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WorkspaceResumableUploadService
{
    public function __construct(private WorkspaceDriveService $drive, private SchoolContext $schools) {}

    public function limits(): array
    {
        return [
            'max_bytes' => max(1, (int) config('workspace.uploads.max_bytes', 104857600)),
            // Hard ceiling retains PHP's existing 2M upload/post limits.
            'chunk_bytes' => max(1, min(1048576, (int) config('workspace.uploads.chunk_bytes', 1048576))),
        ];
    }

    public function create(User $owner, string $name, int $bytes, string $checksum, ?string $folder): WorkspaceUploadSession
    {
        $school = $this->school();
        $limits = $this->limits();
        $this->require($bytes > 0 && $bytes <= $limits['max_bytes'], 'size', 'Ukuran berkas melewati batas unggahan.');
        $this->require((bool) preg_match('/\A[a-f0-9]{64}\z/', $checksum), 'checksum', 'SHA-256 tidak valid.');
        $this->require(ceil($bytes / $limits['chunk_bytes']) <= max(1, min(4096, (int) config('workspace.uploads.max_chunks', 1024))), 'size', 'Jumlah potongan melewati batas konfigurasi.');
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name))) ?? '');
        $this->require($name !== '' && mb_strlen($name) <= 255, 'name', 'Nama berkas tidak valid.');

        return DB::transaction(function () use ($owner, $school, $limits, $name, $bytes, $checksum, $folder) {
            abort_unless(DB::table('workspace_upload_capacity')->where('id', 1)->lockForUpdate()->first(), 503, 'Upload capacity schema is not ready.');
            $this->lockOwner($owner);
            $parent = $this->drive->folderFor($owner, $folder);
            // Current reads: expired-but-uncleaned reservations still count.
            $sessions = WorkspaceUploadSession::query()->where('reserved_bytes', '>', 0)
                ->orderBy('id')->lockForUpdate()->get(['owner_id', 'school_id', 'total_bytes', 'reserved_bytes']);
            $owned = $sessions->where('owner_id', $owner->id);
            $reservation = $bytes * 3; // chunks + assembly + in-flight final object
            $this->require($owned->count() < max(1, (int) config('workspace.uploads.max_sessions_per_owner', 3)), 'size', 'Terlalu banyak sesi unggahan aktif.');
            $this->require($reservation <= max(0, (int) config('workspace.uploads.owner_capacity_bytes', 629145600) - (int) $owned->sum('reserved_bytes')), 'size', 'Kapasitas sementara pengguna penuh.');
            $this->require($reservation <= max(0, (int) config('workspace.uploads.capacity_bytes', 1073741824) - (int) $sessions->sum('reserved_bytes')), 'size', 'Kapasitas unggahan sementara penuh.');
            $retained = DB::table('workspace_file_versions')
                ->join('workspace_files', 'workspace_files.id', '=', 'workspace_file_versions.workspace_file_id')
                ->where('workspace_files.owner_id', $owner->id)->where('workspace_files.school_id', $school)
                ->orderBy('workspace_files.id')->orderBy('workspace_file_versions.id')->lockForUpdate()
                ->get(['workspace_file_versions.size_bytes'])->sum('size_bytes');
            $pending = $owned->where('school_id', $school)->sum('total_bytes');
            $this->require($bytes <= max(0, $this->drive->quotaBytesFor($owner) - $retained - $pending), 'size', 'Kuota penyimpanan Workspace tidak mencukupi.');

            return WorkspaceUploadSession::create([
                'public_id' => (string) Str::uuid(), 'school_id' => $school, 'owner_id' => $owner->id,
                'parent_public_id' => $parent?->public_id, 'name' => $name, 'total_bytes' => $bytes,
                'chunk_bytes' => $limits['chunk_bytes'], 'checksum_sha256' => $checksum,
                'receipts' => [], 'temp_key' => (string) Str::uuid(), 'target_public_id' => (string) Str::uuid(),
                'reserved_bytes' => $reservation, 'state' => 'open',
                'expires_at' => now()->addMinutes(max(1, (int) config('workspace.uploads.ttl_minutes', 1440))),
            ]);
        });
    }

    public function status(User $owner, string $id): array
    {
        return $this->present($this->find($owner, $id));
    }

    public function chunk(User $owner, string $id, int $index, string $checksum, UploadedFile $chunk): array
    {
        return DB::transaction(function () use ($owner, $id, $index, $checksum, $chunk) {
            $this->lockOwner($owner);
            $session = $this->find($owner, $id, true);
            $this->assertOpen($session);
            $this->require($chunk->isValid(), 'chunk', 'Potongan unggahan tidak valid.');
            $size = (int) $chunk->getSize();
            $count = (int) ceil($session->total_bytes / $session->chunk_bytes);
            $this->require($index >= 0 && $index < $count, 'index', 'Indeks potongan di luar batas.');
            $expected = min($session->chunk_bytes, $session->total_bytes - $index * $session->chunk_bytes);
            $this->require($size === $expected, 'chunk', 'Jumlah byte potongan harus persis sesuai sesi.');
            $actual = hash_file('sha256', $chunk->getRealPath());
            $this->require((bool) preg_match('/\A[a-f0-9]{64}\z/', $checksum) && hash_equals($checksum, $actual ?: ''), 'checksum', 'Checksum potongan tidak cocok.');
            $receipts = $session->receipts;
            $path = $session->temp_key.'/'.$index.'.chunk';
            if ($index < $session->next_index) {
                $this->require(isset($receipts[$index]) && hash_equals($receipts[$index], $checksum), 'checksum', 'Percobaan ulang berisi potongan berbeda.');
                // Repair an acknowledged chunk lost to a filesystem/DB rollback.
                $disk = $this->temporaryDisk();
                if (! $disk->exists($path) || $disk->size($path) !== $size
                    || ! hash_equals($checksum, hash_file('sha256', $disk->path($path)) ?: '')) {
                    $this->writeChunk($path, $chunk);
                }
                return $this->present($session);
            }
            $this->require($index === $session->next_index, 'index', 'Potongan harus diunggah berurutan.');
            $this->writeChunk($path, $chunk);
            $receipts[$index] = $checksum;
            $session->update([
                'receipts' => $receipts, 'next_index' => $index + 1,
                'received_bytes' => $session->received_bytes + $size,
            ]);
            return $this->present($session);
        });
    }

    public function finalize(User $owner, string $id): array
    {
        // DB owner/session locks serialize retries, cleanup and concurrent tabs.
        // Deterministic generated target lets a retry overwrite crash orphans.
        return DB::transaction(function () use ($owner, $id) {
            $this->lockOwner($owner);
            $session = $this->find($owner, $id, true);
            if ($session->state === 'completed') {
                return $this->present($session);
            }
            $this->assertOpen($session);
            $this->require($session->received_bytes === $session->total_bytes, 'size', 'Unggahan belum lengkap.');
            $disk = $this->temporaryDisk();
            $assembly = $session->temp_key.'/assembled';
            // fopen w+b truncates a previous failed assembly: no growing append.
            $output = fopen($disk->path($assembly), 'w+b');
            if ($output === false) {
                throw new RuntimeException('Cannot open private upload assembly.');
            }
            chmod($disk->path($assembly), 0600);
            try {
                $hash = hash_init('sha256');
                $copied = 0;
                foreach ($session->receipts as $index => $receipt) {
                    $input = $disk->readStream($session->temp_key.'/'.$index.'.chunk');
                    if ($input === false) {
                        throw new RuntimeException('Upload chunk missing; retry this session.');
                    }
                    $chunkHash = hash_init('sha256');
                    $chunkSize = 0;
                    try {
                        while (! feof($input)) {
                            $buffer = fread($input, 65536);
                            if ($buffer === false) {
                                throw new RuntimeException('Upload chunk read failed.');
                            }
                            $chunkSize += strlen($buffer);
                            $copied += strlen($buffer);
                            $this->require($chunkSize <= $session->chunk_bytes && $copied <= $session->total_bytes, 'chunk', 'Potongan melebihi ukuran sesi.');
                            hash_update($hash, $buffer);
                            hash_update($chunkHash, $buffer);
                            if (fwrite($output, $buffer) !== strlen($buffer)) {
                                throw new RuntimeException('Upload assembly write failed.');
                            }
                        }
                    } finally {
                        fclose($input);
                    }
                    $expected = min($session->chunk_bytes, $session->total_bytes - $index * $session->chunk_bytes);
                    $this->require($chunkSize === $expected && hash_equals($receipt, hash_final($chunkHash)), 'chunk', 'Potongan tersimpan rusak.');
                }
                $this->require($copied === $session->total_bytes && hash_equals($session->checksum_sha256, hash_final($hash)), 'checksum', 'Checksum berkas lengkap tidak cocok.');
                if (! fflush($output) || ! rewind($output)) {
                    throw new RuntimeException('Cannot flush/rewind upload assembly.');
                }
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($disk->path($assembly)) ?: 'application/octet-stream';
                $file = $this->drive->ingestTrustedStream(
                    $output, $owner, $session->name, $session->total_bytes, $session->checksum_sha256,
                    $mime, $session->target_public_id, $session->parent_public_id,
                );
                $session->update(['state' => 'completed', 'result_public_id' => $file->public_id]);
                app(AuditService::class)->logAs((int) $owner->id, 'workspace.file.uploaded', $file, 'Mengunggah berkas melalui sesi resumable.', [
                    'size_bytes' => $file->size_bytes, 'mime_type' => $file->mime_type, 'resumable' => true,
                ]);
                // Register after the OUTERMOST commit. A deletion failure leaves
                // the reservation charged and cleanup can retry safely.
                DB::afterCommit(fn () => $this->releaseTemporary($session->id));
                return $this->present($session);
            } catch (Throwable $error) {
                // The DB transaction will roll back; target is exclusively this
                // session's generated UUID and cannot name someone else's file.
                if (isset($file)) {
                    try {
                        Storage::disk('workspace')->delete($this->targetPath($session));
                    } catch (Throwable $cleanupError) {
                        report($cleanupError); // durable session cleanup retries
                    }
                }
                throw $error;
            } finally {
                fclose($output);
                $disk->delete($assembly);
            }
        });
    }

    public function cancel(User $owner, string $id): array
    {
        return DB::transaction(function () use ($owner, $id) {
            $this->lockOwner($owner);
            $session = $this->find($owner, $id, true);
            if ($session->state !== 'completed') {
                $session->update(['state' => 'cancelled']);
            }
            DB::afterCommit(fn () => $this->releaseTemporary($session->id));
            return $this->present($session);
        });
    }

    /** Internal maintenance only; no client ID/tenant/path inputs. */
    public function cleanup(): int
    {
        $count = 0;
        WorkspaceUploadSession::query()->where(function ($query) {
            $query->where('expires_at', '<=', now())->orWhere('state', '!=', 'open');
        })->orderBy('id')->chunkById(100, function ($sessions) use (&$count) {
            foreach ($sessions as $session) {
                $this->releaseTemporary($session->id);
                $count++;
            }
        });
        // Completed UUID tombstones are kept for a bounded retry window.
        WorkspaceUploadSession::query()->where('reserved_bytes', 0)->where('state', '!=', 'open')
            ->where('updated_at', '<', now()->subDays(max(1, (int) config('workspace.uploads.result_retention_days', 7))))->delete();
        return $count;
    }

    private function releaseTemporary(int $id): void
    {
        // Obtain stable owner first (unlocked lookup does not change scope).
        $candidate = WorkspaceUploadSession::query()->find($id);
        if (! $candidate) {
            return;
        }
        DB::transaction(function () use ($candidate) {
            User::query()->whereKey($candidate->owner_id)->lockForUpdate()->firstOrFail();
            $session = WorkspaceUploadSession::query()->whereKey($candidate->id)->lockForUpdate()->first();
            if (! $session || ($session->state === 'open' && $session->expires_at->isFuture())) {
                return;
            }
            if ($session->state === 'open') {
                $session->update(['state' => 'expired']);
            }
            if ($session->reserved_bytes === 0) {
                return;
            }
            $this->require($this->temporaryDisk()->deleteDirectory($session->temp_key), 'upload', 'Gagal membersihkan penyimpanan sementara.');
            if ($session->state !== 'completed') {
                // Defensive current read: never remove a retained version even
                // if a manually repaired session has stale state metadata.
                $retained = WorkspaceFile::withoutGlobalScopes()->where('public_id', $session->target_public_id)
                    ->where('owner_id', $session->owner_id)->where('school_id', $session->school_id)
                    ->lockForUpdate()->first();
                if ($retained) {
                    $session->update(['state' => 'completed', 'result_public_id' => $retained->public_id]);
                } else {
                    // Reap an object written before a process/DB crash.
                    Storage::disk('workspace')->delete($this->targetPath($session));
                }
            }
            $session->update(['reserved_bytes' => 0]);
        });
    }

    public function present(WorkspaceUploadSession $session): array
    {
        return [
            'id' => $session->public_id, 'name' => $session->name, 'size' => $session->total_bytes,
            'checksum' => $session->checksum_sha256, 'chunk_bytes' => $session->chunk_bytes,
            'received_bytes' => $session->received_bytes, 'next_index' => $session->next_index,
            'state' => $session->state === 'open' && $session->expires_at->isPast() ? 'expired' : $session->state,
            'expires_at' => $session->expires_at->toIso8601String(), 'file_id' => $session->result_public_id,
        ];
    }

    private function find(User $owner, string $id, bool $lock = false): WorkspaceUploadSession
    {
        abort_unless(Str::isUuid($id), 404);
        return WorkspaceUploadSession::query()->where('public_id', $id)->where('owner_id', $owner->id)
            ->where('school_id', $this->school())->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    private function school(): int
    {
        $school = $this->schools->current();
        abort_unless($school && $school->is_active, 403);
        return (int) $school->id;
    }

    private function lockOwner(User $owner): void
    {
        User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
    }

    private function assertOpen(WorkspaceUploadSession $session): void
    {
        abort_if($session->expires_at->isPast(), 410, 'Sesi unggahan kedaluwarsa.');
        abort_unless($session->state === 'open', 409, 'Sesi unggahan tidak aktif.');
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function temporaryDisk(): FilesystemAdapter
    {
        // Not a registered/servable disk; no URL, no public symlink, no S3 ACL.
        return Storage::build([
            'driver' => 'local', 'root' => config('workspace.uploads.temp_root', storage_path('app/workspace-upload-sessions')),
            'visibility' => 'private', 'directory_visibility' => 'private', 'throw' => true,
        ]);
    }

    private function writeChunk(string $path, UploadedFile $chunk): void
    {
        $stream = fopen($chunk->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Cannot open uploaded chunk.');
        }
        try {
            if (! $this->temporaryDisk()->put($path, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Cannot persist upload chunk.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function targetPath(WorkspaceUploadSession $session): string
    {
        return 'schools/'.$session->school_id.'/users/'.$session->owner_id.'/'.$session->target_public_id.'.bin';
    }
}
