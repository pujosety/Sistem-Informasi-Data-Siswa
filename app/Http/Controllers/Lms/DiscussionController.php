<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LmsDiscussion;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscussionController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request, Course $course): View
    {
        $this->authorizeParticipant($request, $course);
        $discussions = $course->discussions()
            ->whereNull('parent_id')
            ->with(['author', 'replies.author'])
            ->oldest()
            ->get();

        return view('lms.discussions.index', compact('course', 'discussions'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeParticipant($request, $course);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', 'exists:lms_discussions,id'],
        ]);

        if (! empty($data['parent_id'])) {
            abort_unless(
                LmsDiscussion::query()->where('course_id', $course->id)->whereKey($data['parent_id'])->exists(),
                404
            );
        }

        $discussion = $course->discussions()->create([
            'school_id' => $course->school_id,
            'author_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        $recipientIds = $course->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student.user_id')
            ->filter()
            ->push($course->teacher_id)
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $request->user()->id)
            ->values()
            ->all();
        $this->notifications->notifyUsers(
            $recipientIds,
            NotificationService::COURSE_DISCUSSION,
            'Diskusi baru di '.$course->title,
            $data['body'],
            ['course_id' => $course->id, 'discussion_id' => $discussion->id]
        );

        return back()->with('success', 'Pesan diskusi berhasil dikirim.');
    }

    private function authorizeParticipant(Request $request, Course $course): void
    {
        $user = $request->user();
        if ($user?->can('lms.lesson.manage') && ((int) $course->teacher_id === (int) $user->id || $user->isSuperAdmin())) {
            return;
        }

        $studentId = $user?->student?->id;
        $enrolled = $studentId && $course->enrollments()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
        abort_unless($enrolled, 404);
    }
}
