<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded stage change (CORE-WFL-005/006).
 *
 * Append-only, and `updated_at` is deliberately absent: a transition is a historical fact, so
 * there is nothing to update.
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property string|null $from_stage
 * @property string $to_stage
 * @property int|null $actor_user_id
 * @property Carbon $created_at
 */
#[Fillable(['workflow_instance_id', 'from_stage', 'to_stage', 'actor_user_id'])]
class WorkflowTransition extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
