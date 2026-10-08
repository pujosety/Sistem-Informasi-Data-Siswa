<?php

use App\Http\Controllers\Lms\CourseController;
use App\Http\Controllers\Lms\LessonController;
use App\Http\Controllers\Lms\StudentLmsController;
use App\Http\Controllers\Lms\AssignmentController;
use App\Http\Controllers\Lms\QuizController;
use App\Http\Controllers\Lms\ModuleController;
use App\Http\Controllers\Lms\EnrollmentController;
use App\Http\Controllers\Lms\GradebookController;
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
        Route::get('/courses/{course}/modules/create', [ModuleController::class, 'create'])
            ->middleware('can:lms.lesson.manage')
            ->name('modules.create');
        Route::post('/courses/{course}/modules', [ModuleController::class, 'store'])
            ->middleware('can:lms.lesson.manage')
            ->name('modules.store');
        Route::get('/courses/{course}/enrollments', [EnrollmentController::class, 'index'])
            ->middleware('can:lms.enrollment.manage')
            ->name('enrollments.index');
        Route::post('/courses/{course}/enrollments', [EnrollmentController::class, 'store'])
            ->middleware('can:lms.enrollment.manage')
            ->name('enrollments.store');
        Route::delete('/courses/{course}/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])
            ->middleware('can:lms.enrollment.manage')
            ->name('enrollments.destroy');
        Route::get('/courses/{course}/gradebook', [GradebookController::class, 'index'])
            ->middleware('can:lms.gradebook.view')
            ->name('gradebook.index');

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
        Route::get('/courses/{course}/assignments/create', [AssignmentController::class, 'create'])
            ->middleware('can:lms.assignment.manage')->name('assignments.create');
        Route::post('/courses/{course}/assignments', [AssignmentController::class, 'store'])
            ->middleware('can:lms.assignment.manage')->name('assignments.store');
        Route::post('/courses/{course}/assignments/{assignment}/publish', [AssignmentController::class, 'publish'])
            ->middleware('can:lms.assignment.manage')->name('assignments.publish');
        Route::get('/courses/{course}/assignments/{assignment}/submissions', [AssignmentController::class, 'submissions'])
            ->middleware('can:lms.assignment.grade')->name('assignments.submissions');
        Route::put('/courses/{course}/assignments/{assignment}/submissions/{submission}', [AssignmentController::class, 'grade'])
            ->middleware('can:lms.assignment.grade')->name('submissions.grade');
        Route::post('/courses/{course}/quizzes', [QuizController::class, 'store'])
            ->middleware('can:lms.assignment.manage')->name('quizzes.store');
        Route::post('/courses/{course}/quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion'])
            ->middleware('can:lms.assignment.manage')->name('quizzes.questions.store');
        Route::post('/courses/{course}/quizzes/{quiz}/publish', [QuizController::class, 'publish'])
            ->middleware('can:lms.assignment.manage')->name('quizzes.publish');
    });

Route::middleware(['auth', 'can:lms.student.view'])
    ->prefix('lms')
    ->name('lms.student.')
    ->scopeBindings()
    ->group(function () {
        Route::get('/dashboard', [StudentLmsController::class, 'dashboard'])->name('dashboard');
        Route::get('/courses/{course}', [StudentLmsController::class, 'show'])->name('courses.show');
        Route::post('/courses/{course}/lessons/{lesson}/complete', [StudentLmsController::class, 'completeLesson'])
            ->middleware('can:lms.lesson.complete')
            ->name('lessons.complete');
        Route::get('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'showForStudent'])
            ->name('assignments.show');
        Route::post('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'submit'])
            ->middleware('can:lms.assignment.submit')->name('assignments.submit');
        Route::get('/courses/{course}/quizzes/{quiz}', [QuizController::class, 'showForStudent'])
            ->name('quizzes.show');
        Route::post('/courses/{course}/quizzes/{quiz}', [QuizController::class, 'submit'])
            ->name('quizzes.submit');
    });
