<?php

use App\Http\Controllers\GradeEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Grade entry
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
 | Grade entry.
 |
 | `grade.edit` and `grade.publish` are SEPARATE and both are inside the same
 | scope check, so a Wali Kelas can mark their own class and cannot publish
 | someone else's — and holding one does not imply the other. ClassroomPolicy
 | is the model: permission AND ClassScope, composed in the policy.
 */
Route::middleware(['auth', 'can:grade.view'])
    ->prefix('akademik/kelas/{classroom}/nilai')
    ->name('academic.grades.')
    ->group(function () {
        Route::get('/', [GradeEntryController::class, 'index'])->name('index');
        Route::post('/', [GradeEntryController::class, 'store'])->name('store');
        Route::post('/terbitkan', [GradeEntryController::class, 'publish'])->name('publish');
    });

