<?php

use App\Http\Controllers\NavigationItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'module:cms', 'can:dashboard.admin.view', 'can:cms.navigation.manage'])
    ->group(function (): void {
        Route::get('/navigasi-publik', [NavigationItemController::class, 'index'])->name('cms.navigation.index');
        Route::post('/navigasi-publik', [NavigationItemController::class, 'store'])->name('cms.navigation.store');
        Route::put('/navigasi-publik/{navigationItem}', [NavigationItemController::class, 'update'])->name('cms.navigation.update');
        Route::delete('/navigasi-publik/{navigationItem}', [NavigationItemController::class, 'destroy'])->name('cms.navigation.destroy');
    });
