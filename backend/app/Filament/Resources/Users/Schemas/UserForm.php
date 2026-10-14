<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ->helperText('Akun yang terikat pada satu perusahaan hanya melihat data perusahaan itu.'),
                // Deliberately not a relationship field: role changes must go through
                // syncRoles() so LogRoleChange audits them (CORE-RBA-007).
                Select::make('role_names')
                    ->label('Peran')
                    ->multiple()
                    ->options(fn (): array => self::roleOptions())
                    ->afterStateHydrated(fn (Select $component, ?User $record) => $component->state(
                        $record?->getRoleNames()->all() ?? [],
                    ))
                    ->disabled(fn (?User $record): bool => $record !== null
                        && auth()->user()?->can('assignRoles', $record) !== true)
                    ->helperText('Anda tidak dapat mengubah peran akun Anda sendiri.'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'name')
            ->map(fn (string $name): string => RoleEnum::tryFrom($name)?->label() ?? $name)
            ->all();
    }
}
