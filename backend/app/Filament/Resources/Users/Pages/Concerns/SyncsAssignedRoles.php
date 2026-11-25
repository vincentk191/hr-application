<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Enums\Role;
use App\Models\User;

/**
 * Carries the role selection from the form to the model.
 *
 * The role field is a single choice and not bound to a relationship, so the selection is lifted out
 * of the form data before it reaches the model and applied with syncRoles() afterwards. That route fires
 * the events LogRoleChange listens to; a relationship save would change roles silently. The
 * UserPolicy::assignRoles check is repeated here because a disabled field is a UI courtesy,
 * not a guarantee.
 */
trait SyncsAssignedRoles
{
    /**
     * @var list<string>|null
     */
    protected ?array $selectedRoles = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractSelectedRoles(array $data): array
    {
        $this->selectedRoles = filled($data['role_name'] ?? null)
            ? [(string) $data['role_name']]
            : null;

        unset($data['role_name']);

        // A disabled company field is not sent, and the form is only a courtesy: the rule is applied here.
        if ($this->selectedRoles !== null && Role::tryFrom($this->selectedRoles[0])?->isGroupWide() === true) {
            $data['company_id'] = null;
        }

        return $data;
    }

    protected function applySelectedRoles(User $user): void
    {
        if ($this->selectedRoles === null || auth()->user()?->can('assignRoles', $user) !== true) {
            return;
        }

        $user->syncRoles($this->selectedRoles);
    }
}
