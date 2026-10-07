<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;

/**
 * Who may administer the company list (CORE-ADM-001).
 *
 * Adding or removing a company needs Management's approval before the System Admin acts; that
 * approval is a process step recorded on the case, not something this policy can see, so the
 * policy only answers "may this account perform the action at all".
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        // Every signed-in account sees the companies it may switch to.
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->canAccessCompany($company);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCompanies->value);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can(Permission::ManageCompanies->value);
    }

    /**
     * Companies are deactivated rather than deleted — every tagged record would otherwise
     * lose its company.
     */
    public function delete(User $user, Company $company): bool
    {
        return false;
    }
}
