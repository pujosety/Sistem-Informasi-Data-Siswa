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
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Document verification writes to the database.
 *
 * THE CLASS OF BUG THIS GUARDS
 *
 * Brief §9 and §11 both describe forms that submit and save nothing. The
 * verification flow is the worst place for that: an admin marks a document
 * valid, sees a success flash, and the checklist has not moved — so the next
 * approve is blocked by a document that looks approved.
 *
 * Every test here reads the row back. A 302 is what both a success AND a
 * validation bounce produce, so asserting the response alone proves nothing.
 */
class DocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    private Registration $registration;

    private DocumentType $type;

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

        $owner = User::factory()->create();

        $this->student = Student::create([
            'user_id' => $owner->id,
            'full_name' => 'Siswa Dokumen',
            'nisn' => '3200111222',
            'gender' => 'P',
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ]);

        $this->registration = Registration::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $year->id,
            'status' => Registration::STATUS_PENDING,
        ]);

        $this->type = DocumentType::create([
            'name' => 'Ijazah',
            'label' => 'Ijazah',
            'slug' => 'ijazah',
            'is_required' => true,
        ]);
    }

    private function pendingDocument(): Document
    {
        return Document::create([
            'registration_id' => $this->registration->id,
            'document_type_id' => $this->type->id,
            'status' => 'pending',
            'path' => 'documents/test/ijazah.pdf',
            'original_name' => 'ijazah.pdf',
        ]);
    }

    /** @test */
    public function approving_a_document_marks_it_valid_and_records_who_and_when(): void
    {
        $document = $this->pendingDocument();

        $this->actingAs($this->admin)
            ->from(route('admin.registrations.show', $this->student))
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'approve',
            ])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame('valid', $document->status);
        $this->assertSame($this->admin->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at, 'reviewed_at was not written.');
    }

    /** @test */
    public function approving_a_document_writes_a_verification_row(): void
    {
        // The trail is what lets an administrator answer "who approved this and
        // when" three months later. Without it the document row is the only
        // evidence, and it does not say who touched it.
        $document = $this->pendingDocument();

        $this->actingAs($this->admin)
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'approve',
            ]);

        $this->assertDatabaseHas('verifications', [
            'registration_id' => $this->registration->id,
            'admin_id' => $this->admin->id,
        ]);
    }

    /** @test */
    public function requesting_a_revision_requires_a_reason_and_writes_it(): void
    {
        $document = $this->pendingDocument();

        $this->actingAs($this->admin)
            ->from(route('admin.registrations.show', $this->student))
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'reject',
            ])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending', $document->refresh()->status, 'A rejected request must not have changed the status.');

        $this->actingAs($this->admin)
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'reject',
                'note' => 'Mohon unggah ulang ijazah yang lebih jelas.',
            ])
            ->assertRedirect();

        $document->refresh();

        $this->assertNotSame('pending', $document->status);
        $this->assertNotNull($document->rejection_reason);
    }

    /** @test */
    public function an_unknown_review_action_changes_nothing(): void
    {
        $document = $this->pendingDocument();

        $this->actingAs($this->admin)
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'archive-it',
            ])
            ->assertStatus(422);

        $this->assertSame('pending', $document->refresh()->status);
    }

    /** @test */
    public function a_student_cannot_review_their_own_document(): void
    {
        $document = $this->pendingDocument();

        $studentUser = User::factory()->create();
        $studentUser->assignRole('siswa');

        $this->actingAs($studentUser)
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'approve',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $document->refresh()->status);
    }

    /** @test */
    public function the_registration_stays_pending_while_a_document_is_outstanding(): void
    {
        // This is the connection that matters: document status drives the
        // registration's ability to be verified, so an admin who sees the
        // checklist move must be able to approve next.
        $document = $this->pendingDocument();

        $this->actingAs($this->admin)
            ->post(route('admin.documents.review', [$this->student, $document]), [
                'action' => 'approve',
            ]);

        $this->assertSame('valid', $document->refresh()->status);

        // Now nothing is outstanding, so approve should reach VERIFIED.
        $this->actingAs($this->admin)
            ->post(route('admin.decide', $this->student), ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame(
            Registration::STATUS_VERIFIED,
            $this->registration->refresh()->status,
            'The document was approved but the registration did not move.'
        );
    }
}