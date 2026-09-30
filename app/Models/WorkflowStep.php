<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a workflow: who decides, and what they are allowed to say.
 *
 * A step names a ROLE or a PERMISSION, never a person. The engine resolves who
 * may act at the moment of the action, so an organisation change — the only
 * person holding `verification.approve` leaving, say — does not leave a queue
 * pinned to an id nobody can reach.
 */
class WorkflowStep extends Model
{
    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_definition_id', 'step_order', 'name',
        'approver_role', 'approver_permission',
        'can_approve', 'can_reject', 'can_request_revision', 'can_comment',
        'allow_delegate', 'due_after_hours', 'notes',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'can_approve' => 'boolean',
        'can_reject' => 'boolean',
        'can_request_revision' => 'boolean',
        'can_comment' => 'boolean',
        'allow_delegate' => 'boolean',
        'due_after_hours' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /**
     * Whether this step is bound to a capability at all.
     *
     * A step with neither a role nor a permission would be unanswerable — no
     * user could ever satisfy it — so the engine refuses to start such a
     * workflow rather than creating one that silently stalls.
     */
    public function hasApprover(): bool
    {
        return filled($this->approver_role) || filled($this->approver_permission);
    }

    public function allows(string $action): bool
    {
        return match ($action) {
            'approve' => $this->can_approve,
            'reject' => $this->can_reject,
            'revise' => $this->can_request_revision,
            'comment' => $this->can_comment,
            'delegate' => $this->allow_delegate,
            default => false,
        };
    }
}
