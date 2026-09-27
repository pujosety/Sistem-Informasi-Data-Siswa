<?php

namespace App\Http\Controllers;

use App\Models\ClassroomAnnouncement;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Services\AuditService;
use App\Services\ClassScope;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Class announcements. A Wali Kelas may publish only to their own classroom —
 * the scope check enforces that even when a classroom id is edited in the URL.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private readonly ClassScope $scope,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request, SchoolClass $classroom): View
    {
        abort_unless($this->scope->canView($request->user(), $classroom), 403);
        abort_unless($request->user()->can('viewAnnouncements', $classroom), 403);

        return view('academic.announcements.index', [
            'classroom' => $classroom->load(['academicYear', 'department']),
            'announcements' => $classroom->announcements()
                ->with('author')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->paginate(10),
            'audiences' => ClassroomAnnouncement::AUDIENCES,
        ]);
    }

    public function create(Request $request, SchoolClass $classroom): View
    {
        abort_unless($this->scope->canView($request->user(), $classroom), 403);
        abort_unless($request->user()->can('publishAnnouncement', $classroom), 403);

        return view('academic.announcements.create', [
            'classroom' => $classroom->load(['academicYear', 'department']),
            'audiences' => ClassroomAnnouncement::AUDIENCES,
        ]);
    }

    public function store(Request $request, SchoolClass $classroom): RedirectResponse
    {
        abort_unless($this->scope->canView($request->user(), $classroom), 403);
        abort_unless($request->user()->can('publishAnnouncement', $classroom), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(array_keys(ClassroomAnnouncement::AUDIENCES))],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'publish' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'body.required' => 'Isi pengumuman wajib diisi.',
            'audience.in' => 'Audiens tidak valid.',
            'expires_at.after' => 'Tanggal berakhir harus setelah waktu sekarang.',
        ]);

        $announcement = $classroom->announcements()->create([
            'academic_year_id' => $classroom->academic_year_id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'published_at' => $request->boolean('publish') ? now() : null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log(
            'announcement.publish',
            $announcement,
            "Pengumuman \"{$announcement->title}\" untuk kelas {$classroom->name}",
        );

        if ($announcement->published_at) {
            $this->notifyAudience($classroom, $announcement);
        }

        return redirect()
            ->route('academic.announcements.index', $classroom)
            ->with('success', 'Pengumuman berhasil disimpan.');
    }

    public function destroy(Request $request, SchoolClass $classroom, ClassroomAnnouncement $announcement): RedirectResponse
    {
        abort_unless($this->scope->canView($request->user(), $classroom), 403);
        abort_unless($request->user()->can('classroom.announcement.delete'), 403);
        abort_unless($announcement->classroom_id === $classroom->id, 404);

        $announcement->delete();
        $this->audit->log('announcement.delete', $classroom, "Pengumuman \"{$announcement->title}\" dihapus");

        return back()->with('success', 'Pengumuman dihapus.');
    }

    /**
     * Notify the students, and/or the guardians, of this classroom.
     */
    private function notifyAudience(SchoolClass $classroom, ClassroomAnnouncement $announcement): void
    {
        $enrollments = $classroom->liveEnrollments()->with('student')->get();

        $studentUserIds = in_array($announcement->audience, ['students', 'both'], true)
            ? $enrollments->pluck('student.user_id')->filter()->all()
            : [];

        $guardianUserIds = in_array($announcement->audience, ['parents', 'both'], true)
            ? \App\Models\GuardianRelationship::query()
                ->whereIn('student_id', $enrollments->pluck('student_id'))
                ->where('status', 'active')
                ->pluck('guardian_user_id')
                ->all()
            : [];

        $recipients = array_values(array_unique(array_merge($studentUserIds, $guardianUserIds)));

        $this->notifications->notifyUsers(
            $recipients,
            \App\Services\NotificationService::CLASS_ANNOUNCEMENT,
            "Pengumuman kelas {$classroom->name}",
            $announcement->title,
            ['classroom_id' => $classroom->id, 'announcement_id' => $announcement->id],
        );
    }
}
