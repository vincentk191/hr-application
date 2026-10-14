<?php

namespace App\Policies;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;

/**
 * Who may ask for, withdraw and decide a change to an employee's profile (EMP-CHG).
 *
 * Asking is for the employee about their own record, and nobody else. Deciding is HR's (the right to
 * update employees), never about one's own record: an HR Admin's own change needs a different one.
 */
class ProfileChangeRequestPolicy
{
    /**
     * The review list: those who may update employee records.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UpdateEmployees->value);
    }

    public function view(User $user, ProfileChangeRequest $request): bool
    {
        return $this->isOwner($user, $request)
            || ($user->can(Permission::UpdateEmployees->value) && $user->canAccessCompany($request->company_id));
    }

    /**
     * Ask for a change to this record: the person it belongs to, while they still work here.
     */
    public function createFor(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null
            && $employee->user_id === $user->id
            && $employee->employment_status === EmploymentStatus::Active;
    }

    public function withdraw(User $user, ProfileChangeRequest $request): bool
    {
        return $request->isPending() && $this->isOwner($user, $request);
    }

    public function decide(User $user, ProfileChangeRequest $request): bool
    {
        return $request->isPending()
            && $user->can(Permission::UpdateEmployees->value)
            && $user->canAccessCompany($request->company_id)
            && ! $this->isOwner($user, $request);
    }

    private function isOwner(User $user, ProfileChangeRequest $request): bool
    {
        $owner = Employee::acrossCompanies()->whereKey($request->employee_id)->value('user_id');

        return $owner !== null && $owner === $user->id;
    }
}
