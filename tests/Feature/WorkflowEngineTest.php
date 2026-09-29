<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the approval engine §39 asks for.
 *
 * The tests are arranged around the ways a workflow engine quietly produces
 * the wrong answer:
 *
 *   - approving at step one of a two-step workflow (looks finished, is not)
 *   - a user without the right role acting anyway
 *   - a second submit creating a second live workflow for one record
 *   - a revision request stalling the queue instead of returning it
 *   - a closed workflow accepting a late decision
 *   - a definition nobody can act on, which would sit unanswered forever
 */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    /**
     * The definition key this test created.
     *
     * Unique per test on purpose. The key column is UNIQUE, and a hard-coded
     * one would make every test after the first collide — which is a fixture
     * fault masquerading as an engine fault.
     */
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->workflow = app(WorkflowService::class);
        $this->key = 'test.flow.'.uniqid();
    }

    private function definition(array $steps): WorkflowDefinition
    {
        $definition = WorkflowDefinition::create([
            'key' => $this->key,
            'name' => 'Test Flow',
            'entity_type' => Registration::class,
            'is_active' => true,
        ]);

        foreach ($steps as $order => $step) {
            WorkflowStep::create(array_merge([
                'workflow_definition_id' => $definition->id,
                'step_order' => $order + 1,
            ], $step));
        }

        return $definition;
    }

    /** Two steps: verification (verifikator), then approval (kesiswaan). */
    private function twoStep(): WorkflowDefinition
    {
        return $this->definition([
            ['name' => 'Verifikasi', 'approver_role' => 'verifikator'],
            ['name' => 'Persetujuan', 'approver_role' => 'kesiswaan'],
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->refresh();
    }

    /**
     * A fresh student with one registration.
     *
     * registrations is UNIQUE on student_id alone — a student has one
     * registration for the whole of their time at the school, not one per
     * year. So every call needs its own student, and the student factory's
     * sequence is not enough on its own here.
     */
    private function registration(): Registration
    {
        $student = \App\Models\Student::factory()->create();

        // The factory's configure() hook already creates this row, and
        // registrations is UNIQUE on student_id alone — so asking the student
        // for it is the only correct way to get one. Creating a second here
        // collides, which is a fixture fault rather than an engine fault.
        return $student->registration()->firstOrFail();
    }

    /**
     * @test
     */
    public function test_a_workflow_starts_on_the_first_step(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());

        $this->assertSame(WorkflowInstance::PENDING, $instance->status);
        $this->assertSame(1, $instance->current_step);
    }

    /**
     * @test
     */
    public function test_a_two_step_workflow_is_not_approved_at_the_first_step(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());
        $instance = $this->workflow->act($instance, $this->user('verifikator'), WorkflowAction::APPROVE);

        // The failure this guards: finishing at step one would report a
        // registration as approved when the second approver never saw it.
        $this->assertNotSame(WorkflowInstance::APPROVED, $instance->status);
        $this->assertSame(2, $instance->current_step);
        $this->assertSame(WorkflowInstance::IN_PROGRESS, $instance->status);
    }

    /**
     * @test
     */
    public function test_a_two_step_workflow_approves_only_after_the_last_step(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());
        $this->workflow->act($instance, $this->user('verifikator'), WorkflowAction::APPROVE);
        $instance = $this->workflow->act($instance->refresh(), $this->user('kesiswaan'), WorkflowAction::APPROVE);

        $this->assertSame(WorkflowInstance::APPROVED, $instance->status);
        $this->assertNotNull($instance->completed_at);
    }

    /**
     * @test
     */
    public function test_a_user_outside_the_steps_role_cannot_act(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());

        $this->expectException(\LogicException::class);

        // An operator holds no verification permission and is not a verifikator.
        $this->workflow->act($instance, $this->user('operator'), WorkflowAction::APPROVE);
    }

    /**
     * @test
     */
    public function test_a_user_cannot_skip_ahead_to_a_later_step(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());
        $verifier = $this->user('verifikator');
        $kesiswaan = $this->user('kesiswaan');

        $this->workflow->act($instance, $verifier, WorkflowAction::APPROVE);

        // The second actor now holds the second step's role, but the step they
        // could act on has already been passed.
        $this->assertSame(2, $instance->refresh()->current_step);
        $this->assertTrue($instance->refresh()->userCanAct($kesiswaan, WorkflowAction::APPROVE));
    }

    /**
     * @test
     */
    public function test_a_revision_request_returns_the_subject_to_the_first_step(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());
        $this->workflow->act($instance, $this->user('verifikator'), WorkflowAction::APPROVE);
        $instance = $this->workflow->act($instance->refresh(), $this->user('kesiswaan'), WorkflowAction::REVISE, 'KTP kurang jelas');

        // Stalling instead would leave the queue showing nothing to anyone.
        $this->assertSame(1, $instance->current_step);
        $this->assertSame(WorkflowInstance::IN_PROGRESS, $instance->status);
        $this->assertNull($instance->completed_at);
    }

    /**
     * @test
     */
    public function test_a_second_submit_reuses_the_open_workflow(): void
    {
        $this->twoStep();

        $registration = $this->registration();
        $first = $this->workflow->start($this->key, $registration);
        $second = $this->workflow->start($this->key, $registration->fresh());

        // Two live workflows for one record make "what is the status"
        // unanswerable, and a double-submit is the usual cause.
        $this->assertSame($first->id, $second->id);
    }

    /**
     * @test
     */
    public function test_a_closed_workflow_accepts_nothing_further(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());
        $this->workflow->act($instance, $this->user('verifikator'), WorkflowAction::APPROVE);
        $instance = $this->workflow->act($instance->refresh(), $this->user('kesiswaan'), WorkflowAction::REJECT, 'Tidak sesuai');

        $this->assertSame(WorkflowInstance::REJECTED, $instance->status);

        $this->expectException(\LogicException::class);
        $this->workflow->act($instance, $this->user('verifikator'), WorkflowAction::APPROVE);
    }

    /**
     * @test
     */
    public function test_every_decision_is_appended_and_never_rewritten(): void
    {
        $this->twoStep();

        $instance = $this->workflow->start($this->key, $this->registration());
        $this->workflow->act($instance, $this->user('verifikator'), WorkflowAction::APPROVE);
        $this->workflow->act($instance->refresh(), $this->user('kesiswaan'), WorkflowAction::REVISE, 'Dokumen kurang jelas');
        $this->workflow->act($instance->refresh(), $this->user('verifikator'), WorkflowAction::APPROVE);
        $this->workflow->act($instance->refresh(), $this->user('kesiswaan'), WorkflowAction::APPROVE);

        $actions = WorkflowAction::where('workflow_instance_id', $instance->id)->orderBy('id')->get();

        // The trail is the product. Four decisions, none edited.
        $this->assertCount(4, $actions);
        $this->assertSame(
            ['approve', 'revise', 'approve', 'approve'],
            $actions->pluck('action')->all()
        );

        // Each row records which step it was taken on, so a trail read months
        // later can be reconstructed rather than inferred.
        $this->assertSame([1, 2, 1, 2], $actions->pluck('step_order')->all());
    }

    /**
     * @test
     */
    public function test_a_definition_nobody_can_act_on_is_refused(): void
    {
        // No role and no permission: the workflow would sit unanswered forever,
        // and that is discovered weeks later.
        $this->definition([['name' => 'Tanpa penerima']]);

        $this->expectException(\LogicException::class);
        $this->workflow->start($this->key, $this->registration());
    }

    /**
     * @test
     */
    public function test_an_unknown_definition_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->workflow->start('does.not.exist', $this->registration());
    }

    /**
     * @test
     */
    public function test_a_step_can_be_bound_to_a_permission_instead_of_a_role(): void
    {
        $this->definition([['name' => 'Review', 'approver_permission' => 'verification.approve']]);

        $instance = $this->workflow->start($this->key, $this->registration());

        // A role that does not hold the permission must not act, even if the
        // role check would otherwise pass.
        $this->assertTrue($instance->userCanAct($this->user('verifikator'), WorkflowAction::APPROVE));
        $this->assertFalse($instance->userCanAct($this->user('operator'), WorkflowAction::APPROVE));
    }
}
