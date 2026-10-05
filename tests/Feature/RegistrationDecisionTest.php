<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduce and pin the registration decision endpoint.
 *
 * THE REPORT
 *
 * `POST /admin/pendaftaran/18/keputusan` answered 422 Unprocessable Content.
 * That status has exactly two sources in this action, and neither is the one it
 * looks like:
 *
 *   1. `$request->validate()` inside the `revise` and `reject` branches — the
 *      note is required and must be at least 10 characters.
 *   2. `abort(422, 'Aksi tidak dikenal.')` for any action that is not approve,
 *      revise or reject.
 *
 * There is NO validate() on the approve branch, so an approve with no note is
 * valid and must succeed. If approve 422s, the cause is upstream of this
 * controller — a middleware, a different route, or a form posting the wrong
 * field names.
 *
 * These tests assert the WRITE lands, not that the response looks right. A 302
 * to `back()` is also what a validation bounce produces, so every branch reads
 * the registration back out of the database.
 */
class RegistrationDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    private Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $year = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 1',
            'level' => 'XII',
            'academic_year_id' => $year->id,
            'department_id' => Department::firstOrCreate(['name' => 'IPA'], ['code' => 'IPA'])->id,
        ]);

        $owner = User::factory()->create(['name' => 'Siswa Pendaftar']);

        $this->student = Student::create([
            'user_id' => $owner->id,
            'full_name' => 'Siswa Pendaftar',
            'nisn' => '3200987654',
            'gender' => 'L',
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ]);

        $this->registration = Registration::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $year->id,
            'status' => Registration::STATUS_PENDING,
        ]);
    }

    /**
     * Give the registration a full set of valid documents, because approve is
     * blocked while any document is missing, pending or rejected — and a test
     * that fails for that reason is not testing the endpoint under audit.
     */
    private function withValidDocuments(): void
    {
        $type = DocumentType::create([
            'name' => 'Kartu Keluarga',
            'label' => 'Kartu Keluarga',
            'slug' => 'kk',
            'is_required' => true,
        ]);

        Document::create([
            'registration_id' => $this->registration->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            // Both NOT NULL: a document row without a file on disk is not a
            // document. Supplying them keeps the fixture honest rather than
            // making the approve path look broken.
            'path' => 'documents/test/kk.pdf',
            'original_name' => 'kk.pdf',
        ]);
    }

    /** @test */
    public function approving_with_no_note_succeeds_and_writes_to_the_database(): void
    {
        // The approve branch has NO validation at all. If this 422s, the cause
        // is upstream of the controller.
        $this->withValidDocuments();

        $this->actingAs($this->admin)
            ->from(route('admin.registrations.show', $this->student))
            ->post(route('admin.decide', $this->student), [
                'action' => 'approve',
            ])
            ->assertRedirect();

        $this->assertSame(
            Registration::STATUS_VERIFIED,
            $this->registration->refresh()->status,
            'The registration was not marked verified.'
        );

        $this->assertNotNull($this->registration->verified_at);
        $this->assertSame($this->admin->id, $this->registration->verified_by);
    }

    /** @test */
    public function approving_stores_the_optional_note(): void
    {
        $this->withValidDocuments();

        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), [
                'action' => 'approve',
                'note' => 'Data sudah lengkap.',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Data sudah lengkap.',
            $this->registration->refresh()->admin_note,
        );
    }

    /** @test */
    public function approving_with_an_outstanding_document_requests_revision_instead(): void
    {
        // The blocked path needs a document that is NOT valid. Zero documents is
        // a different case — nothing outstanding, so approve proceeds, which is
        // what the first test proves.
        $type = DocumentType::create([
            'name' => 'Ijazah',
            'label' => 'Ijazah',
            'slug' => 'ijazah',
            'is_required' => true,
        ]);

        Document::create([
            'registration_id' => $this->registration->id,
            'document_type_id' => $type->id,
            'status' => 'pending',
            'path' => 'documents/test/ijazah.pdf',
            'original_name' => 'ijazah.pdf',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame(
            Registration::STATUS_REVISION,
            $this->registration->refresh()->status,
            'Approve must not verify a registration while a required document is still pending.'
        );
    }

    /** @test */
    public function approving_with_no_documents_at_all_proceeds(): void
    {
        // Stated because it is surprising and it is deliberate: a registration
        // with no required document types has nothing outstanding, so approve
        // verifies it. Blocking here would deadlock a school that has not set
        // up its document checklist yet.
        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame(Registration::STATUS_VERIFIED, $this->registration->refresh()->status);
    }

    /** @test */
    public function revising_requires_a_note_of_ten_characters(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.registrations.show', $this->student))
            ->post(route('admin.decide', $this->student), [
                'action' => 'revise',
                'note' => 'pendek',
            ])
            ->assertSessionHasErrors('note');

        $this->assertSame(
            Registration::STATUS_PENDING,
            $this->registration->refresh()->status,
            'A rejected note must not have moved the status.'
        );
    }

    /** @test */
    public function revising_with_a_valid_note_succeeds(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), [
                'action' => 'revise',
                'note' => 'Mohon lengkapi data NISN dan unggah ijazah yang lebih jelas.',
            ])
            ->assertRedirect();

        $this->assertSame(Registration::STATUS_REVISION, $this->registration->refresh()->status);
    }

    /** @test */
    public function rejecting_requires_a_note(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.registrations.show', $this->student))
            ->post(route('admin.decide', $this->student), [
                'action' => 'reject',
            ])
            ->assertSessionHasErrors('note');

        $this->assertSame(Registration::STATUS_PENDING, $this->registration->refresh()->status);
    }

    /**
     * @test
     */
    public function an_unknown_action_is_the_only_path_to_a_bare_422(): void
    {
        // `abort(422)` here is CORRECT and must stay: an unrecognised action is
        // a programming error, not a user mistake, and the request body must
        // not be trusted to decide what happens to a student record.
        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), ['action' => 'delete-everything'])
            ->assertStatus(422);

        $this->assertSame(
            Registration::STATUS_PENDING,
            $this->registration->refresh()->status,
            'An unknown action changed the registration.'
        );
    }

    /** @test */
    public function a_missing_action_is_also_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), [])
            ->assertStatus(422);

        $this->assertSame(Registration::STATUS_PENDING, $this->registration->refresh()->status);
    }

    /** @test */
    public function a_student_cannot_decide_their_own_registration(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole('siswa');

        $this->actingAs($studentUser)
            ->post(route('admin.decide', $this->student), ['action' => 'approve'])
            ->assertForbidden();
    }
}