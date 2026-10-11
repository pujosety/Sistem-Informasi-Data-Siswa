<?php

use App\Http\Controllers\WorkspaceDesignController;
use Illuminate\Support\Facades\Route;

// Include from routes/web.php (web/session/CSRF), never api.php.
Route::middleware(['auth', 'throttle:120,1'])->prefix('workspace/design')->name('workspace.design.')->group(function (): void {
    Route::get('/', [WorkspaceDesignController::class, 'index'])->name('index');
    Route::post('/', [WorkspaceDesignController::class, 'store'])->name('store');
    Route::get('/{project}/editor', [WorkspaceDesignController::class, 'editor'])->whereUuid('project')->name('editor');
    Route::get('/{project}/document', [WorkspaceDesignController::class, 'show'])->whereUuid('project')->name('show');
    Route::put('/{project}/document', [WorkspaceDesignController::class, 'update'])->whereUuid('project')->name('update');
    Route::post('/{project}/assets', [WorkspaceDesignController::class, 'attachAsset'])->whereUuid('project')->name('assets.attach');
    Route::get('/{project}/assets/{asset}', [WorkspaceDesignController::class, 'asset'])->whereUuid('project')->whereUuid('asset')->name('assets.show');
});
