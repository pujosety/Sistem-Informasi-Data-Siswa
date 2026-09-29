<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One approval in progress, or one that finished.
 *
 * The subject is entity_type + entity_id rather than a polymorphic foreign
 * key: a workflow must survive the disappearance of the row it governs, or a
 * rejected leave request would lose the record of who rejected it.
 */
class WorkflowInstance extends Model
{
    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /** Statuses from which no further action is possible. */
    public const CLOSED = [self::APPROVED, self::REJECTED, self::CANCELLED];

    protected $table = 'workflow_instances';

    protected $fillable = [
        'workflow_definition_id', 'entity_type', 'entity_id', 'status',
        'current_step', 'started_by', 'started_at', 'completed_at', 'outcome_note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_step' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('acted_at');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }

    public function currentStepDefinition(): ?WorkflowStep
    {
        return $this->definition
            ->steps()
            ->where('step_order', $this->current_step)
            ->first();
    }

    /**
     * Whether this user may act on the current step.
     *
     * The step names a ROLE or a PERMISSION; both are satisfied through
     * Spatie so a school that redefines a role changes the answer here
     * automatically, with no workflow data to update.
     */
    public function userCanAct(User $user, string $action): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        $step = $this->currentStepDefinition();

        if (! $step || ! $step->allows($action)) {
            return false;
        }

        if (filled($step->approver_permission) && ! $user->can($step->approver_permission)) {
            return false;
        }

        if (filled($step->approver_role) && ! $user->hasRole($step->approver_role)) {
            return false;
        }

        return true;
    }
}
