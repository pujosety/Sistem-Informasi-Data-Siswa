<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decision, comment or handover in a workflow's history.
 *
 * This is the append-only record. Nothing here is ever updated — a revision
 * request that becomes an approval is two rows, not one row edited, because
 * "who asked, who decided, and when" is the only thing an approval trail is
 * actually for.
 */
class WorkflowAction extends Model
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public const REVISE = 'revise';

    public const DELEGATE = 'delegate';

    public const COMMENT = 'comment';

    public const CANCEL = 'cancel';

    /** Actions that end the workflow. */
    public const TERMINAL = [self::REJECT, self::CANCEL];

    protected $table = 'workflow_actions';

    protected $fillable = [
        'workflow_instance_id', 'step_order', 'actor_id', 'action',
        'note', 'delegated_from_id', 'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'step_order' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_id');
    }

    public function isDecision(): bool
    {
        return in_array($this->action, [self::APPROVE, self::REJECT, self::REVISE], true);
    }
}
