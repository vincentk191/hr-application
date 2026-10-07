<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role as RoleEnum;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
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
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->placeholder('Tingkat grup'),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleEnum::tryFrom($state)?->label() ?? $state),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
