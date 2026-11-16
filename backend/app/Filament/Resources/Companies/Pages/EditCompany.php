<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Models\GoogleConnection;
use App\Support\Email\CompanyMailer;
use App\Support\Recruitment\InterviewEvent;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

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
            $this->mailSettingsAction(),
            $this->testEmailAction(),
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
     * The mailbox this company's emails are sent from (email automation): the SMTP login from the hosting's "mail client
     * settings", and the From and Reply-To. The password is never shown again; leaving it empty keeps the saved one.
     */
    private function mailSettingsAction(): Action
    {
        return Action::make('mailSettings')
            ->label('Atur email pengirim')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->modalHeading('Email pengirim perusahaan')
            ->modalWidth('2xl')
            ->fillForm(function (): array {
                $setting = $this->company()->mailSetting;

                return $setting === null
                    ? ['port' => 465, 'encryption' => 'ssl', 'is_enabled' => false]
                    : [...$setting->only(['host', 'port', 'encryption', 'username', 'from_address', 'from_name', 'reply_to', 'is_enabled'])];
            })
            ->schema([
                TextInput::make('host')->label('Server keluar (SMTP)')->placeholder('mail.contoh.id')->required()->maxLength(255),
                TextInput::make('port')->label('Port')->numeric()->minValue(1)->maxValue(65535)->required(),
                Select::make('encryption')->label('Enkripsi')->options(CompanyMailSetting::ENCRYPTIONS)->required(),
                TextInput::make('username')->label('Nama pengguna')->helperText('Biasanya alamat emailnya lengkap.')->required()->maxLength(255),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->required(fn (): bool => $this->company()->mailSetting === null)
                    ->helperText('Kosongkan untuk tidak mengubah yang tersimpan.'),
                TextInput::make('from_address')->label('Alamat pengirim')->email()->required()->maxLength(255),
                TextInput::make('from_name')->label('Nama pengirim')->maxLength(255),
                TextInput::make('reply_to')->label('Balasan ke (opsional)')->email()->maxLength(255),
                Toggle::make('is_enabled')->label('Aktif')->helperText('Selama tidak aktif, tidak ada email yang dikirim atas nama perusahaan ini.'),
            ])
            ->action(function (array $data): void {
                $company = $this->company();
                $fields = [
                    'host' => trim((string) $data['host']),
                    'port' => (int) $data['port'],
                    'encryption' => (string) $data['encryption'],
                    'username' => trim((string) $data['username']),
                    'from_address' => Str::lower(trim((string) $data['from_address'])),
                    'from_name' => filled($data['from_name'] ?? null) ? trim((string) $data['from_name']) : null,
                    'reply_to' => filled($data['reply_to'] ?? null) ? Str::lower(trim((string) $data['reply_to'])) : null,
                    'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                    ...(filled($data['password'] ?? null) ? ['password' => (string) $data['password']] : []),
                ];

                CompanyMailSetting::acrossCompanies()->updateOrCreate(['company_id' => $company->getKey()], $fields);

                // The password is not part of the trail, only that the mailbox was set and who by.
                activity('settings')
                    ->causedBy(auth()->user())
                    ->performedOn($company)
                    ->withProperties(['from' => $fields['from_address'], 'enabled' => $fields['is_enabled'], 'password_changed' => isset($fields['password'])])
                    ->log('Company mailbox changed');

                $company->unsetRelation('mailSetting');
                $this->refreshFormData([]);

                Notification::make()->title('Email pengirim disimpan.')->success()->send();
            });
    }

    /**
     * Sends a test email through the saved mailbox, now, and says what came of it, so a wrong password is found here and not
     * when an offer goes out.
     */
    private function testEmailAction(): Action
    {
        return Action::make('testEmail')
            ->label('Kirim email uji')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->visible(fn (): bool => $this->company()->mailSetting !== null)
            ->modalHeading('Kirim email uji')
            ->fillForm(fn (): array => ['to' => auth()->user()?->email])
            ->schema([
                TextInput::make('to')->label('Kirim ke')->email()->required()->helperText('Alamat Anda sendiri, untuk memeriksa bahwa emailnya sampai.'),
            ])
            ->action(function (array $data): void {
                $company = $this->company();
                $message = app(CompanyMailer::class)->sendNow(
                    $company,
                    EmailKind::Test,
                    (string) $data['to'],
                    "Email uji dari {$company->name}",
                    "Halo,\n\nIni email uji dari aplikasi HR {$company->name}. Jika Anda membacanya, pengaturan email pengirim sudah benar.",
                    auth()->user(),
                );

                $message->status === EmailStatus::Sent
                    ? Notification::make()->title("Email uji terkirim ke {$message->to_email}.")->success()->send()
                    : Notification::make()->title('Email uji gagal dikirim.')->body((string) $message->error)->danger()->send();
            });
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
