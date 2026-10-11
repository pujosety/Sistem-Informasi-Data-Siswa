<?php

use App\Http\Controllers\WorkspaceFileShareController;
use Illuminate\Support\Facades\Route;

// Require from routes/web.php so session authentication and CSRF stay enabled.
// Never register this file under API middleware or create a signed/public variant.
Route::middleware(['auth', 'throttle:60,1'])->prefix('workspace/shares')->name('workspace.shares.')->group(function (): void {
    Route::get('/received', [WorkspaceFileShareController::class, 'received'])->name('received');
    Route::get('/files/{fileId}', [WorkspaceFileShareController::class, 'manage'])->whereUuid('fileId')->name('manage');
    Route::post('/files/{fileId}', [WorkspaceFileShareController::class, 'grant'])
        ->middleware('throttle:10,1')->whereUuid('fileId')->name('grant');
    Route::delete('/files/{fileId}/{shareId}', [WorkspaceFileShareController::class, 'revoke'])
        ->whereUuid(['fileId', 'shareId'])->name('revoke');
    Route::get('/{shareId}/download', [WorkspaceFileShareController::class, 'download'])
        ->whereUuid('shareId')->name('download');
});
