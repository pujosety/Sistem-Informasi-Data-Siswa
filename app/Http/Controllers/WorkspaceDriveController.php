<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFolder;
use App\Services\AuditService;
use App\Services\WorkspaceDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceDriveController extends Controller
{
    public function __construct(
        private readonly WorkspaceDriveService $drive,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'string', 'max:36'],
            'favorites' => ['sometimes', 'boolean'],
        ]);

        $owner = $request->user();
        $search = trim($data['q'] ?? '');
        $favoritesOnly = (bool) ($data['favorites'] ?? false);

        if ($favoritesOnly) {
            $folder = null;
            $folders = collect();
            $files = $this->drive->favoriteFilesFor($owner, $search);
            $breadcrumbs = [];
        } elseif ($search !== '') {
            $folder = null;
            $folders = $this->drive->searchFoldersFor($owner, $search);
            $files = $this->drive->searchFilesFor($owner, $search);
            $breadcrumbs = [];
        } else {
            $folder = $this->drive->folderFor($owner, $data['folder'] ?? null);
            $folders = $this->drive->foldersFor($owner, $folder?->id);
            $files = $this->drive->filesFor($owner, $folder?->id);
            $breadcrumbs = $this->drive->breadcrumbsFor($owner, $folder);
        }

        return view('workspace.drive.index', [
            'files' => $files,
            'folders' => $folders,
            'currentFolder' => $folder,
            'breadcrumbs' => $breadcrumbs,
            'search' => $search,
            'favoritesOnly' => $favoritesOnly,
            'usedBytes' => $this->drive->usedBytesFor($owner),
            'maxFileKb' => (int) config('workspace.max_file_kb', 2048),
            'wopiExtensions' => config('workspace.wopi.supported_extensions', []),
            'moveDestinations' => $this->drive->folderDestinationsFor($owner),
        ]);
    }

    public function trash(Request $request): View
    {
        return view('workspace.drive.trash', [
            'files' => $this->drive->trashedFilesFor($request->user()),
            'folders' => $this->drive->trashedFoldersFor($request->user()),
        ]);
    }

    public function trashFile(Request $request, WorkspaceFile $workspaceFile): RedirectResponse
    {
        $file = $this->drive->trashFile($request->user(), $workspaceFile);
        $this->audit->log('workspace.file.trashed', $file, 'Memindahkan berkas Drive ke Trash.');

        return redirect()->route('workspace.drive.index')->with('success', 'Berkas dipindahkan ke Trash. Versi dan data tetap tersimpan.');
    }

    public function restoreFile(Request $request, string $publicId): RedirectResponse
    {
        $file = $this->drive->restoreFile($request->user(), $publicId);
        $this->audit->log('workspace.file.restored', $file, 'Memulihkan berkas Drive dari Trash.');

        return redirect()->route('workspace.drive.trash')->with('success', 'Berkas dipulihkan ke lokasi asal.');
    }

    public function trashFolder(Request $request, WorkspaceFolder $workspaceFolder): RedirectResponse
    {
        $folder = $this->drive->trashFolder($request->user(), $workspaceFolder);
        $this->audit->log('workspace.folder.trashed', $folder, 'Memindahkan folder kosong ke Trash.');

        return redirect()->route('workspace.drive.index')->with('success', 'Folder dipindahkan ke Trash.');
    }

    public function restoreFolder(Request $request, string $publicId): RedirectResponse
    {
        $folder = $this->drive->restoreFolder($request->user(), $publicId);
        $this->audit->log('workspace.folder.restored', $folder, 'Memulihkan folder Drive dari Trash.');

        return redirect()->route('workspace.drive.trash')->with('success', 'Folder dipulihkan ke lokasi asal.');
    }

    public function setFavorite(Request $request, WorkspaceFile $workspaceFile): RedirectResponse
    {
        $data = $request->validate([
            'favorite' => ['required', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'string', 'max:36'],
            'favorites' => ['nullable', 'boolean'],
        ]);
        $favorite = filter_var($data['favorite'], FILTER_VALIDATE_BOOLEAN);
        $file = $this->drive->setFavorite($request->user(), $workspaceFile, $favorite);
        $this->audit->log($favorite ? 'workspace.file.favorited' : 'workspace.file.unfavorited', $file,
            $favorite ? 'Menambahkan berkas ke favorit Drive.' : 'Menghapus berkas dari favorit Drive.');

        return redirect()->route('workspace.drive.index', array_filter([
            'q' => $data['q'] ?? null,
            'folder' => $data['folder'] ?? null,
            'favorites' => isset($data['favorites']) ? (int) (bool) $data['favorites'] : null,
        ], fn ($value) => $value !== null));
    }

    public function renameFile(Request $request, WorkspaceFile $workspaceFile): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'q' => ['nullable', 'string', 'max:100'], 'folder' => ['nullable', 'string', 'max:36'], 'favorites' => ['nullable', 'boolean']]);
        $oldName = $workspaceFile->name;
        $file = $this->drive->renameFile($request->user(), $workspaceFile, $data['name']);
        $this->audit->log('workspace.file.renamed', $file, 'Mengganti nama berkas Drive.', ['old_name' => $oldName, 'new_name' => $file->name]);
        return redirect()->route('workspace.drive.index', array_filter(['q' => $data['q'] ?? null, 'folder' => $data['folder'] ?? null, 'favorites' => isset($data['favorites']) ? (int) (bool) $data['favorites'] : null], fn ($v) => $v !== null));
    }

    public function moveFile(Request $request, WorkspaceFile $workspaceFile): RedirectResponse
    {
        $data = $request->validate(['destination' => ['nullable', 'string', 'max:36']]);
        $oldParent = $this->drive->folderForFile($request->user(), $workspaceFile);
        $file = $this->drive->moveFile($request->user(), $workspaceFile, $data['destination'] ?? null);
        $newParent = $this->drive->folderForFile($request->user(), $file);
        $this->audit->log('workspace.file.moved', $file, 'Memindahkan berkas Drive.', ['old_parent_folder_id' => $oldParent?->id, 'new_parent_folder_id' => $newParent?->id]);
        return redirect()->route('workspace.drive.index')->with('success', 'Berkas berhasil dipindahkan.');
    }

    public function moveFolder(Request $request, \App\Models\WorkspaceFolder $workspaceFolder): RedirectResponse
    {
        $data = $request->validate(['destination' => ['nullable', 'string', 'max:36']]);
        $oldParent = $workspaceFolder->parent_folder_id;
        $folder = $this->drive->moveFolder($request->user(), $workspaceFolder, $data['destination'] ?? null);
        $this->audit->log('workspace.folder.moved', $folder, 'Memindahkan folder Drive.', ['old_parent_folder_id' => $oldParent, 'new_parent_folder_id' => $folder->parent_folder_id]);
        return redirect()->route('workspace.drive.index')->with('success', 'Folder berhasil dipindahkan.');
    }

    public function renameFolder(Request $request, \App\Models\WorkspaceFolder $workspaceFolder): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'q' => ['nullable', 'string', 'max:100'], 'folder' => ['nullable', 'string', 'max:36'], 'favorites' => ['nullable', 'boolean']]);
        $oldName = $workspaceFolder->name;
        $folder = $this->drive->renameFolder($request->user(), $workspaceFolder, $data['name']);
        $this->audit->log('workspace.folder.renamed', $folder, 'Mengganti nama folder Drive.', ['old_name' => $oldName, 'new_name' => $folder->name]);
        return redirect()->route('workspace.drive.index', array_filter(['q' => $data['q'] ?? null, 'folder' => $data['folder'] ?? null, 'favorites' => isset($data['favorites']) ? (int) (bool) $data['favorites'] : null], fn ($v) => $v !== null));
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'folder' => ['nullable', 'string', 'max:36'],
        ]);

        $owner = $request->user();
        $parent = $this->drive->folderFor($owner, $data['folder'] ?? null);
        $folder = $this->drive->createFolder($owner, $data['name'], $parent?->public_id);

        $this->audit->log(
            'workspace.folder.created',
            $folder,
            'Membuat folder di Drive pribadi.',
            ['parent_folder_id' => $parent?->id],
        );

        return redirect()->route(
            'workspace.drive.index',
            $parent ? ['folder' => $parent->public_id] : [],
        )->with('success', 'Folder berhasil dibuat.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('workspace.max_file_kb', 2048)],
            'folder' => ['nullable', 'string', 'max:36'],
        ]);

        $owner = $request->user();
        $folder = $this->drive->folderFor($owner, $data['folder'] ?? null);
        $file = $this->drive->store($data['file'], $owner, $folder?->public_id);
        $this->audit->log(
            'workspace.file.uploaded',
            $file,
            'Mengunggah berkas ke Drive pribadi.',
            ['size_bytes' => $file->size_bytes, 'mime_type' => $file->mime_type],
        );

        return redirect()->route(
            'workspace.drive.index',
            $folder ? ['folder' => $folder->public_id] : [],
        )->with('success', 'Berkas berhasil disimpan di Drive.');
    }

    public function storeVersion(Request $request, WorkspaceFile $workspaceFile): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('workspace.max_file_kb', 2048)],
        ]);

        $owner = $request->user();
        $folder = $this->drive->folderForFile($owner, $workspaceFile);
        $updated = $this->drive->replaceVersion($data['file'], $owner, $workspaceFile);
        $this->audit->log(
            'workspace.file.version.created',
            $updated,
            'Menyimpan versi baru berkas Drive pribadi.',
            ['version' => $updated->current_version, 'size_bytes' => $updated->size_bytes],
        );

        return redirect()->route(
            'workspace.drive.index',
            $folder ? ['folder' => $folder->public_id] : [],
        )->with('success', 'Versi baru berhasil disimpan.');
    }

    public function download(Request $request, WorkspaceFile $workspaceFile): StreamedResponse
    {
        $version = $this->drive->currentVersion($request->user(), $workspaceFile);
        app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($version);
        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404, 'Berkas tidak ditemukan di penyimpanan.');

        $stream = $disk->readStream($version->path);
        abort_if($stream === false, 404, 'Berkas tidak ditemukan di penyimpanan.');

        $this->audit->log(
            'workspace.file.downloaded',
            $workspaceFile,
            'Mengunduh berkas dari Drive pribadi.',
            ['version' => $version->version_number],
        );

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $workspaceFile->name, [
            'Content-Type' => $version->mime_type,
            'Cache-Control' => 'private, max-age=0, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restoreVersion(Request $request, WorkspaceFile $workspaceFile, int $version): RedirectResponse
    {
        $source = $this->drive->version($request->user(), $workspaceFile, $version);
        $restored = $this->drive->restoreVersion($request->user(), $workspaceFile, $version);
        $this->audit->log('workspace.file.version.restored', $restored, 'Memulihkan versi berkas Drive sebagai versi baru.', [
            'source_version' => $source->version_number,
            'created_version' => $restored->current_version,
        ]);

        return redirect()->route('workspace.drive.index')->with('success', 'Versi berhasil dipulihkan sebagai versi baru.');
    }

    public function downloadVersion(Request $request, WorkspaceFile $workspaceFile, int $version): StreamedResponse
    {
        $version = $this->drive->version($request->user(), $workspaceFile, $version);
        app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($version);
        $disk = Storage::disk('workspace');
        abort_unless($disk->exists($version->path), 404, 'Berkas tidak ditemukan di penyimpanan.');
        $stream = $disk->readStream($version->path);
        abort_if($stream === false, 404, 'Berkas tidak ditemukan di penyimpanan.');

        $this->audit->log('workspace.file.downloaded', $workspaceFile,
            'Mengunduh versi berkas dari Drive pribadi.', ['version' => $version->version_number]);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, $workspaceFile->name, [
            'Content-Type' => $version->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
