<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LmsAssignment;
use App\Models\LmsAssignmentSubmission;
use App\Models\Student;
use App\Models\WorkspaceFile;
use App\Services\NotificationService;
use App\Services\WorkspaceDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly WorkspaceDriveService $drive,
    ) {}

    public function create(Course $course): View
    {
        $this->authorizeTeacher($course);
        return view('lms.teacher.assignments.create', compact('course'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeTeacher($course);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:9999'],
            'submission_type' => ['required', 'in:text,file,text_file'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'allow_late' => ['nullable', 'boolean'],
        ]);

        $course->assignments()->create($data + [
            'school_id' => $course->school_id,
            'status' => LmsAssignment::DRAFT,
            'allow_late' => (bool) ($data['allow_late'] ?? false),
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Tugas berhasil disimpan sebagai draft.');
    }

    public function publish(Course $course, LmsAssignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($course, $assignment);
        $assignment->update(['status' => LmsAssignment::PUBLISHED]);

        $studentUserIds = $course->enrollments()
            ->where('status', CourseEnrollment::ACTIVE)
            ->with('student')
            ->get()
            ->pluck('student.user_id')
            ->filter()
            ->all();
        $this->notifications->notifyUsers(
            $studentUserIds,
            NotificationService::ASSIGNMENT_PUBLISHED,
            'Tugas baru: '.$assignment->title,
            $assignment->due_at
                ? 'Tugas baru tersedia. Batas pengumpulan '.$assignment->due_at->format('d M Y H:i').'.'
                : 'Tugas baru tersedia di course '.$course->title.'.',
            ['course_id' => $course->id, 'assignment_id' => $assignment->id]
        );

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Tugas berhasil diterbitkan.');
    }

    public function submissions(Course $course, LmsAssignment $assignment): View
    {
        $this->authorizeAssignment($course, $assignment);
        $assignment->load(['submissions.student', 'course']);
        return view('lms.teacher.assignments.submissions', compact('course', 'assignment'));
    }

    public function grade(Request $request, Course $course, LmsAssignment $assignment, LmsAssignmentSubmission $submission): RedirectResponse
    {
        $this->authorizeAssignment($course, $assignment);
        abort_unless($submission->assignment_id === $assignment->id && $submission->school_id === $course->school_id, 404);
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:10000'],
        ]);
        $submission->update([
            'score' => $data['score'], 'feedback' => $data['feedback'] ?? null,
            'graded_by' => $request->user()->id, 'graded_at' => now(), 'status' => LmsAssignmentSubmission::GRADED,
        ]);
        $submission->load('student');
        if ($submission->student) {
            $this->notifications->notifyStudent(
                $submission->student,
                NotificationService::ASSIGNMENT_GRADED,
                'Tugas dinilai: '.$assignment->title,
                'Nilai Anda '.$data['score'].' dari '.$assignment->max_score.'.'.(($data['feedback'] ?? null) ? ' Feedback guru tersedia.' : ''),
                ['course_id' => $course->id, 'assignment_id' => $assignment->id, 'submission_id' => $submission->id]
            );
        }
        return back()->with('success', 'Nilai dan feedback berhasil disimpan.');
    }

    public function showForStudent(Request $request, Course $course, LmsAssignment $assignment): View
    {
        $student = $this->student($request);
        $this->authorizeEnrollment($student, $course);
        abort_unless($assignment->course_id === $course->id && $assignment->status === LmsAssignment::PUBLISHED, 404);
        $driveFiles = $this->drive->searchFilesFor($request->user(), '');
        $submission = $assignment->submissions()->where('student_id', $student->id)->latest('id')->first();
        return view('lms.student.assignments.show', compact('course', 'assignment', 'submission', 'driveFiles'));
    }

    public function submit(Request $request, Course $course, LmsAssignment $assignment): RedirectResponse
    {
        $student = $this->student($request);
        $this->authorizeEnrollment($student, $course);
        abort_unless($assignment->course_id === $course->id && $assignment->status === LmsAssignment::PUBLISHED, 404);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:30000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'workspace_file' => ['nullable', 'uuid'],
        ]);
        if ($assignment->submission_type === 'text' && blank($data['body'] ?? null)) {
            return back()->withErrors(['body' => 'Jawaban teks wajib diisi.']);
        }
        $attempts = $assignment->submissions()->where('student_id', $student->id)->count();
        abort_if($attempts >= $assignment->max_attempts, 422, 'Batas percobaan tugas sudah tercapai.');
        $late = $assignment->due_at?->isPast() ?? false;
        abort_if($late && ! $assignment->allow_late, 422, 'Batas waktu tugas sudah lewat.');

        $workspaceVersionId = null;
        if (! empty($data['workspace_file'])) {
            $workspaceVersionId = DB::transaction(function () use ($request, $data, $student, $course): int {
                $file = WorkspaceFile::query()->where('public_id', $data['workspace_file'])
                    ->where('owner_id', $request->user()->id)->where('school_id', $course->school_id)
                    ->lockForUpdate()->firstOrFail();
                $this->drive->assertOwner($request->user(), $file);
                $version = $file->versions()->where('version_number', $file->current_version)->firstOrFail();
                app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($version);
                return (int) $version->id;
            });
        }
        $filePath = $data['file'] ?? null
            ? $data['file']->store('lms/'.$course->school_id.'/assignments', 'local')
            : null;
        $assignment->submissions()->create([
            'school_id' => $course->school_id,
            'student_id' => $student->id,
            'body' => $data['body'] ?? null,
            'file_path' => $filePath,
            'workspace_file_version_id' => $workspaceVersionId,
            'submitted_at' => now(),
            'is_late' => $late,
            'status' => LmsAssignmentSubmission::SUBMITTED,
        ]);
        $this->notifications->notifyUser(
            $course->teacher_id,
            NotificationService::ASSIGNMENT_SUBMITTED,
            'Pengumpulan baru: '.$assignment->title,
            ($student->full_name ?: 'Siswa').' mengirim jawaban untuk tugas ini.'.($late ? ' Pengumpulan terlambat.' : ''),
            ['course_id' => $course->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id]
        );
        return redirect()->route('lms.student.assignments.show', [$course, $assignment])
            ->with('success', 'Tugas berhasil dikirim.');
    }

    public function downloadWorkspaceSnapshot(Request $request, LmsAssignmentSubmission $submission)
    {
        $submission->load(['assignment.course', 'student', 'workspaceFileVersion']);
        $assignment = $submission->assignment;
        $course = $assignment?->course;
        abort_unless($course && (int) $assignment->school_id === (int) $course->school_id && (int) $submission->school_id === (int) $course->school_id, 404);
        $user = $request->user();
        if ($user->student()->whereKey($submission->student_id)->exists()) {
            abort_unless(CourseEnrollment::query()->where('school_id', $course->school_id)->where('course_id', $course->id)->where('student_id', $submission->student_id)->where('status', CourseEnrollment::ACTIVE)->exists(), 403);
        } else {
            abort_unless($user->can('lms.assignment.grade'), 403);
            $this->authorizeTeacher($course);
        }
        $version = $submission->workspaceFileVersion;
        $file = $version
            ? WorkspaceFile::withTrashed()->where('school_id', $course->school_id)->whereKey($version->workspace_file_id)->first()
            : null;
        abort_unless($version && $file && (int) $file->owner_id === (int) $submission->student?->user_id && (int) $file->school_id === (int) $course->school_id, 404);
        abort_unless($version->disk === 'workspace' && Storage::disk('workspace')->exists($version->path), 404);
        app(\App\Services\WorkspaceMalwareScanService::class)->assertClean($version);
        return Storage::disk('workspace')->download($version->path, basename($file->name), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeTeacher(Course $course): void
    {
        abort_unless(request()->user()?->can('lms.assignment.manage'), 403);
        $this->authorize('update', $course);
    }

    private function authorizeAssignment(Course $course, LmsAssignment $assignment): void
    {
        abort_unless($assignment->course_id === $course->id, 404);
        $this->authorizeTeacher($course);
    }

    private function student(Request $request): Student { return $request->user()->student()->firstOrFail(); }

    private function authorizeEnrollment(Student $student, Course $course): void
    {
        abort_unless(CourseEnrollment::query()->where('school_id', $course->school_id)->where('course_id', $course->id)->where('student_id', $student->id)->where('status', CourseEnrollment::ACTIVE)->exists(), 403);
    }
}
