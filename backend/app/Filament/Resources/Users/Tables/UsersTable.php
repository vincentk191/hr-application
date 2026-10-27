<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UsersTable
{
    /**
     * Resetting or revoking is for someone else's account: doing it to yourself would sign you out
     * in the middle of the action. UserPolicy decides who may touch whose account.
     */
    private static function mayManage(User $record): bool
    {
        $actor = auth()->user();

        return $actor !== null && ! $actor->is($record) && $actor->can('update', $record);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Terakhir masuk')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(),
                IconColumn::make('must_change_password')
                    ->label('Sandi sementara')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                    ->falseIcon('')
                    ->trueColor('warning'),
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->placeholder('Tingkat grup'),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleEnum::tryFrom($state)?->label() ?? $state),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetPassword')
                    ->label('Reset sandi')
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalDescription('Sandi sementara dibuat dan ditampilkan sekali. Pengguna wajib menggantinya saat masuk, dan semua sesinya dicabut.')
                    ->visible(fn (User $record): bool => self::mayManage($record))
                    ->action(function (User $record): void {
                        $temporary = Str::password(14, symbols: false);

                        $record->update(['password' => $temporary]);
                        $record->forceFill(['must_change_password' => true])->save();
                        $record->revokeAccess();

                        Notification::make()
                            ->title("Sandi sementara untuk {$record->name}")
                            ->body("Salin sekarang, sandi ini tidak akan ditampilkan lagi:\n\n{$temporary}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('resetTwoFactor')
                    ->label('Reset 2FA')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->requiresConfirmation()
                    ->modalDescription('Untuk pengguna yang kehilangan perangkatnya. Autentikasi dua langkah dimatikan dan semua sesinya dicabut; akun yang wajib memakainya harus mengaktifkannya lagi saat masuk berikutnya.')
                    ->visible(fn (User $record): bool => self::mayManage($record) && $record->two_factor_secret !== null)
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                        ])->save();
                        $record->revokeAccess();

                        activity('auth')
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->log('Two-factor authentication reset by an administrator');

                        Notification::make()->title("2FA {$record->name} direset.")->success()->send();
                    }),
                Action::make('revokeSessions')
                    ->label('Cabut sesi')
                    ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                    ->requiresConfirmation()
                    ->modalDescription('Pengguna langsung keluar dari semua perangkat dan token API-nya dibatalkan. Kata sandinya tidak berubah.')
                    ->visible(fn (User $record): bool => self::mayManage($record))
                    ->action(function (User $record): void {
                        $record->revokeAccess();

                        Notification::make()->title("Sesi {$record->name} dicabut.")->success()->send();
                    }),
            ])
            ->defaultSort('name');
    }
}
