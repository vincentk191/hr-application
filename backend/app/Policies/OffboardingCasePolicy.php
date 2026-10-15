<?php

namespace App\Policies;

use App\Enums\OffboardingCaseStage;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\ReportingLine;

/**
 * Who may start, see, change and decide an offboarding case (OFF-*).
 *
 * HR starts and manages cases and Management approves them. A supervisor sees the cases of people
 * below them, and the person leaving sees their own once the checklist stage begins. What each of
 * those people may see is the `visibleTo` scope on the case, so a list and a single case never
 * disagree.
 */
class OffboardingCasePolicy
{
    /**
     * Whether the offboarding list is worth opening: HR, Management, and anyone with people under them.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOffboarding->value)
            || $user->isA(Role::Management)
            || app(ReportingLine::class)->hasReports($user);
    }

    public function view(User $user, OffboardingCase $case): bool
    {
        return $user->canAccessCompany($case->company_id)
            && OffboardingCase::query()->visibleTo($user)->whereKey($case->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOffboarding->value);
    }

    /**
     * A case is edited while it is a draft; a rejected case returns to Draft, so it can be corrected.
     */
    public function update(User $user, OffboardingCase $case): bool
    {
        return $this->manages($user, $case) && $case->isDraft();
    }

    public function delete(User $user, OffboardingCase $case): bool
    {
        return $this->update($user, $case);
    }

    /**
     * Adding files to a case, until it is closed (OFF-PIP-006).
     */
    public function addFiles(User $user, OffboardingCase $case): bool
    {
        return $this->manages($user, $case) && $case->stage() !== OffboardingCaseStage::Closed;
    }

    /**
     * Management approves or rejects, and only a case that is waiting for it. Never the person
     * leaving, and never HR's own case.
     */
    public function decide(User $user, OffboardingCase $case): bool
    {
        if ($case->stage() !== OffboardingCaseStage::Submitted || ! $user->canAccessCompany($case->company_id)) {
            return false;
        }

        $leaver = Employee::acrossCompanies()->whereKey($case->employee_id)->value('user_id');

        if ($leaver !== null && $leaver === $user->id) {
            return false;
        }

        $step = $case->workflow?->steps()->where('status', 'pending')->first();

        return $step instanceof WorkflowStep && $step->canBeDecidedBy($user);
    }

    /**
     * The comment thread, from the moment a case is sent until it is closed and after: HR, Management
     * and the supervisors who can see the case may all write in it. The person leaving reads the
     * checklist but takes no part in the discussion about their own case.
     */
    public function comment(User $user, OffboardingCase $case): bool
    {
        // A draft that was never sent has no thread; one Management sent back does, so HR can answer.
        if (($case->isDraft() && $case->submitted_at === null) || ! $this->view($user, $case)) {
            return false;
        }

        $leaver = Employee::acrossCompanies()->whereKey($case->employee_id)->value('user_id');

        return $leaver === null || $leaver !== $user->id;
    }

    private function manages(User $user, OffboardingCase $case): bool
    {
        return $user->can(Permission::ManageOffboarding->value) && $user->canAccessCompany($case->company_id);
    }
}
