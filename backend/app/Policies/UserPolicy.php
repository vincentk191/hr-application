<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Who may administer accounts and role assignments (CORE-USR).
 *
 * `User` is not company-tagged — a parent-wide account has no company at all — so these checks
 * cannot lean on CompanyScope the way the dossier policies do. The company comparison here is
 * the only thing keeping a company-pinned administrator out of another company's accounts.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return true;
        }

        return $user->can(Permission::ManageUsers->value)
            && $this->sharesScopeWith($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value)
            && $this->sharesScopeWith($user, $model);
    }

    /**
     * Assigning roles is separate from editing the account, because it is the action that can
     * escalate privilege (CORE-USR-003/004).
     */
    public function assignRoles(User $user, User $model): bool
    {
        // Nobody edits their own roles, including a System Admin — that is the one change
        // that could silently widen their own access with no second pair of eyes.
        if ($user->is($model)) {
            return false;
        }

        return $user->can(Permission::ManageUsers->value)
            && $this->sharesScopeWith($user, $model);
    }

    /**
     * Accounts are deactivated, not deleted: offboarding revokes access and the dossier keeps
     * the history (OFF-CHK-010).
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * A company-pinned administrator may only touch accounts in their own company, and never
     * a parent-wide account, which outranks their scope.
     */
    private function sharesScopeWith(User $user, User $model): bool
    {
        if ($user->isParentWide()) {
            return true;
        }

        if ($model->isParentWide()) {
            return false;
        }

        return $user->company_id === $model->company_id;
    }
}
