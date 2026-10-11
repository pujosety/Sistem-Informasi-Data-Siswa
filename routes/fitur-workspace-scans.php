<?php

use App\Http\Controllers\WorkspaceScanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:6,1'])->post('/workspace/drive/files/{workspaceFile}/versions/{version}/scan', [WorkspaceScanController::class, 'retry'])
    ->whereNumber('version')->name('workspace.drive.scans.retry');
