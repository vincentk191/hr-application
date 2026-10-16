<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\OffboardingItem;
use App\Models\User;

/**
 * Who may manage the master list of offboarding items (OFF-TPL-002): those who manage offboarding.
 */
class OffboardingItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOffboarding->value);
    }

    public function view(User $user, OffboardingItem $item): bool
    {
        return $this->viewAny($user) && $user->canAccessCompany($item->company_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OffboardingItem $item): bool
    {
        return $this->view($user, $item);
    }

    public function delete(User $user, OffboardingItem $item): bool
    {
        return $this->view($user, $item);
    }
}
