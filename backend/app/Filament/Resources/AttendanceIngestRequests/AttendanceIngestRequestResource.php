<?php

namespace App\Filament\Resources\AttendanceIngestRequests;

use App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests;
use App\Models\AttendanceIngestRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The sync agent's request log (ATT-DEV-004): every request that reached the endpoint, with what
 * came of it. Read-only — a log that can be edited is not evidence of anything.
 */
class AttendanceIngestRequestResource extends Resource
{
    protected static ?string $model = AttendanceIngestRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'log sinkronisasi';

    protected static ?string $pluralModelLabel = 'Log sinkronisasi';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-attendance-device') === true;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('agent.name')->label('Agen')->placeholder('—'),
                TextColumn::make('status')
                    ->label('Hasil')
                    ->badge()
                    ->color(fn (string $state): string => $state === AttendanceIngestRequest::OK ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === AttendanceIngestRequest::OK ? 'Berhasil' : 'Ditolak'),
                TextColumn::make('rows')->label('Baris'),
                TextColumn::make('accepted')->label('Baru'),
                TextColumn::make('duplicates')->label('Duplikat'),
                TextColumn::make('unmapped')->label('Menunggu'),
                TextColumn::make('message')->label('Pesan')->wrap()->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Hasil')->options([
                    AttendanceIngestRequest::OK => 'Berhasil',
                    AttendanceIngestRequest::REJECTED => 'Ditolak',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceIngestRequests::route('/'),
        ];
    }
}
