<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
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
                    ->options(fn (): array => Permission::query()->orderBy('name')->pluck('name', 'name')
                        ->map(fn (string $name): string => PermissionEnum::tryFrom($name)?->label() ?? $name)
                        ->all())
                    ->afterStateHydrated(fn (CheckboxList $component, ?Role $record) => $component->state(
                        $record?->permissions->pluck('name')->all() ?? [],
                    ))
                    ->disableOptionWhen(fn (string $value, ?Role $record): bool => $record !== null
                        && in_array($value, self::lockedPermissionsFor($record), true))
                    ->helperText('Perubahan berlaku pada pengguna dengan peran ini pada permintaan berikutnya.'),
            ]);
    }
}
