<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleForm
{
    /**
     * Permissions the System Admin role can never lose. Without them an administrator could
     * remove their own way back into this screen, and nobody could repair it short of the
     * database.
     *
     * @return list<string>
     */
    public static function lockedPermissionsFor(Role $role): array
    {
        if ($role->name !== RoleEnum::SystemAdmin->value) {
            return [];
        }

        return [PermissionEnum::ManageUsers->value, PermissionEnum::AccessAdminPanel->value];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Peran')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (TextInput $component, ?Role $record) => $component->state(
                        RoleEnum::tryFrom($record->name ?? '')?->label() ?? $record?->name,
                    )),
                CheckboxList::make('permission_names')
                    ->label('Izin')
                    ->options(fn (?Role $record): array => Permission::query()->orderBy('name')->pluck('name', 'name')
                        ->map(fn (string $name): string => (PermissionEnum::tryFrom($name)?->label() ?? $name)
                            .($record !== null && in_array($name, self::lockedPermissionsFor($record), true) ? ' (terkunci)' : ''))
                        ->all())
                    ->afterStateHydrated(fn (CheckboxList $component, ?Role $record) => $component->state(
                        $record?->permissions->pluck('name')->all() ?? [],
                    ))
                    // A locked permission is kept ticked rather than disabled: Filament refuses to save
                    // a form whose state contains a disabled option, which made every change to the
                    // System Admin role fail. Unticking one puts it straight back, and the save adds
                    // it again regardless (EditRole), so the lock does not rest on the browser.
                    ->afterStateUpdated(function (array $state, Set $set, ?Role $record): void {
                        $locked = $record === null ? [] : self::lockedPermissionsFor($record);

                        if (array_diff($locked, $state) !== []) {
                            $set('permission_names', array_values(array_unique([...$state, ...$locked])));
                        }
                    })
                    ->live()
                    ->helperText('Perubahan berlaku pada pengguna dengan peran ini pada permintaan berikutnya. Izin bertanda "terkunci" tidak dapat dicabut dari Admin Sistem, agar akses ke layar ini tidak hilang.'),
            ]);
    }
}
