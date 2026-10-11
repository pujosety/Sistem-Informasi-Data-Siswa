<?php

use App\Http\Controllers\WorkspaceWopiController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('workspace/drive/files')->name('workspace.drive.files.')->group(function (): void {
    Route::get('/{workspaceFile}/collabora', [WorkspaceWopiController::class, 'edit'])
        ->name('collabora');
    Route::post('/{workspaceFile}/wopi-session', [WorkspaceWopiController::class, 'issueSession'])
        ->name('wopi-session');
    Route::delete('/{workspaceFile}/wopi-session/{sessionId}', [WorkspaceWopiController::class, 'revokeSession'])
        ->whereNumber('sessionId')
        ->name('wopi-session.revoke');
});

// WOPI uses its short-lived, file-scoped bearer token instead of the browser's
// Laravel session. Only the CSRF middleware is removed; the token is verified
// independently on every endpoint.
Route::prefix('wopi/files')->name('wopi.files.')->middleware('throttle:600,1')->group(function (): void {
    Route::get('/{fileId}', [WorkspaceWopiController::class, 'checkFileInfo'])
        ->name('info');
    Route::get('/{fileId}/contents', [WorkspaceWopiController::class, 'getFile'])
        ->name('contents.get');
    Route::post('/{fileId}/contents', [WorkspaceWopiController::class, 'putFile'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->name('contents.put');
    Route::post('/{fileId}', [WorkspaceWopiController::class, 'operation'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->name('operations');
});
