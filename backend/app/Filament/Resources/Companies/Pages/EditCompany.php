<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Models\GoogleConnection;
use App\Support\Recruitment\InterviewEvent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * The company's Google account for interview invitations (REC-INT): connected from here, by an admin, to the sender address
     * set in the form. Connecting leaves for Google's consent screen and comes back to this page.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('connectGoogle')
                ->label('Hubungkan Google')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->visible(fn (): bool => app(InterviewEvent::class)->stateOf($this->company()->getKey()) !== 'ready')
                ->disabled(fn (): bool => in_array(app(InterviewEvent::class)->stateOf($this->company()->getKey()), ['unconfigured', 'no-sender'], true))
                ->url(fn (): string => route('recruitment.google.connect', $this->company())),
            Action::make('disconnectGoogle')
                ->label('Putuskan Google')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Undangan interview berikutnya dikirim lewat email aplikasi sampai Google dihubungkan lagi. Acara yang sudah dibuat tetap ada di Google Calendar.')
                ->visible(fn (): bool => GoogleConnection::acrossCompanies()->where('company_id', $this->company()->getKey())->exists())
                ->action(function (): void {
                    app(InterviewEvent::class)->disconnect($this->company()->getKey());

                    activity('settings')->causedBy(auth()->user())->performedOn($this->company())->log('Google account disconnected');

                    Notification::make()->title('Google diputus.')->success()->send();
                    $this->refreshFormData(['google_sender_email']);
                }),
        ];
    }

    /**
     * Changing the sender address is an admin's decision about who may send for the company, so it is kept in the audit trail.
     */
    protected function afterSave(): void
    {
        if ($this->company()->wasChanged('google_sender_email')) {
            activity('settings')
                ->causedBy(auth()->user())
                ->performedOn($this->company())
                ->withProperties(['sender' => $this->company()->google_sender_email])
                ->log('Google sender address changed');
        }
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = $this->getRecord();

        return $company;
    }
}
