<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;

/**
 * Who may administer departments (CORE-ORG-001).
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewEmployees->value);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can(Permission::ViewEmployees->value)
            && $user->canAccessCompany($department->company_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOrganisation->value);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can(Permission::ManageOrganisation->value)
            && $user->canAccessCompany($department->company_id);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can(Permission::ManageOrganisation->value)
            && $user->canAccessCompany($department->company_id)
            && ! $department->employees()->exists();
    }
}
