<?php

namespace App\Policies;

use App\Enums\LeaveRequestStage;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\ReportingLine;

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

        if (! $user->canAccessCompany($leaveRequest->company_id)) {
            return false;
        }

        $ids = app(ReportingLine::class)->visibleIds($user);

        return $ids === null
            || in_array($leaveRequest->employee_id, $ids, strict: true)
            || (bool) $leaveRequest->workflow?->steps()->where('approver_user_id', $user->id)->exists();
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
     * Whether this person may file for anyone but themselves at all: HR Admin and Management, and
     * nobody else (REQ-DSH-005, REQ-WIZ-002).
     */
    public function createOnBehalf(User $user): bool
    {
        return $user->can(Permission::CreateEmployees->value) || $user->isA(Role::Management);
    }

    /**
     * Whether this person may file for this particular employee: themselves always; HR Admin for
     * anyone in a company they can reach; Management for their own downline.
     */
    public function fileFor(User $user, Employee $employee): bool
    {
        if ($employee->user_id !== null && $employee->user_id === $user->id) {
            return true;
        }

        if ($user->can(Permission::CreateEmployees->value)) {
            return $user->canAccessCompany($employee->company_id);
        }

        if (! $user->isA(Role::Management)) {
            return false;
        }

        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        return $own !== null
            && in_array($employee->id, app(ReportingLine::class)->downlineIds($own), strict: true);
    }

    /**
     * Whether this person may file a request that starts in the past (REQ-WIZ-014). Everyone else
     * is held to the kind's "boleh mundur" days.
     */
    public function backdate(User $user): bool
    {
        return $this->createOnBehalf($user);
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

        return $user->can('fileFor', [LeaveRequest::class, $leaveRequest->employee])
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
