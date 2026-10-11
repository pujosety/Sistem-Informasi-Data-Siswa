<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LmsAssignment;
use App\Models\LmsQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GradebookController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        abort_unless($request->user()?->can('lms.gradebook.view'), 403);
        $this->authorize('view', $course);

        $enrollments = $course->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->orderBy('id')
            ->get();
        $studentIds = $enrollments->pluck('student_id');

        $assignments = $course->assignments()
            ->where('status', LmsAssignment::PUBLISHED)
            ->with(['submissions' => fn ($query) => $query
                ->whereIn('student_id', $studentIds)
                ->whereIn('status', ['submitted', 'graded'])
                ->latest('id')])
            ->get();

        $quizzes = $course->quizzes()
            ->where('status', LmsQuiz::PUBLISHED)
            ->with(['attempts' => fn ($query) => $query
                ->whereIn('student_id', $studentIds)
                ->where('status', 'submitted')
                ->latest('id')])
            ->get();

        $rows = $enrollments->map(function ($enrollment) use ($assignments, $quizzes): array {
            $assignmentScores = $assignments->mapWithKeys(function ($assignment) use ($enrollment): array {
                $submission = $assignment->submissions
                    ->where('student_id', $enrollment->student_id)
                    ->sortByDesc('id')
                    ->first();

                return [$assignment->id => $submission?->score];
            });
            $quizScores = $quizzes->mapWithKeys(function ($quiz) use ($enrollment): array {
                $attempt = $quiz->attempts
                    ->where('student_id', $enrollment->student_id)
                    ->sortByDesc('id')
                    ->first();

                return [$quiz->id => $attempt?->score];
            });
            $scores = $assignmentScores->merge($quizScores)
                ->filter(fn ($score): bool => $score !== null)
                ->map(fn ($score): float => (float) $score);

            return [
                'student' => $enrollment->student,
                'assignment_scores' => $assignmentScores,
                'quiz_scores' => $quizScores,
                'average' => $scores->isEmpty() ? null : round($scores->avg(), 2),
            ];
        });

        return view('lms.teacher.gradebook.index', compact('course', 'assignments', 'quizzes', 'rows'));
    }
}
