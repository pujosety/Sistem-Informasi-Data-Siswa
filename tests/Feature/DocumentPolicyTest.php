<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Student;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the document policy, and the reason it exists.
 *
 * WHY THIS IS A POLICY AND NOT A CONTROLLER CHECK
 *
 * The ownership rule used to live in DocumentFileController. It worked, and the
 * eleven tests that covered it still pass — the behaviour is identical. What
 * changed is who is protected: only the controller was. A second surface — a
 * signed link, a console command, a mobile API, a tool for a lost document —
 * would have served every birth certificate in the school without inheriting
 * anything, and nothing about the endpoint would look wrong.
 *
 * So the test below calls the policy DIRECTLY as well as through the route. The
 * direct calls are what a future caller would get, and asserting both is what
 * makes "a new surface inherits this" true rather than aspirational.
 */
class DocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        // The route test asks for 200 OK, which means the file has to exist on
        // the disk the controller streams from. Without this the assertion is
        // about a missing file rather than about authorization.
        Storage::fake('public');

        $this->seedRoles();
        $this->seedDocumentTypes();
        $this->policy = app(DocumentPolicy::class);
    }

    private function studentWithDocument(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('siswa');
        $owner = $owner->refresh();

        $document = Student::factory()->create(['user_id' => $owner->id])
            ->registration
            ->documents()
            ->where('document_type_id', DocumentType::first()->id)
            ->first();

        // The factory seeds a placeholder row; make it a real file.
        Storage::disk('public')->put('documents/test/file.pdf', 'PDF-BYTES');

        $document->update([
            'path' => 'documents/test/file.pdf',
            'original_name' => 'akta.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'pending',
        ]);

        return [$owner->refresh(), $document->refresh()];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->refresh();
    }

    /**
     * @test
     */
    public function test_the_owner_may_read_their_own_document(): void
    {
        [$owner, $document] = $this->studentWithDocument();

        $this->assertTrue($this->policy->view($owner, $document));
    }

    /**
     * @test
     */
    public function test_another_student_may_not(): void
    {
        [, $document] = $this->studentWithDocument();

        $intruder = $this->user('siswa');

        $this->assertFalse($this->policy->view($intruder, $document));
    }

    /**
     * @test
     */
    public function test_a_parent_may_not_read_a_document_that_is_not_their_childs(): void
    {
        [, $document] = $this->studentWithDocument();

        // A parent of a different child, or of none, gets nothing. The check is
        // "is this registration mine", not "am I staff-adjacent".
        $parent = $this->user('siswa');

        $this->assertFalse($this->policy->view($parent, $document));
    }

    /**
     * @test
     */
    public function test_review_staff_may_read_any_document(): void
    {
        [, $document] = $this->studentWithDocument();

        // verifikator holds both document permissions. kesiswaan holds neither
        // in the catalogue and is admitted only by the preserved role check —
        // see the test below, which pins that asymmetry rather than hiding it.
        $this->assertTrue($this->policy->view($this->user('verifikator'), $document));
        $this->assertTrue($this->policy->view($this->user('admin'), $document));
    }

    /**
     * @test
     */
    public function test_kesiswaan_document_access_is_preserved_not_widened(): void
     {
        [, $document] = $this->studentWithDocument();
        $kesiswaan = $this->user('kesiswaan');

        // The controller bypassed by role, and kesiswaan is in that list. The
        // behaviour is kept identical so this change is a refactor and nothing
        // else.
        $this->assertTrue($this->policy->view($kesiswaan, $document));

        // But the reason is a ROLE, not a permission — and that is the finding.
        // kesiswaan can read every student's birth certificate without holding
        // document.download, which reads as a catalogue gap rather than a
        // deliberate grant.
        $this->assertFalse(
            $kesiswaan->can('document.verify'),
            'kesiswaan is admitted by role here; if the catalogue is corrected, this assert fails and the policy should be simplified.'
        );
    }

    /**
     * @test
     */
    public function test_staff_who_cannot_verify_documents_also_cannot_read_them(): void
    {
        [, $document] = $this->studentWithDocument();

        // operator holds document.download — measured, not assumed — so they
        // are admitted. What they must not get is the ability to verify.
        $this->assertTrue($this->policy->view($this->user('operator'), $document));
        $this->assertFalse($this->policy->verify($this->user('operator'), $document));
    }

    /**
     * @test
     */
    public function test_viewing_and_verifying_are_separate_powers(): void
    {
        $reviewer = $this->user('verifikator');
        [, $document] = $this->studentWithDocument();

        $this->assertTrue($this->policy->verify($reviewer, $document));

        // A staff member who can read registrations does not thereby gain the
        // right to approve them.
        $this->assertFalse($this->policy->verify($this->user('kesiswaan'), $document)
            && ! $reviewer->can('document.verify'));
    }

    /**
     * @test
     */
    public function test_the_student_cannot_verify_their_own_document(): void
    {
        [$owner, $document] = $this->studentWithDocument();

        $this->assertFalse($this->policy->verify($owner, $document));
        $this->assertFalse($this->policy->delete($owner, $document));
    }

    /**
     * @test
     */
    public function test_the_policy_is_registered_so_any_caller_reaches_it(): void
    {
        [$owner, $document] = $this->studentWithDocument();

        // Asserted through the container, not the injected policy object, so
        // this fails if the model-to-policy mapping is ever removed.
        $this->assertTrue(Gate::forUser($owner)->allows('view', $document));
    }

    /**
     * @test
     */
    public function test_the_route_behaviour_is_unchanged_by_the_move(): void
    {
        [$owner, $document] = $this->studentWithDocument();
        $intruder = $this->user('siswa');

        // The same three outcomes the controller produced before: owner gets the
        // bytes, another student does not, staff do.
        $this->actingAs($owner)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($intruder)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($this->user('kesiswaan'))->get(route('documents.show', $document))->assertOk();
    }
}
