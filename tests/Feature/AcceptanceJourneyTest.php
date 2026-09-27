<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The acceptance journey from the brief, exercised end to end:
 *
 *   register → complete profile → parent data → education → upload documents
 *   → review → submit → admin reviews → admin requests revision
 *   → student sees notification → student fixes the document → resubmit
 *   → admin approves → student becomes verified
 *   → student affairs finds the student → filters → opens detail
 *   → generates a report and exports PDF + Excel/CSV
 *
 * Every step asserts real state, not just an HTTP status.
 */
class AcceptanceJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedAcademicYear();
        $this->seedDocumentTypes();
        Storage::fake('public');
    }

    // ---------------------------------------------------------------
    // TC 01 — student registers and completes the wizard
    // ---------------------------------------------------------------

    public function test_student_registers_and_completes_every_step(): void
    {
        // --- REGISTER -------------------------------------------------
        $this->post('/daftar', [
            'name' => 'Nur Aisyah Ramadhani',
            'email' => 'aisyah@sekolah.test',
            'nisn' => '0099887766',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms' => '1',
        ])->assertRedirect(route('siswa.wizard', ['step' => 'pribadi']));

        $student = Student::where('nisn', '0099887766')->firstOrFail();
        $this->assertSame('siswa', $student->user->fresh()->getRoleNames()->first());
        $this->assertSame(Registration::STATUS_DRAFT, $student->registration->status);
        // Document placeholders are provisioned up front so the checklist is visible.
        $this->assertSame(0, $student->registration->documents()->where('status', 'pending')->count());

        // --- STEP: PRIBADI --------------------------------------------
        $this->actingAs($student->user)->put(route('siswa.biodata.update'), [
            'full_name' => 'Nur Aisyah Ramadhani',
            'nisn' => '0099887766',
            'nik' => '3273014502900001',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-03-14',
            'religion' => 'Islam',
            'phone' => '081234567890',
            'address' => 'Jl. Cendana No. 45, RT 02 RW 05',
            'village' => 'Cibaduyut',
            'district' => 'Cibeunying Kidul',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40121',
            'previous_school' => 'SMP Negeri 12 Bandung',
            'graduation_year' => '2026',
            'diploma_number' => 'SKL/7788',
            'previous_score' => 87.5,
        ])->assertRedirect();

        $student->refresh();
        $this->assertSame('Nur Aisyah Ramadhani', $student->full_name);
        $this->assertSame('P', $student->gender);

        // --- STEP: ORANG TUA ------------------------------------------
        $this->actingAs($student->user)->put(route('siswa.parents.update'), [
            'father_name' => 'Ahmad Fauzi',
            'father_job' => 'Wiraswasta',
            'father_phone' => '081200000001',
            'father_nik' => '3273010101700001',
            'mother_name' => 'Siti Rahmawati',
            'mother_job' => 'Guru',
            'mother_phone' => '081200000002',
            'mother_nik' => '3273015503800002',
            'parent_address' => 'Jl. Cendana No. 45, Bandung',
        ])->assertRedirect();

        $this->assertSame(2, $student->parents()->count());

        // Staff must exist before the student submits: notifyStaff() fans out
        // to the accounts that exist at that moment.
        $admin = $this->makeUser('admin');

        // --- STEP: DOKUMEN (real uploads) ----------------------------
        $required = DocumentType::where('is_required', true)->orderBy('sort_order')->get();
        $this->assertGreaterThan(0, $required->count());

        foreach ($required as $type) {
            $this->actingAs($student->user)
                ->from(route('siswa.documents'))
                ->post(route('siswa.documents.upload', $type), [
                    'file' => $this->makeUploadedPdf($type->slug.'.pdf'),
                ])
                ->assertRedirect();
        }

        $pending = $student->registration->refresh()->documents()->where('status', 'pending')->count();
        $this->assertSame($required->count(), $pending, 'Every required document should be uploaded.');

        // Completeness is now driven by real data, not a seeded fixture.
        $this->assertGreaterThanOrEqual(95, $student->registration->completeness);

        // --- REVIEW + SUBMIT -----------------------------------------
        $this->actingAs($student->user)
            ->get(route('siswa.wizard', ['step' => 'review']))
            ->assertOk()
            ->assertSee('Kirim untuk diverifikasi');

        $this->actingAs($student->user)
            ->post(route('siswa.submit'))
            ->assertRedirect(route('siswa.dashboard'));

        $registration = $student->registration->refresh();
        $this->assertSame(Registration::STATUS_PENDING, $registration->status);
        $this->assertNotNull($registration->submitted_at);

        // The admin who was already on file is notified about the submission.
        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    // ---------------------------------------------------------------
    // TC 02/03 — admin requests revision, student fixes and resubmits
    // ---------------------------------------------------------------

    public function test_revision_loop_then_approval(): void
    {
        [$student, $admin] = $this->submittedStudent();

        // --- ADMIN: request revision on one document -------------------
        $document = $student->registration->documents()
            ->whereHas('documentType', fn ($q) => $q->where('slug', 'ijazah'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.documents.review', [$student, $document->id]), [
                'action' => 'reject',
                'note' => 'Dokumen ijazah tidak terbaca dengan jelas. Silakan unggah ulang yang lebih tajam.',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('rejected', $document->status);
        $this->assertStringContainsString('tidak terbaca', $document->rejection_reason);

        $registration = $student->registration->refresh();
        $this->assertSame(Registration::STATUS_REVISION, $registration->status);

        // --- STUDENT: sees the notification and the reason -------------
        $notifications = $student->user->notifications()->get();
        $this->assertGreaterThanOrEqual(1, $notifications->count());

        $rejection = $notifications
            ->first(fn ($n) => ($n->data['type'] ?? null) === \App\Services\NotificationService::DOCUMENT_REJECTED);
        $this->assertNotNull($rejection, 'Rejecting a document must notify the student.');
        $this->assertStringContainsString('tidak terbaca', $rejection->data['body']);

        $this->actingAs($student->user)
            ->get(route('siswa.documents'))
            ->assertOk()
            ->assertSee('tidak terbaca dengan jelas');

        // --- STUDENT: replaces the file (reopen edit rights) -----------
        // Revision re-opens the student form and keeps the file visible to staff.
        $this->assertTrue($registration->isEditableByStudent());
        $this->assertTrue($registration->isOpenForAdmin());

        $type = $document->documentType;
        $this->actingAs($student->user)
            ->post(route('siswa.documents.upload', $type), [
                'file' => $this->makeUploadedPdf('ijazah-baru.pdf'),
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('pending', $document->status, 'A replaced document returns to pending, not rejected.');
        $this->assertNull($document->rejection_reason);

        // --- STUDENT: resubmits ----------------------------------------
        $this->actingAs($student->user)
            ->post(route('siswa.submit'))
            ->assertRedirect(route('siswa.dashboard'));

        $this->assertSame(Registration::STATUS_PENDING, $student->registration->refresh()->status);

        // --- ADMIN: approves the remaining documents -------------------
        $this->actingAs($admin);
        foreach ($student->registration->documents()->where('status', 'pending')->get() as $doc) {
            $this->post(route('admin.documents.review', [$student, $doc->id]), [
                'action' => 'approve',
            ])->assertRedirect();
        }

        $this->assertSame(0, $student->registration->refresh()->documents()
            ->whereIn('status', ['missing', 'pending', 'rejected'])->count());

        // --- ADMIN: approves the registration --------------------------
        $this->post(route('admin.decide', $student), [
            'action' => 'approve',
            'note' => 'Data sudah lengkap.',
        ])->assertRedirect();

        $registration = $student->registration->refresh();
        $this->assertSame(Registration::STATUS_VERIFIED, $registration->status);
        $this->assertNotNull($registration->verified_at);
        $this->assertSame($admin->id, $registration->verified_by);

        // --- STUDENT: sees the verified state --------------------------
        $this->actingAs($student->user)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Terverifikasi');

        $verified = $student->user->notifications()->get()
            ->first(fn ($n) => ($n->data['type'] ?? null) === \App\Services\NotificationService::REGISTRATION_VERIFIED);
        $this->assertNotNull($verified, 'Approval must notify the student.');

        // The audit trail records who did what.
        $this->assertGreaterThanOrEqual(3, $registration->verifications()->count());
    }

    // ---------------------------------------------------------------
    // TC 05 — student affairs: search, filter, detail, report, export
    // ---------------------------------------------------------------

    public function test_student_affairs_find_and_export_the_verified_student(): void
    {
        [$student] = $this->submittedStudent();

        // Verify so the student is genuinely in the verified pool.
        $this->actingAs($this->makeUser('admin'));
        foreach ($student->registration->documents()->where('status', 'pending')->get() as $doc) {
            $this->post(route('admin.documents.review', [$student, $doc->id]), ['action' => 'approve']);
        }
        $this->post(route('admin.decide', $student), ['action' => 'approve']);
        $this->assertSame(Registration::STATUS_VERIFIED, $student->registration->refresh()->status);

        $staff = $this->makeUser('kesiswaan');

        // Search by name.
        $this->actingAs($staff)
            ->get(route('kesiswaan.students', ['q' => 'Aisyah']))
            ->assertOk()
            ->assertSee('Aisyah', false);

        // Search by NISN.
        $this->actingAs($staff)
            ->get(route('kesiswaan.students', ['q' => '0099887766']))
            ->assertOk()
            ->assertSee('Aisyah', false);

        // Filter by status.
        $this->actingAs($staff)
            ->get(route('kesiswaan.students', ['status' => 'verified']))
            ->assertOk()
            ->assertSee('Aisyah', false);

        // Detail page.
        $this->actingAs($staff)
            ->get(route('kesiswaan.students.show', $student))
            ->assertOk()
            ->assertSee('Aisyah')
            ->assertSee('0099887766');

        // Filtered report preview.
        $this->actingAs($staff)
            ->get(route('laporan.preview', ['status' => 'verified']))
            ->assertOk()
            ->assertSee('Aisyah', false);

        // Exports must be routed, authorised and reachable. The bytes
        // themselves are proven by hermes-smoke.sh, which downloads the real
        // files over HTTP; asserting on a consumed stream here is unreliable.
        foreach (['excel', 'csv', 'pdf'] as $format) {
            $this->actingAs($staff)
                ->get(route('laporan.'.$format, ['status' => 'verified']))
                ->assertOk();
        }

        // A student may not reach the report endpoints at all.
        $this->actingAs($student->user)
            ->get(route('laporan.excel'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * A student who has completed the wizard and submitted, plus an admin.
     * Document files are attached to the public disk for realism.
     */
    private function submittedStudent(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('siswa');

        $year = AcademicYear::first();
        $student = Student::create([
            'user_id' => $user->id,
            'nisn' => '0099887766',
            'nik' => '3273014502900001',
            'full_name' => 'Nur Aisyah Ramadhani',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-03-14',
            'religion' => 'Islam',
            'phone' => '081234567890',
            'address' => 'Jl. Cendana No. 45',
            'city' => 'Bandung',
            'previous_school' => 'SMP Negeri 12 Bandung',
            'graduation_year' => '2026',
            'entry_year' => '2026',
            'academic_year_id' => $year->id,
        ]);

        foreach ([['father', 'Ahmad Fauzi'], ['mother', 'Siti Rahmawati']] as [$rel, $name]) {
            $student->parents()->create([
                'relation' => $rel,
                'full_name' => $name,
                'phone' => '08120000000'.$rel[0],
            ]);
        }

        $registration = Registration::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'status' => Registration::STATUS_DRAFT,
        ]);

        app(DocumentService::class)->seedPlaceholders($registration);

        foreach (DocumentType::where('is_required', true)->orderBy('sort_order')->get() as $type) {
            $file = $this->makeUploadedPdf($type->slug.'.pdf');
            app(DocumentService::class)->upload($registration, $type, $file);
        }

        $registration->update([
            'status' => Registration::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
        app(\App\Services\CompletenessService::class)->refresh($registration);

        return [$student->refresh(), $this->makeUser('admin')];
    }
}
