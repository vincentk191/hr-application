<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Akun aktif')
                    ->helperText('Akun yang dinonaktifkan langsung keluar dari semua sesi dan tidak bisa masuk. Akun tidak dihapus.')
                    ->default(true),
                TextInput::make('password')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Kata sandi sementara' : 'Kata sandi baru')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(12)
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Kosongkan untuk mempertahankan kata sandi saat ini. Mengisinya mereset kata sandi: pengguna wajib menggantinya saat masuk, dan sesinya yang aktif dicabut.'
                        : 'Pengguna wajib menggantinya saat masuk pertama kali.'),
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->placeholder('Tingkat grup (semua perusahaan)')
                    ->disabled(fn (Get $get): bool => RoleEnum::tryFrom((string) $get('role_name'))?->isGroupWide() === true)
                    ->helperText(fn (Get $get): string => RoleEnum::tryFrom((string) $get('role_name'))?->isGroupWide() === true
                        ? 'Admin HR dan Admin Sistem selalu di tingkat grup (semua perusahaan).'
                        : 'Akun yang terikat pada satu perusahaan hanya melihat data perusahaan itu.'),
                // Deliberately not a relationship field: role changes must go through
                // syncRoles() so LogRoleChange audits them (CORE-RBA-007).
                Select::make('role_name')
                    ->label('Peran')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if (RoleEnum::tryFrom((string) $state)?->isGroupWide() === true) {
                            $set('company_id', null);
                        }
                    })
                    ->options(fn (): array => self::roleOptions())
                    ->afterStateHydrated(fn (Select $component, ?User $record) => $component->state(
                        $record === null ? null : self::primaryRoleName($record),
                    ))
                    ->disabled(fn (?User $record): bool => $record !== null
                        && auth()->user()?->can('assignRoles', $record) !== true)
                    ->helperText('Anda tidak dapat mengubah peran akun Anda sendiri.'),
                // Deliberately not a relationship field either: see SyncsAssignedRoles.
                CheckboxList::make('extra_roles')
                    ->label('Akses tambahan')
                    ->options(fn (): array => self::addOnOptions())
                    ->descriptions([
                        RoleEnum::RecruitmentRequester->value => 'Boleh membuat permintaan rekrutmen untuk timnya sendiri. Persetujuannya tetap oleh Manajemen lalu HR.',
                    ])
                    ->afterStateHydrated(fn (CheckboxList $component, ?User $record) => $component->state(
                        $record === null ? [] : self::addOnNames($record),
                    ))
                    ->visible(fn (Get $get): bool => $get('role_name') === RoleEnum::Supervisor->value)
                    ->disabled(fn (?User $record): bool => $record !== null
                        && auth()->user()?->can('assignRoles', $record) !== true),
            ]);
    }

    /**
     * The one role an account is shown with: the highest in the Role enum's order, leaving out the add-on accesses. An
     * account that already holds several (from when roles were a multi-select) shows its most privileged one and
     * is brought down to it the next time roles are saved.
     */
    public static function primaryRoleName(User $user): ?string
    {
        return $user->primaryRoleName();
    }

    /**
     * The add-on accesses the account holds, as shown in "Akses tambahan".
     *
     * @return list<string>
     */
    public static function addOnNames(User $user): array
    {
        return $user->getRoleNames()->filter(fn (string $name): bool => in_array($name, RoleEnum::addOnNames(), true))->values()->all();
    }

    /**
     * The main roles, to choose one from.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()->whereNotIn('name', RoleEnum::addOnNames())->orderBy('name')->pluck('name', 'name')
            ->map(fn (string $name): string => RoleEnum::tryFrom($name)?->label() ?? $name)
            ->all();
    }

    /**
     * The add-on accesses, to tick any number of.
     *
     * @return array<string, string>
     */
    public static function addOnOptions(): array
    {
        return Role::query()->whereIn('name', RoleEnum::addOnNames())->orderBy('name')->pluck('name', 'name')
            ->map(fn (string $name): string => RoleEnum::tryFrom($name)?->label() ?? $name)
            ->all();
    }
}
