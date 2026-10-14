<?php

namespace App\Filament\Resources\RequestTypes;

use App\Enums\Permission;
use App\Filament\Resources\RequestTypes\Pages\CreateRequestType;
use App\Filament\Resources\RequestTypes\Pages\EditRequestType;
use App\Filament\Resources\RequestTypes\Pages\ListRequestTypes;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * The kinds of request (jenis pengajuan) people can file: add one, rename it, give it a maximum
 * number of days, switch it off, or remove it.
 *
 * A type that requests have been filed under is switched off rather than removed, so those requests
 * keep their label. Needs `manage-leave-settings`, which HR Admin holds by default.
 */
class RequestTypeResource extends Resource
{
    protected static ?string $model = RequestType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'jenis pengajuan';

    protected static ?string $pluralModelLabel = 'Jenis pengajuan';

    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageLeaveSettings->value) === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('Nama')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            TextInput::make('code')
                ->label('Kode')
                ->helperText('Dibuat otomatis dari nama saat jenis ini dibuat, dan tidak berubah setelahnya.')
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),
            TextInput::make('max_days')
                ->label('Hari kerja maksimal per pengajuan')
                ->helperText('Kosongkan jika tanpa batas. Pengajuan yang sudah ada tidak berubah.')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(365),
            TextInput::make('max_back_days')
                ->label('Boleh mundur (hari)')
                ->helperText('Berapa hari kalender ke belakang pengajuan jenis ini boleh dimulai, mis. 3 untuk cuti sakit. Kosongkan jika tidak boleh mundur. Admin HR dan Manajemen tidak dibatasi.')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(365),
            TextInput::make('sort_order')
                ->label('Urutan')
                ->helperText('Yang lebih kecil tampil lebih dulu di formulir.')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
            Toggle::make('is_active')
                ->label('Tersedia untuk pengajuan baru')
                ->helperText('Matikan untuk menghentikan jenis ini tanpa menghapusnya; pengajuan yang sudah ada tetap memakainya.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Nama')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->color('gray'),
                TextColumn::make('max_days')->label('Maks. hari kerja')->placeholder('Tanpa batas')->sortable(),
                TextColumn::make('max_back_days')->label('Boleh mundur')->placeholder('Tidak')->suffix(' hari')->sortable(),
                TextColumn::make('requests_count')
                    ->label('Dipakai')
                    ->state(fn (RequestType $record): int => LeaveRequest::acrossCompanies()->where('leave_type', $record->code)->count()),
                IconColumn::make('is_active')->label('Tersedia')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                self::removeAction(),
            ])
            ->defaultSort('sort_order');
    }

    /**
     * Remove a type nobody has used. One that has been used is refused with a pointer to switching it
     * off, so no request is left without a label.
     */
    public static function removeAction(): Action
    {
        return Action::make('remove')
            ->label('Hapus')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus jenis pengajuan?')
            ->modalDescription('Jenis ini hilang dari formulir. Ini tidak dapat dibatalkan.')
            ->action(function (RequestType $record, Component $livewire): void {
                if ($record->isInUse()) {
                    Notification::make()
                        ->title('Jenis ini sudah dipakai pengajuan')
                        ->body('Matikan "Tersedia untuk pengajuan baru" saja; pengajuan yang sudah ada tetap memakainya.')
                        ->warning()
                        ->send();

                    return;
                }

                $record->delete();

                Notification::make()->title('Jenis pengajuan dihapus.')->success()->send();

                if ($livewire instanceof EditRecord) {
                    $livewire->redirect(static::getUrl());
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequestTypes::route('/'),
            'create' => CreateRequestType::route('/create'),
            'edit' => EditRequestType::route('/{record}/edit'),
        ];
    }
}
