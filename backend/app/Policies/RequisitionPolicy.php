<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Recruitment\RequisitionApprovers;
use App\Support\Recruitment\RequisitionDepartments;

/**
 * Who may raise, see, change and decide a requisition (REC-*).
 *
 * A supervisor raises one for their own department, HR for any. Management approves it, then HR. What each
 * person may see is the `visibleTo` scope on the model, so a list and a single requisition never disagree.
 */
class RequisitionPolicy
{
    /**
     * Whether "Rekrutmen" is worth opening: HR, Management, anyone who may raise a requisition, and anyone who
     * raised one before.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageRecruitment->value)
            || $user->isA(Role::Management)
            || app(RequisitionDepartments::class)->for($user)->isNotEmpty()
            || Requisition::query()->where('created_by_user_id', $user->id)->exists();
    }

    public function view(User $user, Requisition $requisition): bool
    {
        return $user->canAccessCompany($requisition->company_id)
            && Requisition::query()->visibleTo($user)->whereKey($requisition->id)->exists();
    }

    public function create(User $user): bool
    {
        return app(RequisitionDepartments::class)->for($user)->isNotEmpty();
    }

    /**
     * The requester changes a draft, which is also where a rejected requisition comes back to (REC-WIZ-004). Once
     * sent it can still be corrected until someone has decided on it (REC-WIZ-005); after that a change would go
     * past an approval already given.
     */
    public function update(User $user, Requisition $requisition): bool
    {
        if ($requisition->created_by_user_id !== $user->id || ! $user->canAccessCompany($requisition->company_id)) {
            return false;
        }

        return match ($requisition->stage()) {
            RequisitionStage::Draft => true,
            RequisitionStage::Submitted => ! $requisition->workflow?->steps()->whereIn('status', ['approved', 'rejected'])->exists(),
            default => false,
        };
    }

    /**
     * A draft that was never sent can be thrown away by its requester.
     */
    public function delete(User $user, Requisition $requisition): bool
    {
        return $requisition->created_by_user_id === $user->id && $requisition->isDraft() && $requisition->submitted_at === null;
    }

    /**
     * Deciding the open step: its approver, never the requester, and the HR step only with `manage-recruitment`.
     */
    public function decide(User $user, Requisition $requisition): bool
    {
        $step = $this->openStep($requisition);

        if ($step === null || $requisition->created_by_user_id === $user->id || ! $user->canAccessCompany($requisition->company_id)) {
            return false;
        }

        if ($step->label === RequisitionApprovers::HR_LABEL && ! $user->can(Permission::ManageRecruitment->value)) {
            return false;
        }

        return $step->canBeDecidedBy($user);
    }

    /**
     * Saving the salary expectation without approving yet (REC-APR-007/008): only the Management approver, while
     * their step is the open one.
     */
    public function saveSalary(User $user, Requisition $requisition): bool
    {
        return $this->openStep($requisition)?->label === RequisitionApprovers::MANAGEMENT_LABEL && $this->decide($user, $requisition);
    }

    /**
     * "Lanjut ke Posting" is HR's (REC-APR-003); the guard on the stage keeps it shut until every approval is in.
     */
    public function advance(User $user, Requisition $requisition): bool
    {
        return $requisition->stage() === RequisitionStage::Submitted
            && $user->can(Permission::ManageRecruitment->value)
            && $user->canAccessCompany($requisition->company_id);
    }

    /**
     * The thread between the requester, Management and HR (REC-APR-009), from the first time it is sent.
     */
    public function comment(User $user, Requisition $requisition): bool
    {
        return $requisition->submitted_at !== null && $this->view($user, $requisition);
    }

    private function openStep(Requisition $requisition): ?WorkflowStep
    {
        if ($requisition->stage() !== RequisitionStage::Submitted) {
            return null;
        }

        $step = $requisition->workflow?->steps()->where('status', 'pending')->first();

        return $step instanceof WorkflowStep ? $step : null;
    }
}
