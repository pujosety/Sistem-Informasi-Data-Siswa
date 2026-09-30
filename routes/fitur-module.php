<?php

use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module toggle + employee self-service
|--------------------------------------------------------------------------
|
| OWNED BY ONE FEATURE. Another agent working in parallel must not edit this
| file — put new routes for the same feature in the same file, not a second
| one, or the two will register duplicate paths.
|
| This file is required from routes/web.php OUTSIDE any middleware group, so
| it carries its own. Every group below is auth + the permission that defines
| the surface; never widen it to a neighbouring permission to save a line,
| because a broad guard is how a teacher ends up with a school's user list.
|
*/

/*
 | Module toggle and employee self-service.
 |
 | The toggle uses `module.*`, NOT `system.*`: switching a section of the
 | platform on is ordinary administration, while system.* is about host
 | configuration. An operator should not need a shell to do this.
 */
Route::middleware(['auth', 'can:module.view'])
    ->prefix('admin/modul')
    ->name('admin.modules.')
    ->group(function () {
        Route::get('/', [ModuleController::class, 'index'])->name('index');

        Route::middleware('can:module.toggle')->group(function () {
            Route::put('/{module}', [ModuleController::class, 'update'])->name('update');
        });
    });

/*
 | A staff member reading their OWN employment record. Read-only, own record
 | only, 404 for anyone else's — 403 would confirm the record exists.
 */
Route::middleware('auth')
    ->get('/profil/kepegawaian', [\App\Http\Controllers\ProfileController::class, 'employment'])
    ->name('profile.employment');

