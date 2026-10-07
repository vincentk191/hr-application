<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A comment on an instance (CORE-WFL-007).
 *
 * The stage is captured at the time of writing, so a thread read back after the case has moved
 * on still shows which stage each remark was made in.
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property int $user_id
 * @property string|null $stage
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workflow_instance_id', 'user_id', 'stage', 'body'])]
class WorkflowComment extends Model
{
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
