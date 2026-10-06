<?php

use App\Http\Controllers\Lms\CourseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:lms.course.view'])
    ->prefix('lms/teaching')
    ->name('lms.teacher.')
    ->group(function () {
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])
            ->middleware('can:lms.course.create')
            ->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])
            ->middleware('can:lms.course.create')
            ->name('courses.store');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    });
