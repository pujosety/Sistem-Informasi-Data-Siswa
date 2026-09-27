<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
use App\Http\Requests\ParentDataRequest;
use App\Http\Requests\StudentProfileRequest;
use App\Http\Requests\WizardStepRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ParentGuardian;
use App\Models\Registration;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\DocumentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The student-facing registration journey.
 *
 * Long single-page forms were replaced by a resumable wizard; the individual
 * editors (biodata / orang tua) are preserved because existing links and the
 * notification deep-links still point at them.
 */
class StudentPortalController extends BaseController
{
    public const WIZARD_STEPS = [
        'akun' => ['label' => 'Akun', 'icon' => 'user'],
        'pribadi' => ['label' => 'Data Pribadi', 'icon' => 'user'],
        'orang-tua' => ['label' => 'Orang Tua / Wali', 'icon' => 'users'],
        'pendidikan' => ['label' => 'Pendidikan', 'icon' => 'book'],
        'dokumen' => ['label' => 'Dokumen', 'icon' => 'files'],
        'review' => ['label' => 'Review & Kirim', 'icon' => 'clipboard-check'],
    ];

    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly DocumentService $documents,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($audit, $completeness);
    }

    private function currentStudent(): Student
    {
        return Student::where('user_id', Auth::id())->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------

    public function dashboard()
    {
        $student = $this->currentStudent()->load('parents');
        $registration = $student->registration;
        $registration?->load(['academicYear', 'verifications']);

        $types = DocumentType::where('is_active', true)->orderBy('sort_order')->get();
        $documents = $registration
            ? $registration->documents()->with('documentType')->get()->keyBy('document_type_id')
            : collect();

        $missing = $types->filter(fn ($t) => ! ($documents[$t->id] ?? null) || $documents[$t->id]->status === 'missing')->values();
        $rejected = $documents->filter(fn ($d) => $d && $d->status === 'rejected')->values();
        $pending = $documents->filter(fn ($d) => $d && $d->status === 'pending')->values();

        $nextStep = match ($registration?->status) {
            Registration::STATUS_VERIFIED => 'Data Anda sudah terverifikasi. Silakan cek kembali berkala.',
            Registration::STATUS_PENDING, Registration::STATUS_SUBMITTED => 'Menunggu pemeriksaan admin. Tidak perlu melakukan apa-apa.',
            Registration::STATUS_REVISION => 'Ada bagian yang perlu diperbaiki. Buka menu Dokumen.',
            default => 'Lengkapi biodata dan unggah berkas untuk memulai.',
        };

        return view('siswa.dashboard', compact(
            'student', 'registration', 'types', 'documents', 'missing', 'rejected', 'pending', 'nextStep'
        ));
    }

    // -----------------------------------------------------------------
    // Wizard
    // -----------------------------------------------------------------

    public function wizard(Request $request, ?string $step = null): View|RedirectResponse
    {
        $student = $this->currentStudent()->load(['parents', 'user']);
        $registration = $student->registration;

        if (! $registration) {
            return redirect()->route('siswa.dashboard')
                ->with('error', 'Data pendaftaran belum tersedia. Hubungi admin sekolah.');
        }

        $step = $step ?: $this->firstIncompleteStep($student, $registration);
        $types = DocumentType::where('is_active', true)->orderBy('sort_order')->get();
        $documents = $registration->documents()->with('documentType')->get()->keyBy('document_type_id');
        $parents = $student->parents->keyBy('relation');
        $rejectedDocuments = $documents->filter(fn ($d) => $d && $d->status === 'rejected')->values();

        return view('siswa.wizard', [
            'student' => $student,
            'registration' => $registration->load('academicYear'),
            'step' => $step,
            'steps' => self::WIZARD_STEPS,
            'types' => $types,
            'documents' => $documents,
            'parents' => $parents,
            'rejectedDocuments' => $rejectedDocuments,
            'religions' => ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan'],
            'isEditable' => $registration->isEditableByStudent(),
            'completeness' => $this->completeness->compute($student, $registration),
        ]);
    }

    public function saveStep(WizardStepRequest $request): RedirectResponse
    {
        $student = $this->currentStudent();
        $registration = $student->registration;

        abort_if(! $registration, 404);

        if (! $registration->isEditableByStudent()) {
            return redirect()->route('siswa.wizard')
                ->with('error', 'Data terkunci karena sedang diverifikasi admin.');
        }

        $step = $request->input('step', 'pribadi');
        $data = $request->validated();

        if ($step === 'akun') {
            $user = $student->user;
            if (! empty($data['name'])) {
                $user->update(['name' => $data['name']]);
            }
            if (! empty($data['email'])) {
                $user->update(['email' => $data['email']]);
            }
        }

        if (in_array($step, ['pribadi', 'pendidikan'], true)) {
            $fields = collect($data)->only(Student::REGISTRATION_FIELDS)->all();
            $student->fill($fields);
            if ($step === 'pribadi' && ! empty($data['full_name'])) {
                $student->user->update(['name' => $data['full_name']]);
            }
            $student->save();
        }

        if ($step === 'orang-tua') {
            $this->syncParents($student, $data);
        }

        $this->completeness->refresh($registration);
        $this->audit->log('wizard.step_saved', $student, "Wizard step: {$step}");

        $next = $this->nextStep($step);
        $redirect = $request->boolean('save_only')
            ? route('siswa.wizard', ['step' => $step])
            : route('siswa.wizard', ['step' => $next]);

        return redirect($redirect)->with('success', 'Data tersimpan.');
    }

    private function nextStep(string $current): string
    {
        $keys = array_keys(self::WIZARD_STEPS);
        $index = array_search($current, $keys, true);

        return $keys[min($index + 1, count($keys) - 1)];
    }

    /** Jump the user to the first step that is not yet satisfied. */
    private function firstIncompleteStep(Student $student, Registration $registration): string
    {
        if (! $student->birth_date || ! $student->city) {
            return 'pribadi';
        }

        if ($student->parents()->whereIn('relation', ['father', 'mother'])->count() < 2) {
            return 'orang-tua';
        }

        if (! $student->previous_school) {
            return 'pendidikan';
        }

        $required = DocumentType::where('is_active', true)->where('is_required', true)->count();
        $uploaded = $registration->documents()->where('status', '!=', 'missing')->count();

        if ($uploaded < $required) {
            return 'dokumen';
        }

        return 'review';
    }

    // -----------------------------------------------------------------
    // Individual editors (preserved)
    // -----------------------------------------------------------------

    public function editBiodata(): View
    {
        $student = $this->currentStudent()->load('registration');

        return view('siswa.biodata', [
            'student' => $student,
            'registration' => $student->registration,
            'religions' => $this->religions(),
        ]);
    }

    public function updateBiodata(StudentProfileRequest $request): RedirectResponse
    {
        $student = $this->currentStudent();
        $data = $request->validated();

        // REGISTRATION_FIELDS is already a list of names; wrapping it in
        // array_keys() produced [0,1,2,...] and ->only() matched nothing, so a
        // student's entire biodata was silently discarded.
        $student->update(collect($data)->only(Student::REGISTRATION_FIELDS)->all());

        if (! empty($data['full_name'])) {
            $student->user->update(['name' => $data['full_name']]);
        }

        if ($student->registration) {
            $this->completeness->refresh($student->registration);
        }

        $this->audit->log('student.biodata_updated', $student, 'Siswa memperbarui biodata');

        return $this->backWith('Biodata berhasil disimpan.');
    }

    public function editParents(): View
    {
        $student = $this->currentStudent()->load(['parents', 'registration']);

        return view('siswa.parents', [
            'student' => $student,
            'registration' => $student->registration,
        ]);
    }

    public function updateParents(ParentDataRequest $request): RedirectResponse
    {
        $student = $this->currentStudent();

        $this->syncParents($student, $request->validated());

        if ($student->registration) {
            $this->completeness->refresh($student->registration);
        }

        $this->audit->log('student.parents_updated', $student, 'Siswa memperbarui data orang tua/wali');

        return $this->backWith('Data orang tua/wali berhasil disimpan.');
    }

    /**
     * Upsert father/mother/guardian from the flat form payload.
     */
    private function syncParents(Student $student, array $data): void
    {
        $map = [
            'father' => ['father_name', 'father_job', 'father_phone', 'father_nik'],
            'mother' => ['mother_name', 'mother_job', 'mother_phone', 'mother_nik'],
            'guardian' => ['guardian_name', 'guardian_job', 'guardian_phone', 'parent_address'],
        ];

        DB::transaction(function () use ($student, $map, $data) {
            foreach ($map as $relation => $fields) {
                [$name, $job, $phone, $nik] = $fields;
                $value = $data[$name] ?? null;

                if ($relation === 'guardian' && blank($value)) {
                    $student->parents()->where('relation', 'guardian')->delete();

                    continue;
                }

                $student->parents()->updateOrCreate(
                    ['relation' => $relation],
                    [
                        'full_name' => $value,
                        'job' => $data[$job] ?? null,
                        'phone' => $data[$phone] ?? null,
                        'nik' => ($relation === 'guardian') ? null : ($data[$nik] ?? null),
                        'address' => $data['parent_address'] ?? null,
                    ]
                );
            }
        });
    }

    // -----------------------------------------------------------------
    // Documents
    // -----------------------------------------------------------------

    public function documents(): View
    {
        $student = $this->currentStudent()->load('registration');
        $registration = $student->registration;

        $types = DocumentType::where('is_active', true)->orderBy('sort_order')->get();
        $documents = $registration
            ? $registration->documents()->with('documentType')->get()->keyBy('document_type_id')
            : collect();

        // The view raises an ACTION REQUIRED banner from this.
        $rejected = $documents->filter(fn ($d) => $d && $d->status === 'rejected')->values();

        return view('siswa.documents', compact('student', 'registration', 'types', 'documents', 'rejected'));
    }

    public function uploadDocument(DocumentUploadRequest $request, DocumentType $documentType)
    {
        $student = $this->currentStudent();
        $registration = $student->registration;

        if (! $registration) {
            return $this->backWith('Lengkapi biodata terlebih dahulu.', 'error');
        }

        $this->documents->upload($registration, $documentType, $request->file('file'));
        $this->audit->log('document.uploaded', $student, 'Mengunggah '.$documentType->name);

        return back()->with('success', $documentType->label.' berhasil diunggah.');
    }

    public function deleteDocument(DocumentType $documentType)
    {
        $student = $this->currentStudent();
        $registration = $student->registration;

        if (! $registration || ! $registration->isEditableByStudent()) {
            return $this->backWith('Dokumen terkunci saat pendaftaran sedang diverifikasi.', 'error');
        }

        $document = $registration->documents()->where('document_type_id', $documentType->id)->first();

        if (! $document || $document->status === 'missing') {
            return $this->backWith('Tidak ada berkas untuk dihapus.', 'error');
        }

        $this->documents->delete($document);
        $this->documents->seedPlaceholders($registration);
        $this->completeness->refresh($registration);

        return $this->backWith('Berkas dihapus.');
    }

    // -----------------------------------------------------------------
    // Status / verification history
    // -----------------------------------------------------------------

    public function status(): View
    {
        $student = $this->currentStudent();
        $registration = $student->registration;

        abort_if(! $registration, 404);

        $registration->load(['verifications.admin', 'documents.documentType', 'academicYear']);

        return view('siswa.status', [
            'student' => $student,
            'registration' => $registration,
        ]);
    }

    // -----------------------------------------------------------------
    // Submit
    // -----------------------------------------------------------------

    public function submit()
    {
        $student = $this->currentStudent();
        $registration = $student->registration;

        if (! $registration) {
            return $this->backWith('Lengkapi biodata terlebih dahulu.', 'error');
        }

        $this->completeness->refresh($registration);

        $requiredTypes = DocumentType::where('is_active', true)->where('is_required', true)->count();
        $uploaded = $registration->documents()->where('status', '!=', 'missing')->count();
        $parentsOk = $student->parents()->whereIn('relation', ['father', 'mother'])->count() === 2;

        if (! $parentsOk) {
            return $this->backWith('Lengkapi data ayah dan ibu terlebih dahulu.', 'error');
        }

        if ($uploaded < $requiredTypes) {
            $remaining = $requiredTypes - $uploaded;

            return $this->backWith("Masih ada $remaining berkas wajib yang belum diunggah.", 'error');
        }

        $registration->update([
            'status' => Registration::STATUS_PENDING,
            'submitted_at' => now(),
            'admin_note' => null,
        ]);

        $this->documents->recordVerification($registration, 'submit', Auth::id(), null, 'Dikirim siswa');
        $this->audit->log('registration.submitted', $registration, 'Siswa mengirim pendaftaran');
        $this->notifications->onRegistrationSubmitted($registration);

        return redirect()->route('siswa.dashboard')
            ->with('success', 'Pendaftaran berhasil dikirim. Silakan tunggu verifikasi admin.');
    }

    private function religions(): array
    {
        return ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan'];
    }
}
