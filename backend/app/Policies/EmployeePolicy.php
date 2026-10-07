<?php

namespace App\Policies;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;

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
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewEmployees->value);
    }

    public function view(User $user, Employee $employee): bool
    {
        // An employee may always see their own record, without a view permission.
        if ($this->isSelf($user, $employee)) {
            return true;
        }

        return $user->can(Permission::ViewEmployees->value)
            && $user->canAccessCompany($employee->company_id);
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
