<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Enums\Role as RoleEnum;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Peran')
                    ->formatStateUsing(fn (string $state): string => RoleEnum::tryFrom($state)?->label() ?? $state)
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label('Jumlah izin')
                    ->counts('permissions'),
                TextColumn::make('users_count')
                    ->label('Jumlah pengguna')
                    ->counts('users'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
