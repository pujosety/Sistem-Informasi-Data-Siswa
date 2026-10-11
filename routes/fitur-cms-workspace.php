<?php

use App\Http\Controllers\CmsWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/cms', [CmsWorkspaceController::class, 'index'])
    ->middleware(['auth', 'module:cms', 'can:cms.view'])
    ->name('cms.workspace');
