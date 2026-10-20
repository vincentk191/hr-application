<?php

namespace App\Policies;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;
use App\Support\ReportingLine;

/**
 * Who may see and change a dossier record (CORE-RBA-003, EMP-ADD).
 *
 * Company separation is already enforced at the query level by CompanyScope, so a record from
 * another company normally never reaches a policy at all. The company checks here are
 * deliberate belt-and-braces: a model fetched through the `acrossCompanies()` bypass, or
 * resolved in a console context where no company is active, still has to pass this.
 */
class EmployeePolicy
{
    /**
     * The directory opens for a role that may view employees, and for anyone with people
     * reporting to them: the reporting line decides, not the role (CORE-RBA-004).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewEmployees->value)
            || app(ReportingLine::class)->hasReports($user);
    }

    /**
     * A record is visible to its owner, to a holder of the company-wide permission, and to the
     * people above it in the reporting line; never to a colleague on the same level.
     */
    public function view(User $user, Employee $employee): bool
    {
        if ($this->isSelf($user, $employee)) {
            return true;
        }

        if (! $user->canAccessCompany($employee->company_id)) {
            return false;
        }

        if ($user->can(Permission::ViewAllEmployees->value)) {
            return true;
        }

        return in_array($employee->id, app(ReportingLine::class)->visibleIds($user) ?? [], strict: true);
    }

    /**
     * The attendance tab: the employee's own, or anyone allowed to see the calendar for a company
     * they can reach.
     */
    public function viewAttendance(User $user, Employee $employee): bool
    {
        if ($this->isSelf($user, $employee)) {
            return true;
        }

        return $user->can(Permission::ViewAttendance->value)
            && $user->canAccessCompany($employee->company_id);
    }

    /**
     * Adding, changing and voiding punches. Deliberately separate from editing the profile, and
     * allowed on a former employee too: their last days sometimes need fixing after they leave.
     */
    public function correctAttendance(User $user, Employee $employee): bool
    {
        return $user->can(Permission::CorrectAttendance->value)
            && $user->canAccessCompany($employee->company_id);
    }

    /**
     * HR activates (or rejects) an account the employee registered for themselves (CORE-ACC-006).
     */
    public function activateAccount(User $user, Employee $employee): bool
    {
        return $user->can(Permission::CreateEmployees->value) && $user->canAccessCompany($employee->company_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateEmployees->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        // A closed offboarding locks the profile from editing (OFF-SIG-009, EMP-ADD-006).
        if ($employee->employment_status === EmploymentStatus::Inactive) {
            return false;
        }

        // Nobody edits their own record directly, HR included: it goes through a change request
        // that a different HR Admin approves (EMP-CHG-005).
        if ($this->isSelf($user, $employee)) {
            return false;
        }

        return $user->can(Permission::UpdateEmployees->value)
            && $user->canAccessCompany($employee->company_id);
    }

    /**
     * Dossier records are never deleted. Offboarding flips the record to inactive and locks
     * it (OFF-SIG-009), because attendance, leave and case history reference it.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return false;
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }

    private function isSelf(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }
}
