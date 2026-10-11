<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LmsAnnouncement;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(Course $course): View
    {
        $this->authorizeCourse($course);
        return view('lms.teacher.announcements.create', compact('course'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
        $course->announcements()->create($data + [
            'school_id' => $course->school_id,
            'author_id' => $request->user()->id,
            'status' => LmsAnnouncement::DRAFT,
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Pengumuman course berhasil disimpan sebagai draft.');
    }

    public function publish(Course $course, LmsAnnouncement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncement($course, $announcement);
        $announcement->update([
            'status' => LmsAnnouncement::PUBLISHED,
            'published_at' => now(),
        ]);

        $studentUserIds = $course->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student.user_id')
            ->filter()
            ->all();
        $this->notifications->notifyUsers(
            $studentUserIds,
            NotificationService::COURSE_ANNOUNCEMENT,
            $announcement->title,
            $announcement->body,
            ['course_id' => $course->id, 'announcement_id' => $announcement->id]
        );

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Pengumuman course berhasil diterbitkan.');
    }

    private function authorizeAnnouncement(Course $course, LmsAnnouncement $announcement): void
    {
        abort_unless($announcement->course_id === $course->id, 404);
        $this->authorizeCourse($course);
    }

    private function authorizeCourse(Course $course): void
    {
        abort_unless(request()->user()?->can('lms.lesson.manage'), 403);
        $this->authorize('update', $course);
    }
}
