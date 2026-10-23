<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

/**
 * Who may administer a company's Cabang. Unlike departments, which HR Admin keeps, the offices a
 * company has are set by the System Admin, together with the company itself.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCompanies->value);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageCompanies->value)
            && $user->canAccessCompany($branch->company_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCompanies->value);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageCompanies->value)
            && $user->canAccessCompany($branch->company_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageCompanies->value)
            && $user->canAccessCompany($branch->company_id)
            && ! $branch->employees()->exists();
    }
}
