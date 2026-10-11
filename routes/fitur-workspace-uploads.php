<?php

use App\Http\Controllers\WorkspaceResumableUploadController;
use Illuminate\Support\Facades\Route;

// Require from routes/web.php: web session/CSRF remain enabled; no public URLs.
Route::middleware(['auth', 'throttle:180,1'])->prefix('workspace/drive/uploads')
    ->name('workspace.drive.uploads.')->group(function (): void {
        Route::post('/', [WorkspaceResumableUploadController::class, 'create'])->name('create');
        Route::get('/{upload}', [WorkspaceResumableUploadController::class, 'show'])->whereUuid('upload')->name('show');
        Route::post('/{upload}/chunks', [WorkspaceResumableUploadController::class, 'chunk'])->whereUuid('upload')->name('chunk');
        Route::post('/{upload}/finalize', [WorkspaceResumableUploadController::class, 'finalize'])->whereUuid('upload')->name('finalize');
        Route::delete('/{upload}', [WorkspaceResumableUploadController::class, 'destroy'])->whereUuid('upload')->name('destroy');
    });
