<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentLmsController extends Controller
{
    public function dashboard(Request $request): View
    {
        $student = $this->student($request);
        $courses = CourseEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', CourseEnrollment::ACTIVE)
            ->with([
                'course.subject',
                'course.classroom',
                'course.semester',
                'course.lessons' => fn ($q) => $q->published(),
                'course' => fn ($q) => $q->withCount([
                    'lessons as published_lessons_count' => fn ($lessons) => $lessons->published(),
                    'lessonProgress as completed_lessons_count' => fn ($progress) => $progress
                        ->where('student_id', $student->id)
                        ->whereNotNull('completed_at'),
                ]),
            ])
            ->latest('enrolled_at')
            ->paginate(12);

        return view('lms.student.dashboard', compact('student', 'courses'));
    }

    public function show(Request $request, Course $course): View
    {
        $student = $this->student($request);
        $this->authorizeEnrollment($student, $course);

        $course->load([
            'subject', 'classroom', 'semester',
            'lessons' => fn ($query) => $query->published()->with('module')->orderBy('position'),
            'announcements' => fn ($query) => $query->published(),
        ]);
        $completedLessonIds = LessonProgress::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id')
            ->all();

        return view('lms.student.courses.show', compact('student', 'course', 'completedLessonIds'));
    }

    public function completeLesson(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $student = $this->student($request);
        $this->authorizeEnrollment($student, $course);
        abort_unless($lesson->course_id === $course->id && $lesson->status === Lesson::PUBLISHED, 404);

        LessonProgress::updateOrCreate(
            [
                'school_id' => $course->school_id,
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
            ],
            ['completed_at' => now()]
        );

        return redirect()->route('lms.student.courses.show', $course)
            ->with('success', 'Materi ditandai selesai.');
    }

    private function student(Request $request): Student
    {
        return $request->user()->student()->firstOrFail();
    }

    private function authorizeEnrollment(Student $student, Course $course): void
    {
        abort_unless(
            CourseEnrollment::query()
                ->where('school_id', $course->school_id)
                ->where('course_id', $course->id)
                ->where('student_id', $student->id)
                ->where('status', CourseEnrollment::ACTIVE)
                ->exists(),
            403
        );
    }
}
