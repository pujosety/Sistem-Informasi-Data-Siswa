<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use App\Models\WorkspaceFolder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Private, owner-scoped Drive operations; binary paths never leave this service. */
class WorkspaceDriveService
{
    private const DISK = 'workspace';

    public function folderFor(User $owner, ?string $publicId): ?WorkspaceFolder
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        abort_unless(Str::isUuid($publicId), 404);

        return WorkspaceFolder::query()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    public function folderForFile(User $owner, WorkspaceFile $file): ?WorkspaceFolder
    {
        $this->assertOwner($owner, $file);

        if ($file->parent_folder_id === null) {
            return null;
        }

        return WorkspaceFolder::query()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->whereKey($file->parent_folder_id)
            ->firstOrFail();
    }

    public function createFolder(User $owner, string $name, ?string $parentPublicId = null): WorkspaceFolder
    {
        $name = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', trim($name)) ?: '';

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Nama folder wajib diisi.']);
        }

        return DB::transaction(function () use ($owner, $name, $parentPublicId): WorkspaceFolder {
            $parent = $this->folderFor($owner, $parentPublicId);
            if ($parent) {
                $parent = WorkspaceFolder::query()
                    ->where('school_id', $this->schoolId())
                    ->where('owner_id', $owner->id)
                    ->whereKey($parent->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            return WorkspaceFolder::create([
                'public_id' => (string) Str::uuid(),
                'school_id' => $this->schoolId(),
                'owner_id' => $owner->id,
                'parent_folder_id' => $parent?->id,
                'name' => Str::limit($name, 180, ''),
            ]);
        });
    }

    public function foldersFor(User $owner, ?int $parentFolderId = null)
    {
        return WorkspaceFolder::query()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->where('parent_folder_id', $parentFolderId)
            ->orderBy('name')
            ->get();
    }

    public function filesFor(User $owner, ?int $parentFolderId = null)
    {
        return WorkspaceFile::query()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->where('parent_folder_id', $parentFolderId)
            ->with('versions')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function searchFilesFor(User $owner, string $query)
    {
        $schoolId = $this->schoolId();

        return WorkspaceFile::query()
            ->where('school_id', $schoolId)
            ->where('owner_id', $owner->id)
            ->where('name', 'like', '%'.$query.'%')
            ->with([
                'versions',
                'folder' => fn ($folders) => $folders
                    ->where('school_id', $schoolId)
                    ->where('owner_id', $owner->id),
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function favoriteFilesFor(User $owner, ?string $query = null)
    {
        return WorkspaceFile::query()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->where('is_favorite', true)
            ->when($query !== null && $query !== '', fn ($files) => $files->where('name', 'like', '%'.$query.'%'))
            ->with(['versions', 'folder'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function setFavorite(User $owner, WorkspaceFile $file, bool $favorite): WorkspaceFile
    {
        $this->assertOwner($owner, $file);
        $file->is_favorite = $favorite;
        $file->save();

        return $file->refresh();
    }

    public function searchFoldersFor(User $owner, string $query)
    {
        return WorkspaceFolder::query()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, WorkspaceFolder> */
    public function breadcrumbsFor(User $owner, ?WorkspaceFolder $folder): array
    {
        $schoolId = $this->schoolId();
        $crumbs = [];
        $visited = [];

        while ($folder !== null) {
            abort_unless(! isset($visited[$folder->id]), 404);
            $visited[$folder->id] = true;
            $crumbs[] = $folder;

            if ($folder->parent_folder_id === null) {
                break;
            }

            $folder = WorkspaceFolder::query()
                ->where('school_id', $schoolId)
                ->where('owner_id', $owner->id)
                ->whereKey($folder->parent_folder_id)
                ->firstOrFail();
        }

        return array_reverse($crumbs);
    }

    public function usedBytesFor(User $owner): int
    {
        return (int) $this->retainedVersionBytesQuery($owner, $this->schoolId())
            ->sum('workspace_file_versions.size_bytes');
    }

    public function quotaBytesFor(User $owner): int
    {
        $this->schoolId();

        return max(0, (int) config('workspace.quota_bytes', 1073741824));
    }

    private function retainedVersionBytesQuery(User $owner, int $schoolId): \Illuminate\Database\Query\Builder
    {
        // Explicit tenant predicates also work for token-scoped WOPI requests
        // without a browser SchoolContext. No soft-delete scope: Trash counts.
        return DB::table('workspace_file_versions')
            ->join('workspace_files', 'workspace_files.id', '=', 'workspace_file_versions.workspace_file_id')
            ->where('workspace_files.school_id', $schoolId)
            ->where('workspace_files.owner_id', $owner->id);
    }

    private function lockQuotaOwner(User $owner): void
    {
        // Stable even before the owner's first file. Every byte-appending path
        // locks owner -> optional folder -> file -> retained versions. WOPI may
        // already hold its lock row; Drive never acquires a WOPI lock afterward.
        // Owners shared across schools serialize, but usage stays school-local.
        User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
    }

    private function assertQuotaCapacity(User $owner, int $schoolId, int $additionalBytes, string $field = 'file'): void
    {
        // Current locking read, not a REPEATABLE READ snapshot possibly created
        // by an outer transaction before it waited for the owner lock. Aggregate
        // SELECT SUM ... FOR UPDATE is not a portable row-locking guarantee.
        $usedBytes = (int) $this->retainedVersionBytesQuery($owner, $schoolId)
            ->orderBy('workspace_files.id')
            ->orderBy('workspace_file_versions.id')
            ->lockForUpdate()
            ->get(['workspace_file_versions.size_bytes'])
            ->sum('size_bytes');
        $quotaBytes = max(0, (int) config('workspace.quota_bytes', 1073741824));

        // Versions are append-only: never deduct the previous current version.
        if ($additionalBytes < 0 || $additionalBytes > max(0, $quotaBytes - $usedBytes)) {
            throw ValidationException::withMessages([
                $field => 'Kuota penyimpanan Workspace tidak mencukupi. Semua versi dan berkas di Trash tetap dihitung.',
            ]);
        }
    }

    public function trashedFilesFor(User $owner)
    {
        $schoolId = $this->schoolId();

        return WorkspaceFile::onlyTrashed()
            ->where('school_id', $schoolId)
            ->where('owner_id', $owner->id)
            ->with([
                'versions',
                'folder' => fn ($folders) => $folders->withTrashed()
                    ->where('school_id', $schoolId)
                    ->where('owner_id', $owner->id),
            ])
            ->orderByDesc('deleted_at')
            ->get();
    }

    public function trashedFoldersFor(User $owner)
    {
        return WorkspaceFolder::onlyTrashed()
            ->where('school_id', $this->schoolId())
            ->where('owner_id', $owner->id)
            ->orderByDesc('deleted_at')
            ->get();
    }

    public function trashFile(User $owner, WorkspaceFile $file): WorkspaceFile
    {
        $this->assertOwner($owner, $file);

        return DB::transaction(function () use ($owner, $file): WorkspaceFile {
            $locked = WorkspaceFile::query()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->whereKey($file->id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked->delete();

            return $locked;
        });
    }

    public function restoreFile(User $owner, string $publicId): WorkspaceFile
    {
        return DB::transaction(function () use ($owner, $publicId): WorkspaceFile {
            $file = WorkspaceFile::withTrashed()->onlyTrashed()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($file->parent_folder_id !== null) {
                $parent = WorkspaceFolder::withTrashed()
                    ->where('school_id', $this->schoolId())
                    ->where('owner_id', $owner->id)
                    ->whereKey($file->parent_folder_id)
                    ->firstOrFail();
                if ($parent->trashed()) {
                    throw ValidationException::withMessages(['folder' => 'Pulihkan folder induk terlebih dahulu.']);
                }
            }

            $file->restore();

            return $file->refresh();
        });
    }

    public function trashFolder(User $owner, WorkspaceFolder $folder): WorkspaceFolder
    {
        $this->assertFolderOwner($owner, $folder);

        return DB::transaction(function () use ($owner, $folder): WorkspaceFolder {
            $locked = WorkspaceFolder::query()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->whereKey($folder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasFiles = WorkspaceFile::withTrashed()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->where('parent_folder_id', $locked->id)
                ->exists();
            $hasFolders = WorkspaceFolder::withTrashed()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->where('parent_folder_id', $locked->id)
                ->exists();

            if ($hasFiles || $hasFolders) {
                throw ValidationException::withMessages(['folder' => 'Folder harus kosong sebelum dipindahkan ke Trash.']);
            }

            $locked->delete();

            return $locked;
        });
    }

    public function restoreFolder(User $owner, string $publicId): WorkspaceFolder
    {
        return DB::transaction(function () use ($owner, $publicId): WorkspaceFolder {
            $folder = WorkspaceFolder::withTrashed()->onlyTrashed()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($folder->parent_folder_id !== null) {
                $parent = WorkspaceFolder::withTrashed()
                    ->where('school_id', $this->schoolId())
                    ->where('owner_id', $owner->id)
                    ->whereKey($folder->parent_folder_id)
                    ->firstOrFail();
                if ($parent->trashed()) {
                    throw ValidationException::withMessages(['folder' => 'Pulihkan folder induk terlebih dahulu.']);
                }
            }

            $folder->restore();

            return $folder->refresh();
        });
    }

    public function store(UploadedFile $upload, User $owner, ?string $parentFolderPublicId = null): WorkspaceFile
    {
        $schoolId = $this->schoolId();
        $parentFolder = $this->folderFor($owner, $parentFolderPublicId);
        $mime = $upload->getMimeType() ?: 'application/octet-stream';
        $name = $this->safeDisplayName($upload->getClientOriginalName());
        $size = (int) ($upload->getSize() ?: 0);
        $publicId = (string) Str::uuid();
        $extension = preg_replace('/[^a-zA-Z0-9]+/', '', (string) ($upload->guessExtension() ?: '')) ?: 'bin';
        $directory = 'schools/'.$schoolId.'/users/'.$owner->id;
        $path = null;

        $checksum = hash_file('sha256', $upload->getRealPath()) ?: null;

        try {
            return DB::transaction(function () use ($upload, $owner, $schoolId, $parentFolder, $name, $mime, $size, $publicId, $extension, $directory, &$path, $checksum): WorkspaceFile {
                $this->lockQuotaOwner($owner);
                if ($parentFolder) {
                    $parentFolder = WorkspaceFolder::query()
                        ->where('school_id', $schoolId)
                        ->where('owner_id', $owner->id)
                        ->whereKey($parentFolder->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $this->assertQuotaCapacity($owner, $schoolId, $size);
                $path = $upload->storeAs($directory, $publicId.'.'.$extension, self::DISK);
                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'Berkas gagal disimpan.']);
                }

                $file = WorkspaceFile::create([
                    'public_id' => $publicId,
                    'school_id' => $schoolId,
                    'owner_id' => $owner->id,
                    'parent_folder_id' => $parentFolder?->id,
                    'name' => $name,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'source_application' => 'drive',
                    'current_version' => 1,
                    'is_favorite' => false,
                    'permission_metadata' => ['visibility' => 'private'],
                ]);

                $version = $file->versions()->create([
                    'version_number' => 1,
                    'disk' => self::DISK,
                    'path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'checksum_sha256' => $checksum,
                    'created_by' => $owner->id,
                ]);

                app(WorkspaceMalwareScanService::class)->scan($version);
                return $file->load('versions');
            });
        } catch (Throwable $error) {
            if (is_string($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $error;
        }
    }

    /**
     * Trusted server-side ingestion only. HTTP must never supply stream/path,
     * MIME, tenant or target UUID. Resumable service verifies assembled bytes.
     * The caller owns the stream and any outer transaction's orphan cleanup.
     * @param resource $stream
     */
    public function ingestTrustedStream($stream, User $owner, string $name, int $size, string $checksum, string $mime, string $publicId, ?string $parentPublicId = null): WorkspaceFile
    {
        abort_unless(is_resource($stream) && Str::isUuid($publicId) && $size > 0, 422);
        $schoolId = $this->schoolId();
        $name = $this->safeDisplayName($name);
        $path = 'schools/'.$schoolId.'/users/'.$owner->id.'/'.$publicId.'.bin';
        $writeAttempted = false;
        try {
            return DB::transaction(function () use ($stream, $owner, $schoolId, $name, $size, $checksum, $mime, $publicId, $parentPublicId, $path, &$writeAttempted): WorkspaceFile {
                $this->lockQuotaOwner($owner);
                $parent = $this->folderFor($owner, $parentPublicId);
                if ($parent) {
                    $parent = WorkspaceFolder::query()->where('school_id', $schoolId)->where('owner_id', $owner->id)
                        ->whereKey($parent->id)->lockForUpdate()->firstOrFail();
                }
                $this->assertQuotaCapacity($owner, $schoolId, $size);
                $writeAttempted = true;
                if (! Storage::disk(self::DISK)->put($path, $stream, ['visibility' => 'private'])) {
                    throw ValidationException::withMessages(['file' => 'Berkas gagal disimpan.']);
                }
                $file = WorkspaceFile::create([
                    'public_id' => $publicId, 'school_id' => $schoolId, 'owner_id' => $owner->id,
                    'parent_folder_id' => $parent?->id, 'name' => $name, 'mime_type' => $mime,
                    'size_bytes' => $size, 'source_application' => 'drive', 'current_version' => 1,
                    'is_favorite' => false, 'permission_metadata' => ['visibility' => 'private'],
                ]);
                $version = $file->versions()->create([
                    'version_number' => 1, 'disk' => self::DISK, 'path' => $path, 'mime_type' => $mime,
                    'size_bytes' => $size, 'checksum_sha256' => $checksum, 'created_by' => $owner->id,
                ]);
                app(WorkspaceMalwareScanService::class)->scan($version);
                return $file->load('versions');
            });
        } catch (Throwable $error) {
            if ($writeAttempted) {
                try {
                    Storage::disk(self::DISK)->delete($path);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $error;
        }
    }

    public function replaceVersion(UploadedFile $upload, User $owner, WorkspaceFile $file): WorkspaceFile
    {
        $this->assertOwner($owner, $file);
        $schoolId = $this->schoolId();
        $mime = $upload->getMimeType() ?: 'application/octet-stream';
        $size = (int) ($upload->getSize() ?: 0);
        $extension = preg_replace('/[^a-zA-Z0-9]+/', '', (string) ($upload->guessExtension() ?: '')) ?: 'bin';
        $directory = 'schools/'.$schoolId.'/users/'.$owner->id;
        $storedPath = null;

        try {
            return DB::transaction(function () use ($upload, $owner, $file, $schoolId, $mime, $size, $extension, $directory, &$storedPath): WorkspaceFile {
                $this->lockQuotaOwner($owner);
                $lockedFile = WorkspaceFile::query()
                    ->where('school_id', $schoolId)
                    ->where('owner_id', $owner->id)
                    ->whereKey($file->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $versionNumber = $lockedFile->current_version + 1;
                $this->assertQuotaCapacity($owner, $schoolId, $size);
                $storedPath = $upload->storeAs(
                    $directory,
                    $lockedFile->public_id.'-v'.$versionNumber.'.'.$extension,
                    self::DISK,
                );

                if ($storedPath === false) {
                    throw ValidationException::withMessages(['file' => 'Berkas gagal disimpan.']);
                }

                $version = $lockedFile->versions()->create([
                    'version_number' => $versionNumber,
                    'disk' => self::DISK,
                    'path' => $storedPath,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'checksum_sha256' => hash_file('sha256', $upload->getRealPath()) ?: null,
                    'created_by' => $owner->id,
                ]);
                app(WorkspaceMalwareScanService::class)->scan($version);
                $lockedFile->update([
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'current_version' => $versionNumber,
                ]);

                return $lockedFile->load('versions');
            });
        } catch (Throwable $error) {
            if (is_string($storedPath)) {
                Storage::disk(self::DISK)->delete($storedPath);
            }

            throw $error;
        }
    }

    public function replaceWopiVersion(User $owner, WorkspaceFile $file, int $schoolId, string $contents): WorkspaceFile
    {
        abort_unless(
            (int) $file->school_id === $schoolId && (int) $file->owner_id === (int) $owner->id,
            404,
        );

        $extension = preg_replace('/[^a-zA-Z0-9]+/', '', (string) pathinfo($file->name, PATHINFO_EXTENSION)) ?: 'bin';
        $directory = 'schools/'.$schoolId.'/users/'.$owner->id;
        $storedPath = null;

        try {
            return DB::transaction(function () use ($owner, $file, $schoolId, $contents, $extension, $directory, &$storedPath): WorkspaceFile {
                $this->lockQuotaOwner($owner);
                $lockedFile = WorkspaceFile::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('owner_id', $owner->id)
                    ->whereKey($file->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->firstOrFail();
                app(WorkspaceMalwareScanService::class)->assertClean($lockedFile->versions()->where('version_number', $lockedFile->current_version)->firstOrFail());
                $versionNumber = $lockedFile->current_version + 1;
                $size = strlen($contents);
                $this->assertQuotaCapacity($owner, $schoolId, $size);
                $storedPath = $directory.'/'.$lockedFile->public_id.'-v'.$versionNumber.'.'.$extension;

                if (! Storage::disk(self::DISK)->put($storedPath, $contents)) {
                    throw ValidationException::withMessages(['file' => 'Berkas gagal disimpan.']);
                }

                $version = $lockedFile->versions()->create([
                    'version_number' => $versionNumber,
                    'disk' => self::DISK,
                    'path' => $storedPath,
                    'mime_type' => $lockedFile->mime_type,
                    'size_bytes' => $size,
                    'checksum_sha256' => hash('sha256', $contents),
                    'created_by' => $owner->id,
                ]);
                app(WorkspaceMalwareScanService::class)->scan($version);
                $lockedFile->update([
                    'size_bytes' => $size,
                    'current_version' => $versionNumber,
                ]);

                return $lockedFile->load('versions');
            });
        } catch (Throwable $error) {
            if (is_string($storedPath)) {
                Storage::disk(self::DISK)->delete($storedPath);
            }

            throw $error;
        }
    }

    public function renameFile(User $owner, WorkspaceFile $file, string $name): WorkspaceFile
    {
        $this->assertOwner($owner, $file);
        $name = $this->renameName($name);
        $oldExtension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        $newExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($oldExtension !== $newExtension) {
            throw ValidationException::withMessages(['name' => 'Ekstensi berkas tidak boleh diubah.']);
        }
        $file->name = $name;
        $file->save();
        return $file->refresh();
    }

    public function renameFolder(User $owner, WorkspaceFolder $folder, string $name): WorkspaceFolder
    {
        $this->assertFolderOwner($owner, $folder);
        $folder->name = $this->renameName($name);
        $folder->save();
        return $folder->refresh();
    }

    public function moveFile(User $owner, WorkspaceFile $file, ?string $destination): WorkspaceFile
    {
        $this->assertOwner($owner, $file);

        return DB::transaction(function () use ($owner, $file, $destination): WorkspaceFile {
            $parent = $destination === null || $destination === '' || $destination === 'root'
                ? null
                : $this->folderFor($owner, $destination);
            if ($parent) {
                $parent = WorkspaceFolder::query()
                    ->where('school_id', $this->schoolId())
                    ->where('owner_id', $owner->id)
                    ->whereKey($parent->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $lockedFile = WorkspaceFile::query()
                ->where('school_id', $this->schoolId())
                ->where('owner_id', $owner->id)
                ->whereKey($file->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedFile->parent_folder_id = $parent?->id;
            $lockedFile->save();

            return $lockedFile->refresh();
        });
    }

    public function moveFolder(User $owner, WorkspaceFolder $folder, ?string $destination): WorkspaceFolder
    {
        $this->assertFolderOwner($owner, $folder);
        return DB::transaction(function () use ($owner, $folder, $destination): WorkspaceFolder {
            WorkspaceFolder::query()->where('school_id', $this->schoolId())->where('owner_id', $owner->id)->lockForUpdate()->get(['id']);
            $locked = WorkspaceFolder::query()->where('school_id', $this->schoolId())->where('owner_id', $owner->id)->whereKey($folder->id)->lockForUpdate()->firstOrFail();
            $parent = $destination === null || $destination === '' || $destination === 'root' ? null : $this->folderFor($owner, $destination);
            if ($parent) {
                $cursor = $parent;
                $seen = [];
                while ($cursor) {
                    if ($cursor->id === $locked->id || isset($seen[$cursor->id])) throw ValidationException::withMessages(['destination' => 'Folder tidak dapat dipindahkan ke dirinya sendiri atau turunannya.']);
                    $seen[$cursor->id] = true;
                    $cursor = $cursor->parent_folder_id ? WorkspaceFolder::query()->where('school_id', $this->schoolId())->where('owner_id', $owner->id)->whereKey($cursor->parent_folder_id)->firstOrFail() : null;
                }
            }
            $locked->parent_folder_id = $parent?->id;
            $locked->save();
            return $locked->refresh();
        });
    }

    public function folderDestinationsFor(User $owner, ?WorkspaceFolder $exclude = null)
    {
        $all = WorkspaceFolder::query()->where('school_id', $this->schoolId())->where('owner_id', $owner->id)->orderBy('name')->get();
        $excluded = [];
        if ($exclude) {
            $excluded[$exclude->id] = true;
            do {
                $count = count($excluded);
                foreach ($all as $candidate) if (isset($excluded[$candidate->parent_folder_id])) $excluded[$candidate->id] = true;
            } while ($count !== count($excluded));
        }
        return $all->reject(fn ($item) => isset($excluded[$item->id]))->map(fn ($item) => ['id' => $item->public_id, 'label' => implode(' / ', array_map(fn ($crumb) => $crumb->name, $this->breadcrumbsFor($owner, $item)))])->values();
    }

    private function assertFolderOwner(User $owner, WorkspaceFolder $folder): void
    {
        abort_unless((int) $folder->school_id === $this->schoolId() && (int) $folder->owner_id === (int) $owner->id, 404);
    }

    private function renameName(string $name): string
    {
        $name = preg_replace('/[\\\\\/]+/u', '', $name) ?: '';
        $name = trim(preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $name) ?: '');
        if ($name === '' || mb_strlen($name) > 180) {
            throw ValidationException::withMessages(['name' => 'Nama wajib diisi dan maksimal 180 karakter.']);
        }
        return $name;
    }

    /** Returns 404 for a foreign owner or tenant, including Super Admin. */
    public function assertOwner(User $user, WorkspaceFile $file): void
    {
        $schoolId = $this->schoolId();
        abort_unless(
            $schoolId !== null
                && (int) $file->school_id === $schoolId
                && (int) $file->owner_id === (int) $user->id,
            404,
        );
    }

    public function currentVersion(User $user, WorkspaceFile $file): WorkspaceFileVersion
    {
        $this->assertOwner($user, $file);

        return $file->versions()
            ->where('version_number', $file->current_version)
            ->firstOrFail();
    }

    public function restoreVersion(User $owner, WorkspaceFile $file, int $versionId): WorkspaceFile
    {
        $this->assertOwner($owner, $file);
        $source = $file->versions()->whereKey($versionId)->firstOrFail();
        abort_unless($source->disk === self::DISK && Storage::disk(self::DISK)->exists($source->path), 404);
        $directory = 'schools/'.$this->schoolId().'/users/'.$owner->id;
        $storedPath = null;

        try {
            return DB::transaction(function () use ($owner, $file, $source, $directory, &$storedPath): WorkspaceFile {
                $this->lockQuotaOwner($owner);
                $lockedFile = WorkspaceFile::query()->where('school_id', $this->schoolId())
                    ->where('owner_id', $owner->id)->whereKey($file->id)->lockForUpdate()->firstOrFail();
                $versionNumber = $lockedFile->current_version + 1;
                $extension = preg_replace('/[^a-zA-Z0-9]+/', '', (string) pathinfo($source->path, PATHINFO_EXTENSION)) ?: 'bin';
                $this->assertQuotaCapacity($owner, $this->schoolId(), (int) $source->size_bytes, 'version');
                $storedPath = $directory.'/'.$lockedFile->public_id.'-restore-'.Str::uuid().'-v'.$versionNumber.'.'.$extension;
                if (! Storage::disk(self::DISK)->copy($source->path, $storedPath)) {
                    throw ValidationException::withMessages(['version' => 'Versi gagal dipulihkan.']);
                }
                $version = $lockedFile->versions()->create([
                    'version_number' => $versionNumber, 'disk' => self::DISK, 'path' => $storedPath,
                    'mime_type' => $source->mime_type, 'size_bytes' => $source->size_bytes,
                    'checksum_sha256' => $source->checksum_sha256, 'created_by' => $owner->id,
                ]);
                app(WorkspaceMalwareScanService::class)->scan($version);
                $lockedFile->update(['current_version' => $versionNumber, 'mime_type' => $source->mime_type, 'size_bytes' => $source->size_bytes]);
                return $lockedFile->refresh();
            });
        } catch (Throwable $error) {
            if (is_string($storedPath)) Storage::disk(self::DISK)->delete($storedPath);
            throw $error;
        }
    }

    public function version(User $user, WorkspaceFile $file, int $versionId): WorkspaceFileVersion
    {
        $this->assertOwner($user, $file);

        return $file->versions()->whereKey($versionId)->firstOrFail();
    }

    private function schoolId(): int
    {
        $schoolId = app(SchoolContext::class)->id();
        abort_unless($schoolId !== null, 404, 'School context is required.');

        return $schoolId;
    }

    private function safeDisplayName(string $original): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($name)) ?: 'file';

        return Str::limit($name, 255, '');
    }
}
