<?php

use App\Http\Controllers\WorkspaceDriveController;
use Illuminate\Support\Facades\Route;

// Workspace is available to every authenticated role; file operations enforce
// owner and active-school scope in WorkspaceDriveService, not in the UI.
Route::middleware('auth')->prefix('workspace')->name('workspace.')->group(function (): void {
    Route::redirect('/', '/workspace/drive')->name('home');

    Route::get('/drive', [WorkspaceDriveController::class, 'index'])
        ->name('drive.index');
    Route::get('/drive/trash', [WorkspaceDriveController::class, 'trash'])
        ->name('drive.trash');
    Route::post('/drive/files/{workspaceFile}/trash', [WorkspaceDriveController::class, 'trashFile'])
        ->name('drive.files.trash');
    Route::post('/drive/files/{publicId}/restore', [WorkspaceDriveController::class, 'restoreFile'])
        ->name('drive.files.restore');
    Route::post('/drive/folders/{workspaceFolder}/trash', [WorkspaceDriveController::class, 'trashFolder'])
        ->name('drive.folders.trash');
    Route::post('/drive/folders/{publicId}/restore', [WorkspaceDriveController::class, 'restoreFolder'])
        ->name('drive.folders.restore');
    Route::post('/drive/folders', [WorkspaceDriveController::class, 'storeFolder'])
        ->name('drive.folders.store');
    Route::post('/drive/files', [WorkspaceDriveController::class, 'store'])
        ->name('drive.files.store');
    Route::post('/drive/folders/{workspaceFolder}/rename', [WorkspaceDriveController::class, 'renameFolder'])->name('drive.folders.rename');
    Route::post('/drive/files/{workspaceFile}/rename', [WorkspaceDriveController::class, 'renameFile'])->name('drive.files.rename');
    Route::post('/drive/files/{workspaceFile}/move', [WorkspaceDriveController::class, 'moveFile'])->name('drive.files.move');
    Route::post('/drive/folders/{workspaceFolder}/move', [WorkspaceDriveController::class, 'moveFolder'])->name('drive.folders.move');
    Route::post('/drive/files/{workspaceFile}/favorite', [WorkspaceDriveController::class, 'setFavorite'])
        ->name('drive.files.favorite');
    Route::post('/drive/files/{workspaceFile}/versions', [WorkspaceDriveController::class, 'storeVersion'])
        ->name('drive.files.versions.store');
    Route::post('/drive/files/{workspaceFile}/versions/{version}/restore', [WorkspaceDriveController::class, 'restoreVersion'])
        ->whereNumber('version')->name('drive.files.versions.restore');
    Route::get('/drive/files/{workspaceFile}/versions/{version}/download', [WorkspaceDriveController::class, 'downloadVersion'])
        ->whereNumber('version')->name('drive.files.versions.download');
    Route::get('/drive/files/{workspaceFile}/download', [WorkspaceDriveController::class, 'download'])
        ->name('drive.files.download');
});
