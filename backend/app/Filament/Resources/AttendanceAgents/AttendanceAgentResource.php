<?php

namespace App\Filament\Resources\AttendanceAgents;

use App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents;
use App\Models\AttendanceAgent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The on-site sync agents and their API tokens (ATT-DEV-002, CORE-AUT-008).
 *
 * A token is shown once, when it is created or rotated, and is not stored anywhere readable: lose
 * it and the remedy is to rotate, which revokes the old one. Deleting an agent revokes its tokens.
 */
class AttendanceAgentResource extends Resource
{
    protected static ?string $model = AttendanceAgent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $modelLabel = 'agen sinkronisasi';

    protected static ?string $pluralModelLabel = 'Agen sinkronisasi';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-attendance-device') === true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->placeholder('SERVER-CAMAT')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('last_seen_at')->label('Terakhir terhubung')->dateTime('d M Y H:i')->placeholder('Belum pernah'),
                TextColumn::make('last_heartbeat_at')->label('Listener aktif terakhir')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('last_ip')->label('Alamat IP')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('rotate')
                    ->label('Putar token')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription('Token lama langsung berhenti berlaku. Agen harus diberi token baru.')
                    ->action(function (AttendanceAgent $record): void {
                        self::showToken($record->issueToken(), $record->name);
                    }),
                DeleteAction::make()
                    ->modalDescription('Semua token agen ini dicabut.'),
            ]);
    }

    /**
     * Show a token exactly once. Persistent, so it stays on screen until it is copied and closed.
     */
    public static function showToken(string $token, string $agent): void
    {
        Notification::make()
            ->title("Token untuk {$agent}")
            ->body("Salin sekarang, token ini tidak akan ditampilkan lagi:\n\n{$token}")
            ->success()
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceAgents::route('/'),
        ];
    }
}
