<?php

namespace App\Services;

use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one approval engine, so §39's leave requests, student letters, data
 * corrections, CMS publishing, procurement, mutations and document approval
 * share a single implementation instead of growing seven.
 *
 * The rules it enforces, in order of how badly they break if missed:
 *
 *   1. Only a user who satisfies the CURRENT step's role or permission may act.
 *      Checked here, not in the controller, so a new caller cannot forget.
 *   2. A closed workflow accepts nothing. Approving something already rejected
 *      would rewrite history.
 *   3. Every decision is an appended row. Nothing is ever updated.
 *   4. A step advances only on approval. A revision request goes BACK to the
 *      first step, because a resubmitted document has to be looked at again by
 *      whoever checks documents — which is the behaviour VerificationService
 *      already has, and is the whole reason this engine is safe to point at it.
 *   5. Approval is only reached when the LAST step approves. A two-step
 *      workflow approved at step one is the bug this guards.
 *
 * §39's engine is not wired to VerificationService yet. That refactor is
 * deliberately separate and should be done on its own, with the full suite
 * green either side — it is the riskiest item in the programme, per
 * MIGRATION_PLAN.md.
 */
class WorkflowService
{
    /**
     * Start a workflow for a subject.
     *
     * Refuses rather than starting a workflow that could never finish: a
     * definition with no steps, or whose first step names nobody, produces a
     * queue nobody can answer, and that is discovered weeks later when a
     * request simply sits there.
     */
    public function start(
        string $definitionKey,
        Model $subject,
        ?User $actor = null,
    ): WorkflowInstance {
        $definition = WorkflowDefinition::query()
            ->where('key', $definitionKey)
            ->where('is_active', true)
            ->first();

        if (! $definition) {
            throw new \InvalidArgumentException("Unknown workflow definition [{$definitionKey}].");
        }

        $first = $definition->steps()->where('step_order', 1)->first();

        if (! $first) {
            throw new \LogicException(
                "Workflow [{$definitionKey}] has no step 1 and could never be completed."
            );
        }

        if (! $first->hasApprover()) {
            throw new \LogicException(
                "Workflow [{$definitionKey}] step 1 names no role and no permission, "
                .'so no user could ever act on it.'
            );
        }

        // One live workflow per subject. A second while the first is open is
        // almost always a double-submit, and two open workflows for one record
        // make "what is the status" unanswerable.
        $existing = WorkflowInstance::query()
            ->where('entity_type', $subject::class)
            ->where('entity_id', $subject->getKey())
            ->whereNotIn('status', WorkflowInstance::CLOSED)
            ->first();

        if ($existing) {
            return $existing;
        }

        return WorkflowInstance::create([
            'workflow_definition_id' => $definition->id,
            'entity_type' => $subject::class,
            'entity_id' => $subject->getKey(),
            'status' => WorkflowInstance::PENDING,
            'current_step' => 1,
            'started_by' => $actor?->id,
            'started_at' => now(),
        ]);
    }

    /**
     * Record a decision and advance the workflow.
     *
     * Every return path is a new action row; the instance is only ever moved
     * between statuses, never rewritten in place.
     */
    public function act(
        WorkflowInstance $instance,
        User $actor,
        string $action,
        ?string $note = null,
        ?User $delegatedFrom = null,
    ): WorkflowInstance {
        if ($instance->isClosed()) {
            throw new \LogicException(
                "Workflow #{$instance->id} is already {$instance->status} and cannot be acted on."
            );
        }

        if (! $instance->userCanAct($actor, $action)) {
            throw new \LogicException(
                "User #{$actor->id} may not {$action} step {$instance->current_step} "
                .'of this workflow.'
            );
        }

        return DB::transaction(function () use ($instance, $actor, $action, $note, $delegatedFrom) {
            WorkflowAction::create([
                'workflow_instance_id' => $instance->id,
                'step_order' => $instance->current_step,
                'actor_id' => $actor->id,
                'action' => $action,
                'note' => $note,
                'delegated_from_id' => $delegatedFrom?->id,
                'acted_at' => now(),
            ]);

            return match ($action) {
                WorkflowAction::APPROVE => $this->advance($instance),
                WorkflowAction::REJECT => $this->close($instance, WorkflowInstance::REJECTED, $note),
                WorkflowAction::CANCEL => $this->close($instance, WorkflowInstance::CANCELLED, $note),
                // A revision request sends the subject BACK to the first step
                // rather than stalling. Whoever checks documents must see the
                // resubmitted one; this is the behaviour VerificationService
                // already implements, and matching it is what makes the later
                // refactor safe.
                WorkflowAction::REVISE => $this->reopen($instance, $note),
                // A comment or a delegation changes nothing about the state.
                default => $instance->refresh(),
            };
        });
    }

    /**
     * Move to the next step, or finish if this was the last one.
     */
    private function advance(WorkflowInstance $instance): WorkflowInstance
    {
        $next = $instance->definition
            ->steps()
            ->where('step_order', '>', $instance->current_step)
            ->orderBy('step_order')
            ->first();

        if (! $next) {
            return $this->close($instance, WorkflowInstance::APPROVED, null);
        }

        $instance->update([
            'current_step' => $next->step_order,
            'status' => WorkflowInstance::IN_PROGRESS,
        ]);

        return $instance->refresh();
    }

    private function close(WorkflowInstance $instance, string $status, ?string $note): WorkflowInstance
    {
        $instance->update([
            'status' => $status,
            'completed_at' => now(),
            'outcome_note' => $note,
        ]);

        return $instance->refresh();
    }

    private function reopen(WorkflowInstance $instance, ?string $note): WorkflowInstance
    {
        $instance->update([
            'status' => WorkflowInstance::IN_PROGRESS,
            'current_step' => 1,
            'completed_at' => null,
            'outcome_note' => $note,
        ]);

        return $instance->refresh();
    }

    /**
     * Whether this user may act at all on the current step, for any action.
     */
    public function canAct(WorkflowInstance $instance, User $user): bool
    {
        return $instance->userCanAct($user, WorkflowAction::APPROVE)
            || $instance->userCanAct($user, WorkflowAction::REJECT)
            || $instance->userCanAct($user, WorkflowAction::REVISE);
    }

    /**
     * Everything a queue screen needs: who may act, and which step they are on.
     *
     * Returned rather than rendered, so the queue view does not re-derive the
     * authorisation rules and risk disagreeing with the engine.
     */
    public function pendingFor(?User $user = null): array
    {
        return WorkflowInstance::query()
            ->whereNotIn('status', WorkflowInstance::CLOSED)
            ->with(['definition', 'actions', 'starter'])
            ->get()
            ->map(function (WorkflowInstance $instance) use ($user) {
                return [
                    'instance' => $instance,
                    'step' => $instance->currentStepDefinition(),
                    'can_act' => $user ? $this->canAct($instance, $user) : false,
                ];
            })
            ->values()
            ->all();
    }
}
