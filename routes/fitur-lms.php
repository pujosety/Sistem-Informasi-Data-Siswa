<?php

use App\Http\Controllers\Lms\CourseController;
use App\Http\Controllers\Lms\LessonController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:lms.course.view'])
    ->prefix('lms/teaching')
    ->name('lms.teacher.')
    ->scopeBindings()
    ->group(function () {
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])
            ->middleware('can:lms.course.create')
            ->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])
            ->middleware('can:lms.course.create')
            ->name('courses.store');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

        Route::get('/courses/{course}/lessons/create', [LessonController::class, 'create'])
            ->middleware('can:lms.lesson.manage')
            ->name('lessons.create');
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store'])
            ->middleware('can:lms.lesson.manage')
            ->name('lessons.store');
        Route::get('/courses/{course}/lessons/{lesson}/edit', [LessonController::class, 'edit'])
            ->middleware('can:lms.lesson.manage')
            ->name('lessons.edit');
        Route::put('/courses/{course}/lessons/{lesson}', [LessonController::class, 'update'])
            ->middleware('can:lms.lesson.manage')
            ->name('lessons.update');
        Route::post('/courses/{course}/lessons/{lesson}/publish', [LessonController::class, 'publish'])
            ->middleware('can:lms.lesson.manage')
            ->name('lessons.publish');
    });
