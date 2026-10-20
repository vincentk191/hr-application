<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\WorkflowStepStatus;
use App\Support\Access\Entrance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of an approval chain (CORE-WFL-002).
 *
 * A step carries both the role it needs and the person that role resolved to. The role is the
 * requirement; the resolved user is who is accountable now. `canBeDecidedBy()` is the single
 * place that answers "may this person act", so the queue, the policy and the engine cannot
 * disagree about it.
 *
 * @property int $id
 * @property int $company_id
 * @property int $workflow_instance_id
 * @property int $position
 * @property string $label
 * @property string|null $approver_role
 * @property int|null $approver_user_id
 * @property WorkflowStepStatus $status
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'workflow_instance_id',
    'position',
    'label',
    'approver_role',
    'approver_user_id',
    'status',
    'decided_by_user_id',
    'decided_at',
    'note',
])]
class WorkflowStep extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowStepStatus::class,
            'decided_at' => 'datetime',
        ];
    }

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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * Whether this step is the one currently waiting for a decision.
     */
    public function isOpen(): bool
    {
        return $this->status === WorkflowStepStatus::Pending;
    }

    /**
     * Whether the given user may decide this step.
     *
     * A resolved approver is binding: once a step names a person, only that person decides it,
     * which is what CORE-RBA-005 ("only an item's owner may tick it") needs. Where no person
     * has been resolved, holding the required role is enough.
     */
    public function canBeDecidedBy(User $user): bool
    {
        // Deciding is an elevated act: it needs the private entrance (CORE-ACC-004).
        if (! $this->isOpen() || app(Entrance::class)->isPublic()) {
            return false;
        }

        if ($this->approver_user_id !== null) {
            return $this->approver_user_id === $user->id;
        }

        if ($this->approver_role !== null) {
            return $user->hasRole($this->approver_role);
        }

        return false;
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', WorkflowStepStatus::Pending);
    }

    /**
     * Steps waiting on a given person, for the queue and the nav badge (CORE-WFL-008).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function waitingOn(Builder $query, User $user): void
    {
        if (app(Entrance::class)->isPublic()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', WorkflowStepStatus::Pending)
            ->where(function (Builder $query) use ($user): void {
                $query->where('approver_user_id', $user->id)
                    ->orWhere(function (Builder $query) use ($user): void {
                        $query->whereNull('approver_user_id')
                            ->whereIn('approver_role', $user->getRoleNames());
                    });
            });
    }
}
