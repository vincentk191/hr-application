<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Position;
use App\Models\User;

/**
 * Who may administer job roles (CORE-ORG-002).
 */
class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewEmployees->value);
    }

    public function view(User $user, Position $position): bool
    {
        return $user->can(Permission::ViewEmployees->value)
            && $user->canAccessCompany($position->company_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOrganisation->value);
    }

    public function update(User $user, Position $position): bool
    {
        return $user->can(Permission::ManageOrganisation->value)
            && $user->canAccessCompany($position->company_id);
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->can(Permission::ManageOrganisation->value)
            && $user->canAccessCompany($position->company_id)
            && ! $position->employees()->exists();
    }
}
