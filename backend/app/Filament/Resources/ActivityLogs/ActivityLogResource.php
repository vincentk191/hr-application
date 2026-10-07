<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\Permission;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * A read-only view of the audit trail: dossier edits, role grants and permission changes.
 *
 * Nothing here can create, change or delete an entry — the log is only useful if it cannot be
 * edited by the people it records.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'catatan aktivitas';

    protected static ?string $pluralModelLabel = 'Catatan aktivitas';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageUsers->value) === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
            TextEntry::make('log_name')->label('Jenis'),
            TextEntry::make('description')->label('Keterangan'),
            TextEntry::make('causer.name')->label('Oleh')->placeholder('Sistem'),
            TextEntry::make('subject_type')
                ->label('Objek')
                ->formatStateUsing(fn (?string $state, Activity $record): string => $state === null
                    ? '—'
                    : class_basename($state).' #'.$record->subject_id),
            TextEntry::make('properties')
                ->label('Rincian')
                ->columnSpanFull()
                ->formatStateUsing(fn (mixed $state): string => (string) json_encode(
                    $state,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ))
                ->extraAttributes(['class' => 'font-mono text-xs whitespace-pre-wrap']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('causer.name')
                    ->label('Oleh')
                    ->placeholder('Sistem'),
                TextColumn::make('subject_type')
                    ->label('Objek')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state === null
                        ? '—'
                        : class_basename($state).' #'.$record->subject_id),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Jenis')
                    ->options(fn (): array => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
