<?php

use App\Http\Controllers\GuardianController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guardian linking
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
 | Guardian linking.
 |
 | Without this the parent portal is unreachable: a parent account can read a
 | child only through guardian_relationships, and nothing could create that
 | row.
 */
Route::middleware(['auth', 'can:guardian.view'])
    ->prefix('kesiswaan/data-siswa/{student}/wali')
    ->name('kesiswaan.guardians.')
    ->group(function () {
        Route::get('/', [GuardianController::class, 'index'])->name('index');

        Route::middleware('can:guardian.link')->group(function () {
            Route::post('/', [GuardianController::class, 'store'])->name('store');
        });

        Route::middleware('can:guardian.unlink')->group(function () {
            Route::delete('/{guardianRelationship}', [GuardianController::class, 'destroy'])->name('destroy');
        });
    });

