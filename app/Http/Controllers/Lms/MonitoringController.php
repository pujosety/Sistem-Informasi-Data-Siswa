<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LmsAssignment;
use App\Models\LmsAssignmentSubmission;
use App\Models\LmsQuiz;
use App\Models\LmsQuizAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('lms.monitor.view'), 403);

        $courses = Course::query()
            ->with(['subject', 'classroom', 'teacher'])
            ->withCount([
                'enrollments as active_enrollments_count' => fn ($query) => $query->where('status', CourseEnrollment::ACTIVE),
                'lessons as published_lessons_count' => fn ($query) => $query->where('status', Lesson::PUBLISHED),
            ])
            ->latest('id')
            ->paginate(20);

        return view('lms.monitoring.index', [
            'courseCount' => Course::query()->where('status', Course::PUBLISHED)->count(),
            'activeEnrollmentCount' => CourseEnrollment::query()->where('status', CourseEnrollment::ACTIVE)->count(),
            'publishedLessonCount' => Lesson::query()->where('status', Lesson::PUBLISHED)->count(),
            'pendingAssignmentCount' => LmsAssignmentSubmission::query()
                ->where('status', LmsAssignmentSubmission::SUBMITTED)
                ->whereNull('score')
                ->count(),
            'pendingQuizCount' => LmsQuizAttempt::query()
                ->whereNotNull('submitted_at')
                ->whereNull('score')
                ->count(),
            'courses' => $courses,
        ]);
    }
}
