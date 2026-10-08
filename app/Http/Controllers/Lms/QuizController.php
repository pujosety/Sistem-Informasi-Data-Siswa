<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LmsQuiz;
use App\Models\LmsQuizAttempt;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeTeacher($course);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'show_results' => ['nullable', 'boolean'],
        ]);

        $course->quizzes()->create($data + [
            'school_id' => $course->school_id,
            'status' => LmsQuiz::DRAFT,
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
            'show_results' => (bool) ($data['show_results'] ?? true),
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Kuis berhasil disimpan sebagai draft.');
    }

    public function storeQuestion(Request $request, Course $course, LmsQuiz $quiz): RedirectResponse
    {
        $this->authorizeQuiz($course, $quiz);
        $data = $request->validate([
            'type' => ['required', 'in:multiple_choice,true_false,short_answer'],
            'prompt' => ['required', 'string', 'max:20000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:500'],
            'correct_answer' => ['nullable', 'string', 'max:500'],
            'points' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $quiz->questions()->create([
            'school_id' => $course->school_id,
            'type' => $data['type'],
            'prompt' => $data['prompt'],
            'options' => $data['options'] ?? null,
            'correct_answer' => $data['correct_answer'] ?? null,
            'points' => $data['points'],
            'position' => $data['position'] ?? (((int) $quiz->questions()->max('position')) + 1),
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Pertanyaan kuis berhasil ditambahkan.');
    }

    public function publish(Course $course, LmsQuiz $quiz): RedirectResponse
    {
        $this->authorizeQuiz($course, $quiz);
        abort_if($quiz->questions()->count() === 0, 422, 'Tambahkan minimal satu pertanyaan sebelum menerbitkan kuis.');
        $quiz->update(['status' => LmsQuiz::PUBLISHED]);

        $studentUserIds = $course->enrollments()
            ->where('status', CourseEnrollment::ACTIVE)
            ->with('student')
            ->get()
            ->pluck('student.user_id')
            ->filter()
            ->all();
        $this->notifications->notifyUsers(
            $studentUserIds,
            NotificationService::QUIZ_PUBLISHED,
            'Kuis baru: '.$quiz->title,
            $quiz->due_at
                ? 'Kuis baru tersedia. Batas pengerjaan '.$quiz->due_at->format('d M Y H:i').'.'
                : 'Kuis baru tersedia di course '.$course->title.'.',
            ['course_id' => $course->id, 'quiz_id' => $quiz->id]
        );

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Kuis berhasil diterbitkan.');
    }

    public function showForStudent(Request $request, Course $course, LmsQuiz $quiz): View
    {
        $student = $this->student($request);
        $this->authorizeEnrollment($student, $course);
        abort_unless($quiz->course_id === $course->id && $quiz->status === LmsQuiz::PUBLISHED, 404);

        $quiz->load('questions');
        $latestAttempt = $quiz->attempts()
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        return view('lms.student.quizzes.show', compact('course', 'quiz', 'latestAttempt'));
    }

    public function submit(Request $request, Course $course, LmsQuiz $quiz): RedirectResponse
    {
        $student = $this->student($request);
        $this->authorizeEnrollment($student, $course);
        abort_unless($quiz->course_id === $course->id && $quiz->status === LmsQuiz::PUBLISHED, 404);

        $data = $request->validate(['answers' => ['nullable', 'array']]);
        $attempts = $quiz->attempts()->where('student_id', $student->id)->where('status', 'submitted')->count();
        abort_if($attempts >= $quiz->attempt_limit, 422, 'Batas percobaan kuis sudah tercapai.');

        $answers = $data['answers'] ?? [];
        $questions = $quiz->questions()->get();
        $possible = (float) $questions->sum(fn ($question) => (float) $question->points);
        $earned = $questions->sum(function ($question) use ($answers): float {
            $given = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
            return $given !== null && (string) $given === (string) $question->correct_answer
                ? (float) $question->points
                : 0.0;
        });
        $score = $possible > 0 ? round(($earned / $possible) * 100, 2) : 0;

        $quiz->attempts()->create([
            'school_id' => $course->school_id,
            'student_id' => $student->id,
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => $score,
            'answers' => $answers,
            'status' => 'submitted',
        ]);
        $this->notifications->notifyUser(
            $course->teacher_id,
            NotificationService::QUIZ_SUBMITTED,
            'Kuis dikumpulkan: '.$quiz->title,
            ($student->full_name ?: 'Siswa').' mengumpulkan kuis dengan nilai '.$score.'.',
            ['course_id' => $course->id, 'quiz_id' => $quiz->id, 'student_id' => $student->id]
        );

        return redirect()->route('lms.student.quizzes.show', [$course, $quiz])
            ->with('success', $quiz->show_results ? 'Kuis dikumpulkan. Nilai: '.$score : 'Kuis berhasil dikumpulkan.');
    }

    private function authorizeTeacher(Course $course): void
    {
        abort_unless(request()->user()?->can('lms.assignment.manage'), 403);
        $this->authorize('update', $course);
    }

    private function authorizeQuiz(Course $course, LmsQuiz $quiz): void
    {
        abort_unless($quiz->course_id === $course->id, 404);
        $this->authorizeTeacher($course);
    }

    private function student(Request $request): Student
    {
        return $request->user()->student()->firstOrFail();
    }

    private function authorizeEnrollment(Student $student, Course $course): void
    {
        abort_unless(CourseEnrollment::query()
            ->where('school_id', $course->school_id)
            ->where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->where('status', CourseEnrollment::ACTIVE)
            ->exists(), 403);
    }
}
