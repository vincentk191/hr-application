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
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractSelectedRoles($data);
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        $this->applySelectedRoles($user);
    }
}
