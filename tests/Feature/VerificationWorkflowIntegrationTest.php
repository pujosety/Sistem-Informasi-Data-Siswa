<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedDocumentTypes();
        $this->artisan('sida:seed-verification-workflow')->assertExitCode(0);
    }

    public function test_document_rejection_is_appended_to_the_shared_workflow_ledger(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $registration = $student->registration;
        $registration->update(['academic_year_id' => $this->seedAcademicYear()->id, 'status' => Registration::STATUS_PENDING]);
        app(\App\Services\DocumentService::class)->seedPlaceholders($registration->refresh());

        $document = $registration->documents()->firstOrFail();
        $document->update(['status' => 'pending', 'path' => 'documents/test.pdf', 'original_name' => 'test.pdf']);
        $admin = $this->makeUser('admin');

        app(VerificationService::class)->rejectDocument(
            $registration,
            $document,
            $admin->id,
            'Dokumen tidak terbaca.'
        );

        $action = WorkflowAction::query()
            ->where('actor_id', $admin->id)
            ->where('action', WorkflowAction::REJECT)
            ->whereHas('instance', fn ($query) => $query
                ->where('entity_type', Document::class)
                ->where('entity_id', $document->id))
            ->first();

        $this->assertNotNull($action);
        $this->assertSame('Dokumen tidak terbaca.', $action->note);
    }
}
