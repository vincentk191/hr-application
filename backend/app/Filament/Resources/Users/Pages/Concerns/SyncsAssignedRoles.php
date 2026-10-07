<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Models\User;

/**
 * Carries the role selection from the form to the model.
 *
 * The roles field is not bound to a relationship, so the selection is lifted out of the form
 * data before it reaches the model and applied with syncRoles() afterwards. That route fires
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
        $this->selectedRoles = array_key_exists('role_names', $data)
            ? array_values((array) $data['role_names'])
            : null;

        unset($data['role_names']);

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
