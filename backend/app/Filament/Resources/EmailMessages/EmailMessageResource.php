<?php

namespace App\Filament\Resources\EmailMessages;

use App\Enums\AdminNavigationGroup;
use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Enums\Permission;
use App\Filament\Resources\EmailMessages\Pages\ManageEmailMessages;
use App\Models\EmailMessage;
use App\Support\Email\CompanyMailer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The log of every email the app sent on a company's behalf, and the outbox of those still waiting (email automation).
 * Read-only: a log that can be edited is not evidence of anything. A failed email can be tried again, and one that has not
 * gone yet can be stopped.
 */
class EmailMessageResource extends Resource
{
    protected static ?string $model = EmailMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'email';

    protected static ?string $pluralModelLabel = 'Log email';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageCompanies->value) === true;
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
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('company.name')->label('Perusahaan')->toggleable(),
                TextColumn::make('kind')->label('Jenis')->badge(),
                TextColumn::make('to_email')->label('Kepada')->searchable(),
                TextColumn::make('subject')->label('Subjek')->limit(50)->searchable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('send_at')->label('Jadwal kirim')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent_at')->label('Terkirim')->dateTime('d M Y H:i')->toggleable(),
                TextColumn::make('error')->label('Kesalahan')->wrap()->limit(80)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(EmailStatus::class),
                SelectFilter::make('kind')->label('Jenis')->options(EmailKind::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (EmailMessage $record): string => $record->subject)
                    ->schema([
                        TextEntry::make('to_email')->label('Kepada'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('error')->label('Kesalahan')->placeholder('—'),
                        TextEntry::make('body')->label('Isi')->extraAttributes(['style' => 'white-space: pre-line']),
                        TextEntry::make('attachments')->label('Lampiran')
                            ->state(fn (EmailMessage $record): string => collect($record->attachments)->pluck('name')->implode(', '))
                            ->placeholder('—'),
                    ]),
                Action::make('resend')
                    ->label('Kirim ulang')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->visible(fn (EmailMessage $record): bool => $record->status === EmailStatus::Failed)
                    ->action(function (EmailMessage $record): void {
                        app(CompanyMailer::class)->resend($record);

                        Notification::make()->title('Email dikirim ulang.')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EmailMessage $record): bool => $record->status === EmailStatus::Pending)
                    ->action(function (EmailMessage $record): void {
                        $stopped = app(CompanyMailer::class)->cancel($record);

                        Notification::make()->title($stopped ? 'Email dibatalkan.' : 'Email sudah terlanjur dikirim.')->{$stopped ? 'success' : 'warning'}()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailMessages::route('/'),
        ];
    }
}
