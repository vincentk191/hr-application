<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\Concerns\SyncsAssignedRoles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SyncsAssignedRoles;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * Whether this save set a new password, which makes it a reset.
     */
    private bool $passwordWasSet = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->passwordWasSet = filled($data['password'] ?? null);

        return $this->extractSelectedRoles($data);
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        $this->applySelectedRoles($user);

        // Setting someone else's password is a reset: it is temporary, and whoever was signed in with
        // the old one is signed out. Changing your own through here is just a password change.
        if ($this->passwordWasSet && ! $user->is(auth()->user())) {
            $user->forceFill(['must_change_password' => true])->save();
            $user->revokeAccess();
        }

        // The toggle is not mass-assignable, so it is applied here.
        $active = (bool) ($this->data['is_active'] ?? true);

        if ($user->is_active !== $active && ! $user->is(auth()->user())) {
            $user->forceFill(['is_active' => $active])->save();
        }
    }
}
