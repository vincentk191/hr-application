<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\Concerns\SyncsAssignedRoles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsAssignedRoles;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractSelectedRoles($data);
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        // An administrator vouching for the address is the verification.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->applySelectedRoles($user);
    }
}
