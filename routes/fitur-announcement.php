<?php

use App\Http\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Class announcement editing
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

Route::middleware(['auth', 'can:academic_year.view'])->prefix('akademik/kelas/{classroom}/pengumuman')->name('academic.announcements.')->group(function () {
    Route::get('/{announcement}/ubah', [AnnouncementController::class, 'edit'])->name('edit');
    Route::put('/{announcement}', [AnnouncementController::class, 'update'])->name('update');
});
