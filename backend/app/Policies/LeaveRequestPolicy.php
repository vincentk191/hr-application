<?php

namespace App\Policies;

use App\Enums\LeaveRequestStage;
use App\Enums\Permission;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkflowStep;

/**
 * Who may see and act on a leave request.
 *
 * Deciding a request is deliberately *not* a permission check: the engine already knows who
 * the open step belongs to, so this delegates to it rather than restating the rule and risking
 * the two disagreeing (CORE-WFL-002, CORE-RBA-005).
 */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can(Permission::ViewEmployees->value)
            && $user->canAccessCompany($leaveRequest->company_id);
    }

    /**
     * Anyone signed in may file leave. Filing it for *someone else* is the restricted part,
     * and that is checked separately.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Filing on behalf of another employee is an HR action (REQ-DSH-005).
     */
    public function createOnBehalf(User $user): bool
    {
        return $user->can(Permission::CreateEmployees->value);
    }

    /**
     * Correct a rejected request and send it round again. The requester may, and so may HR filing
     * for someone without a login; nobody may touch a request that is waiting or settled, because
     * changing it under an approver's feet would void what they are looking at.
     */
    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->workflow?->current_stage !== LeaveRequestStage::Rejected->value) {
            return false;
        }

        if ($leaveRequest->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can('createOnBehalf', LeaveRequest::class)
            && $user->canAccessCompany($leaveRequest->company_id);
    }

    /**
     * Deciding is owned by the workflow step, not by a role.
     */
    public function decide(User $user, LeaveRequest $leaveRequest): bool
    {
        $step = $leaveRequest->workflow?->steps()->where('status', 'pending')->first();

        return $step instanceof WorkflowStep && $step->canBeDecidedBy($user);
    }
}
