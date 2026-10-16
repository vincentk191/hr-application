<?php

namespace App\Policies;

use App\Enums\OffboardingCaseStage;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseItem;
use App\Models\OffboardingSignoff;
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
            || app(ReportingLine::class)->hasReports($user)
            || OffboardingCaseDepartment::acrossCompanies()->where('owner_user_id', $user->id)->exists();
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
        return $this->manages($user, $case) && ! $case->stage()->isFinal();
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

    /**
     * Doing the checklist work of one department: picking its items, confirming the list, ticking them.
     * That is the department's owner, or HR Admin when the department has no one to own it.
     */
    public function workGroup(User $user, OffboardingCaseDepartment $group): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($group->offboarding_case_id);

        if (! $case instanceof OffboardingCase || $case->stage() !== OffboardingCaseStage::Checklist || ! $this->view($user, $case)) {
            return false;
        }

        return $group->owner_user_id === null
            ? $this->manages($user, $case)
            : $group->owner_user_id === $user->id;
    }

    /**
     * HR may add an item for this case to any department, whoever owns it (OFF-CHK-006).
     */
    public function addItem(User $user, OffboardingCaseDepartment $group): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($group->offboarding_case_id);

        return $this->workGroup($user, $group)
            || ($case instanceof OffboardingCase && $case->stage() === OffboardingCaseStage::Checklist && $this->manages($user, $case));
    }

    /**
     * Setting a department aside as not involved is HR's call.
     */
    public function setAside(User $user, OffboardingCaseDepartment $group): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($group->offboarding_case_id);

        return $case instanceof OffboardingCase && $case->stage() === OffboardingCaseStage::Checklist && $this->manages($user, $case);
    }

    public function workItem(User $user, OffboardingCaseItem $item): bool
    {
        return $this->workGroup($user, $item->group);
    }

    /**
     * Taking an item off the list: its owner, or HR for anything added to the case.
     */
    public function removeItem(User $user, OffboardingCaseItem $item): bool
    {
        return $this->workGroup($user, $item->group) || $this->addItem($user, $item->group);
    }

    /**
     * "Lanjut ke sign-off" is HR's, once the checklist is complete.
     */
    public function proceed(User $user, OffboardingCase $case): bool
    {
        return $case->stage() === OffboardingCaseStage::Checklist && $this->manages($user, $case);
    }

    /**
     * Signing off, in turn: the person the signature is bound to, or any HR Admin for HR's own.
     */
    public function signOff(User $user, OffboardingSignoff $signoff): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($signoff->offboarding_case_id);

        if (! $case instanceof OffboardingCase || $case->stage() !== OffboardingCaseStage::SignOff || $signoff->isSigned()) {
            return false;
        }

        $leaver = Employee::acrossCompanies()->whereKey($case->employee_id)->value('user_id');

        if ($leaver !== null && $leaver === $user->id) {
            return false;
        }

        $earlierOpen = $case->signoffs()->where('position', '<', $signoff->position)->whereNull('signed_at')->exists();

        if ($earlierOpen) {
            return false;
        }

        return $signoff->user_id === null ? $this->manages($user, $case) : $signoff->user_id === $user->id;
    }

    /**
     * A signer, or HR, may send a case at sign-off back to the checklist.
     */
    public function sendBack(User $user, OffboardingCase $case): bool
    {
        if ($case->stage() !== OffboardingCaseStage::SignOff) {
            return false;
        }

        return $this->manages($user, $case)
            || $case->signoffs()->whereNull('signed_at')->where('user_id', $user->id)->exists();
    }

    /**
     * The exit interview is HR's, before HR signs.
     */
    public function recordExitInterview(User $user, OffboardingCase $case): bool
    {
        return $case->stage() === OffboardingCaseStage::SignOff
            && $this->manages($user, $case)
            && $case->signoffs()->whereNull('signed_at')->where('label', 'HR Admin')->exists();
    }

    /**
     * Changing the last working day, until the exit has been applied.
     */
    public function changeLastDay(User $user, OffboardingCase $case): bool
    {
        return in_array($case->stage(), [OffboardingCaseStage::Checklist, OffboardingCaseStage::SignOff], true)
            && ! $case->exitApplied()
            && $this->manages($user, $case);
    }

    /**
     * Cancelling a case, until the exit has been applied.
     */
    public function cancel(User $user, OffboardingCase $case): bool
    {
        return in_array($case->stage(), [OffboardingCaseStage::Submitted, OffboardingCaseStage::Checklist, OffboardingCaseStage::SignOff], true)
            && ! $case->exitApplied()
            && $this->manages($user, $case);
    }

    public function close(User $user, OffboardingCase $case): bool
    {
        return $case->stage() === OffboardingCaseStage::SignOff && $this->manages($user, $case);
    }

    public function reassign(User $user, OffboardingCase $case): bool
    {
        return in_array($case->stage(), [OffboardingCaseStage::Checklist, OffboardingCaseStage::SignOff], true) && $this->manages($user, $case);
    }

    /**
     * The handover report, from sign-off on, to anyone who may see the case.
     */
    public function beritaAcara(User $user, OffboardingCase $case): bool
    {
        return in_array($case->stage(), [OffboardingCaseStage::SignOff, OffboardingCaseStage::Closed], true) && $this->view($user, $case);
    }

    private function manages(User $user, OffboardingCase $case): bool
    {
        return $user->can(Permission::ManageOffboarding->value) && $user->canAccessCompany($case->company_id);
    }
}
