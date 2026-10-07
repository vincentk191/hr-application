<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Applies the chosen permissions and records what changed.
     *
     * The locked permissions are added back here rather than trusted to the form, because a
     * disabled checkbox is not a server-side guarantee.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        $before = $record->permissions->pluck('name')->all();

        $after = array_values(array_unique([
            ...(array) ($data['permission_names'] ?? []),
            ...RoleForm::lockedPermissionsFor($record),
        ]));

        $record->syncPermissions($after);

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added !== [] || $removed !== []) {
            activity('roles')
                ->performedOn($record)
                ->causedBy(auth()->user())
                ->withProperties(['added' => $added, 'removed' => $removed])
                ->log("Permissions changed for role {$record->name}");
        }

        return $record;
    }
}
