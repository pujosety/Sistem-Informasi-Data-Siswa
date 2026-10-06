<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\DocumentService;
use App\Services\StatsService;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly StatsService $stats,
        private readonly VerificationService $verification,
        private readonly DocumentService $documents,
    ) {
        parent::__construct($audit, $completeness);
    }

    public function dashboard(Request $request)
    {
        $yearId = $request->integer('academic_year_id') ?: null;
        $range = $request->string('range')->value('30d');
        $attendanceThreshold = $request->integer('attendance_threshold') ?: 75;
        $summary = $this->stats->registrationSummary($yearId);
        $daily = $this->stats->dailyRegistrations(30);
        $byStatus = $this->stats->byStatus();
        $analytics = $this->stats->dashboardAnalytics($yearId, $range, $attendanceThreshold);

        // Oldest submissions first: the queue is a work list, so the student
        // who has waited longest is the one an admin should pick up.
        // Ordering by registrations.submitted_at needs the join in scope;
        // with whereHas() alone MySQL rejected the ORDER BY column.
        $pendingQueue = Student::query()
            ->join('registrations', 'registrations.student_id', '=', 'students.id')
            ->select('students.*')
            ->where('registrations.status', Registration::STATUS_PENDING)
            ->when($yearId, fn ($q) => $q->where('registrations.academic_year_id', $yearId))
            ->orderBy('registrations.submitted_at')
            ->orderBy('registrations.id')
            ->limit(6)
            ->get()
            ->load('registration');

        return view('admin.dashboard', [
            'summary' => $summary,
            'daily' => $daily,
            'byStatus' => $byStatus,
            'yearId' => $yearId,
            'years' => $this->filterOptions()['years'],
            'counts' => $this->stats->headlineCounts($yearId),
            'analytics' => $analytics,
            'range' => $range,
            'attendanceThreshold' => $attendanceThreshold,
            'byGender' => $this->stats->byGender(),

            // The timeline reads the audit trail rather than a second log: an
            // activity list that disagrees with the audit log is worse than no
            // activity list. Capped hard, because this is a dashboard widget
            // and a full history belongs at admin.activity-logs.
            'activity' => \App\Models\ActivityLog::query()
                ->with('user')
                ->latest()
                ->limit(6)
                ->get(),
            'pendingQueue' => $pendingQueue,
            'recent' => Student::with('registration')
                ->whereHas('registration')
                ->latest('id')
                ->limit(6)
                ->get(),
        ]);
    }

    public function registrations(Request $request)
    {
        // Join registrations up front: the table drives status, completeness and
        // sort-by-date, and ordering on its columns requires it to be in scope.
        $query = Student::query()
            ->join('registrations', 'registrations.student_id', '=', 'students.id')
            ->leftJoin('classes', 'classes.id', '=', 'students.class_id')
            ->select('students.*')
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('registrations.status', $request->string('status')))
            ->when($request->filled('class_id'), fn ($q) => $q->where('students.class_id', $request->integer('class_id')))
            ->when($request->filled('gender'), fn ($q) => $q->where('students.gender', $request->string('gender')))
            ->when($request->filled('from'), fn ($q) => $q->where('registrations.created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('registrations.created_at', '<=', $request->date('to')->endOfDay()))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request));

        $students = $query->with(['registration', 'schoolClass', 'user'])->paginate(15)->withQueryString();
        $options = $this->filterOptions();

        return view('admin.registrations', compact('students', 'options') + ['request' => $request]);
    }

    private function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->value('')) {
            'full_name' => 'students.full_name',
            'nisn' => 'students.nisn',
            'completeness' => 'registrations.completeness',
            default => 'registrations.created_at',
        };
    }

    private function sortDirection(Request $request): string
    {
        $dir = strtolower($request->string('direction')->value('desc'));

        return in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
    }

    public function showRegistration(Student $student)
    {
        $student->load(['parents', 'registration.academicYear', 'schoolClass.department', 'user']);

        $registration = $student->registration;

        abort_if(! $registration, 404);

        $registration->load(['documents.documentType', 'verifications.admin']);

        // Open the first document that still needs a decision, so the admin
        // lands on the work item rather than on an already-approved file.
        $documents = $registration->documents->sortBy(
            fn ($d) => $d->documentType?->sort_order ?? 99
        )->values();

        $activeDocument = $documents->firstWhere('status', 'rejected')
            ?? $documents->firstWhere('status', 'pending')
            ?? $documents->firstWhere('status', 'missing')
            ?? $documents->firstWhere('status', 'valid');

        // Queue navigation: the work list admins actually move through.
        $queueQuery = Student::query()
            ->join('registrations', 'registrations.student_id', '=', 'students.id')
            ->select('students.id')
            ->whereIn('registrations.status', [
                Registration::STATUS_PENDING, Registration::STATUS_REVISION,
            ]);

        if (request()->filled('status')) {
            $queueQuery->where('registrations.status', request('status'));
        }

        $queue = $queueQuery->orderBy('registrations.submitted_at')->pluck('students.id');
        $position = $queue->search($student->id);

        return view('admin.registration-detail', [
            'student' => $student,
            'registration' => $registration,
            'documents' => $documents,
            'activeDocument' => $activeDocument,
            'outstandingCount' => $documents->whereIn('status', ['missing', 'pending', 'rejected'])->count(),
            'canEditStudent' => true,
            'classOptions' => \App\Models\SchoolClass::orderBy('name')->pluck('name', 'id'),
            'queue' => $queue->isNotEmpty(),
            'queueTotal' => $queue->count(),
            'queuePosition' => $position === false ? 0 : $position + 1,
            'prevStudent' => $position !== false && $position > 0 ? $queue[$position - 1] : null,
            'nextStudent' => $position !== false && $position < $queue->count() - 1 ? $queue[$position + 1] : null,
        ]);
    }

    public function updateStudent(Request $request, Student $student)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:25'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'entry_year' => ['nullable', 'digits:4'],
        ]);

        $student->update($data);

        if ($student->registration) {
            $this->completeness->refresh($student->registration);
        }

        $this->audit->log('student.updated_by_admin', $student, 'Admin memperbarui data siswa', $data);

        return $this->backWith('Data siswa diperbarui.');
    }

    public function reviewDocument(Request $request, Student $student, int $document)
    {
        $registration = $student->registrationOrFail();
        $doc = $registration->documents()->where('documents.id', $document)->firstOrFail();

        $action = $request->string('action')->value();

        if ($action === 'approve') {
            $registration = $this->verification->approveDocument($registration, $doc, Auth::id(), $request->input('note'));
            $message = 'Dokumen disetujui.';
        } elseif ($action === 'reject') {
            $validated = $request->validate(['note' => ['required', 'string', 'max:1000', 'min:10']], [
                'note.required' => 'Alasan penolakan wajib diisi agar siswa tahu apa yang harus diperbaiki.',
            ]);

            $registration = $this->verification->rejectDocument($registration, $doc, Auth::id(), $validated['note']);
            $message = 'Dokumen ditolak dan siswa diminta memperbaiki.';
        } else {
            abort(422, 'Aksi tidak dikenal.');
        }

        return $this->backWith($message);
    }

    public function decideRegistration(Request $request, Student $student)
    {
        $registration = $student->registrationOrFail();
        $action = $request->string('action')->value();

        if ($action === 'approve') {
            $this->verification->approveRegistration($registration, Auth::id(), $request->input('note'));
            $message = $registration->refresh()->status === Registration::STATUS_VERIFIED
                ? 'Siswa dinyatakan terverifikasi.'
                : 'Verifikasi belum dapat diselesaikan: masih ada dokumen belum valid.';
            $level = $registration->refresh()->status === Registration::STATUS_VERIFIED ? 'success' : 'error';
        } elseif ($action === 'revise') {
            $validated = $request->validate(['note' => ['required', 'string', 'max:1000', 'min:10']], [
                'note.required' => 'Tuliskan apa yang perlu diperbaiki.',
            ]);
            $this->verification->requestRevision($registration, Auth::id(), $validated['note']);
            $message = 'Permintaan perbaikan dikirim ke siswa.';
            $level = 'success';
        } elseif ($action === 'reject') {
            $validated = $request->validate(['note' => ['required', 'string', 'max:1000', 'min:10']]);
            $this->verification->rejectRegistration($registration, Auth::id(), $validated['note']);
            $message = 'Pendaftaran ditolak.';
            $level = 'success';
        } else {
            abort(422, 'Aksi tidak dikenal.');
        }

        return back()->with($level, $message);
    }

    public function activityLogs(Request $request)
    {
        $logs = ActivityLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity-logs', [
            'logs' => $logs,
            'actions' => ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function users()
    {
        return view('admin.users', [
            'users' => \App\Models\User::with('roles')->orderBy('name')->paginate(20),
        ]);
    }

    public function updateUserRole(Request $request, \App\Models\User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:siswa,kesiswaan,admin'],
        ]);

        $user->syncRoles([$validated['role']]);
        $this->audit->log('user.role_changed', $user, 'Role diubah menjadi '.$validated['role']);

        return $this->backWith('Role '.$user->name.' diperbarui.');
    }
}
