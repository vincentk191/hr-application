<?php

use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Enums\Role;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\EmailMessages\Pages\ManageEmailMessages;
use App\Jobs\DeliverEmail;
use App\Mail\CompanyEmail;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\Email\CompanyMailer;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The outbox behind every email the app sends on a company's behalf (email automation): each is written down, sent from a
 * queued job through the company's own mailbox and from its own address, can wait for a time and be cancelled, is tried again
 * when the mail server does not answer, and is shown in the log. No real mail server is ever used.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(now()->setTime(10, 0));

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->admin = User::factory()->create(['company_id' => null]);
    $this->admin->assignRole(Role::SystemAdmin->value);
    $this->mailbox = fn (array $overrides = []): CompanyMailSetting => CompanyMailSetting::factory()->forCompany($this->company)->create([
        'host' => 'mail.majujaya.test', 'from_address' => 'recruitment@majujaya.test', 'from_name' => 'HR Maju Jaya', 'reply_to' => 'hr@majujaya.test', ...$overrides,
    ]);
});

function outboxEmail(array $overrides = []): EmailMessage
{
    return app(CompanyMailer::class)->queue(
        test()->company, EmailKind::Test, $overrides['to'] ?? 'budi@example.com', $overrides['subject'] ?? 'Penawaran kerja', $overrides['body'] ?? "Halo Budi,\n\nSelamat.",
        $overrides['attachments'] ?? [], $overrides['sendAt'] ?? null,
    );
}

// --- Sending ---

it('writes an email down, queues its sending, and sends it from the company mailbox with its own From and Reply-To', function () {
    Mail::fake();
    Queue::fake();
    ($this->mailbox)();

    $message = outboxEmail();

    expect($message->status)->toBe(EmailStatus::Pending)->and($message->company_id)->toBe($this->company->id);
    Queue::assertPushed(DeliverEmail::class, fn (DeliverEmail $job): bool => $job->messageId === $message->id);

    app(CompanyMailer::class)->deliver($message);

    Mail::assertSent(CompanyEmail::class, function (CompanyEmail $mail): bool {
        $envelope = $mail->envelope();

        return $mail->hasTo('budi@example.com')
            && $envelope->from->address === 'recruitment@majujaya.test'
            && $envelope->from->name === 'HR Maju Jaya'
            && $envelope->replyTo[0]->address === 'hr@majujaya.test'
            && $mail->subjectLine === 'Penawaran kerja'
            && str_contains($mail->body, 'Selamat.');
    });

    $message->refresh();
    expect($message->status)->toBe(EmailStatus::Sent)->and($message->sent_at)->not->toBeNull()->and($message->attempts)->toBe(1)->and($message->error)->toBeNull();

    // It went through a mailer of this company's own, built from its saved settings.
    expect(config("mail.mailers.company-{$this->company->id}.host"))->toBe('mail.majujaya.test')
        ->and(config("mail.mailers.company-{$this->company->id}.scheme"))->toBe('smtps');
});

it('uses STARTTLS for a mailbox set to it', function () {
    $setting = ($this->mailbox)(['encryption' => 'tls', 'port' => 587]);

    expect($setting->mailerConfig())->toMatchArray(['transport' => 'smtp', 'scheme' => 'smtp', 'port' => 587]);
});

it('keeps the mailbox password encrypted, apart from the model', function () {
    ($this->mailbox)(['password' => 'rahasia-sekali']);

    expect(DB::table('company_mail_settings')->value('password'))->not->toContain('rahasia-sekali')
        ->and(CompanyMailSetting::acrossCompanies()->first()->password)->toBe('rahasia-sekali');
});

it('sends an email only once, however many times it is asked to', function () {
    Mail::fake();
    ($this->mailbox)();
    $message = outboxEmail();

    app(CompanyMailer::class)->deliver($message);
    app(CompanyMailer::class)->deliver($message);

    Mail::assertSent(CompanyEmail::class, 1);
});

it('fails at once, with the reason, when the company has no mailbox or it is not switched on', function () {
    Mail::fake();
    $message = outboxEmail();

    app(CompanyMailer::class)->deliver($message);

    expect($message->refresh()->status)->toBe(EmailStatus::Failed)
        ->and($message->error)->toBe('Email pengirim perusahaan belum diatur atau belum diaktifkan.')
        ->and($message->failed_at)->not->toBeNull();

    ($this->mailbox)(['is_enabled' => false]);
    $second = outboxEmail(['to' => 'ani@example.com']);
    app(CompanyMailer::class)->deliver($second);

    expect($second->refresh()->status)->toBe(EmailStatus::Failed);
    Mail::assertNothingSent();
});

it('attaches the files an email carries, from the private disk or from their content', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::disk('local')->put('letters/surat.pdf', '%PDF-1.4 surat');
    ($this->mailbox)();

    $message = outboxEmail(['attachments' => [
        ['name' => 'Surat penawaran.pdf', 'mime' => 'application/pdf', 'path' => 'letters/surat.pdf'],
        ['name' => 'catatan.txt', 'mime' => 'text/plain', 'data' => 'catatan'],
        ['name' => 'hilang.pdf', 'mime' => 'application/pdf', 'path' => 'letters/tidak-ada.pdf'],
    ]]);
    app(CompanyMailer::class)->deliver($message);

    // A file that is gone is left out rather than stopping the email.
    Mail::assertSent(CompanyEmail::class, fn (CompanyEmail $mail): bool => count($mail->attachments()) === 2);
});

// --- Failing and trying again ---

it('tries again when the mail server does not answer, and marks the email failed after the last try, keeping the error', function () {
    Queue::fake();
    ($this->mailbox)();
    Mail::shouldReceive('purge');
    Mail::shouldReceive('mailer')->times(3)->andThrow(new RuntimeException('Connection could not be established with host mail.majujaya.test'));
    $message = outboxEmail();
    $mailer = app(CompanyMailer::class);

    // The first two tries throw, so the queue tries again later, and the email waits for it.
    expect(fn () => $mailer->deliver($message))->toThrow(RuntimeException::class);
    expect($message->refresh()->status)->toBe(EmailStatus::Pending)->and($message->attempts)->toBe(1);

    expect(fn () => $mailer->deliver($message))->toThrow(RuntimeException::class);
    expect($message->refresh()->attempts)->toBe(2);

    // The last try does not throw: the email has failed, and says why.
    $mailer->deliver($message);
    expect($message->refresh()->status)->toBe(EmailStatus::Failed)
        ->and($message->attempts)->toBe(3)
        ->and($message->error)->toContain('Connection could not be established');
});

it('sends now and reports the result at once, without the queue and without throwing', function () {
    Queue::fake();
    Mail::fake();
    ($this->mailbox)();

    $sent = app(CompanyMailer::class)->sendNow($this->company, EmailKind::Test, 'budi@example.com', 'Uji', 'Halo');

    expect($sent->status)->toBe(EmailStatus::Sent);
    Queue::assertNothingPushed();
});

it('reports a failed test send on the email itself', function () {
    ($this->mailbox)();
    Mail::shouldReceive('purge');
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Authentication failed'));

    $failed = app(CompanyMailer::class)->sendNow($this->company, EmailKind::Test, 'budi@example.com', 'Uji', 'Halo');

    expect($failed->status)->toBe(EmailStatus::Failed)->and($failed->error)->toBe('Authentication failed');
});

it('tries a failed email again from the start, and leaves one that went alone', function () {
    Queue::fake();
    $failed = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Failed)->create(['attempts' => 3, 'error' => 'Gagal']);
    $sent = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Sent)->create();

    expect(app(CompanyMailer::class)->resend($failed))->toBeTrue()
        ->and(app(CompanyMailer::class)->resend($sent))->toBeFalse();

    $failed->refresh();
    expect($failed->status)->toBe(EmailStatus::Pending)->and($failed->attempts)->toBe(0)->and($failed->error)->toBeNull();
    Queue::assertPushed(DeliverEmail::class, 1);
});

// --- Waiting for a time ---

it('holds an email until its time, sends it when due, and lets it be cancelled before then', function () {
    Queue::fake();
    $tomorrow = now()->addDay()->setTime(8, 0);

    $waiting = outboxEmail(['sendAt' => $tomorrow]);
    $cancelled = outboxEmail(['sendAt' => $tomorrow, 'to' => 'ani@example.com']);

    // Nothing is queued yet, and the command finds nothing due.
    Queue::assertNothingPushed();
    expect(app(CompanyMailer::class)->dispatchDue())->toBe(0);

    expect(app(CompanyMailer::class)->cancel($cancelled))->toBeTrue();
    expect($cancelled->refresh()->status)->toBe(EmailStatus::Cancelled);

    $this->travelTo($tomorrow->addMinute());
    Artisan::call('emails:dispatch-due');

    // Only the one still waiting goes; the cancelled one never does.
    Queue::assertPushed(DeliverEmail::class, 1);
    Queue::assertPushed(DeliverEmail::class, fn (DeliverEmail $job): bool => $job->messageId === $waiting->id);
});

it('does not send an email that was cancelled, nor cancel one that has already gone', function () {
    Mail::fake();
    ($this->mailbox)();
    $message = outboxEmail(['sendAt' => now()->addHour()]);
    app(CompanyMailer::class)->cancel($message);

    app(CompanyMailer::class)->deliver($message);
    Mail::assertNothingSent();

    $sent = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Sent)->create();
    expect(app(CompanyMailer::class)->cancel($sent))->toBeFalse()->and($sent->refresh()->status)->toBe(EmailStatus::Sent);
});

it('puts an email back in the queue when the worker that was sending it stopped', function () {
    Queue::fake();
    $stuck = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Sending)->create(['updated_at' => now()->subMinutes(20)]);
    $busy = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Sending)->create(['updated_at' => now()->subMinutes(2)]);

    expect(app(CompanyMailer::class)->dispatchDue())->toBe(1);

    expect($stuck->refresh()->status)->toBe(EmailStatus::Pending)->and($busy->refresh()->status)->toBe(EmailStatus::Sending);
});

// --- The admin panel ---

it('lets an admin save the company mailbox, keeps the saved password when it is left empty, and never writes it to the trail', function () {
    $this->actingAs($this->admin);
    $page = fn () => Livewire::test(EditCompany::class, ['record' => $this->company->getKey()]);

    $page()->assertSee('Email pengirim belum diatur')
        ->callAction('mailSettings', [
            'host' => 'mail.majujaya.test', 'port' => 465, 'encryption' => 'ssl', 'username' => 'recruitment@majujaya.test', 'password' => 'rahasia-1',
            'from_address' => 'Recruitment@MajuJaya.test', 'from_name' => 'HR Maju Jaya', 'reply_to' => '', 'is_enabled' => true,
        ])
        ->assertHasNoActionErrors();

    $setting = CompanyMailSetting::acrossCompanies()->where('company_id', $this->company->id)->firstOrFail();
    expect($setting->password)->toBe('rahasia-1')
        ->and($setting->from_address)->toBe('recruitment@majujaya.test')
        ->and($setting->is_enabled)->toBeTrue();

    // Saved again with the password empty: it stays.
    $page()->assertSee('Email dikirim dari recruitment@majujaya.test')
        ->callAction('mailSettings', [
            'host' => 'mail.baru.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'recruitment@majujaya.test', 'password' => '',
            'from_address' => 'recruitment@majujaya.test', 'from_name' => '', 'reply_to' => 'hr@majujaya.test', 'is_enabled' => true,
        ])
        ->assertHasNoActionErrors();

    $setting->refresh();
    expect($setting->password)->toBe('rahasia-1')->and($setting->host)->toBe('mail.baru.test')->and($setting->reply_to)->toBe('hr@majujaya.test');

    $trail = Activity::query()->where('description', 'Company mailbox changed')->get();
    expect($trail)->toHaveCount(2)
        ->and($trail->last()->properties->get('password_changed'))->toBeFalse()
        ->and($trail->toJson())->not->toContain('rahasia-1');
});

it('needs a password the first time the mailbox is saved', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCompany::class, ['record' => $this->company->getKey()])
        ->callAction('mailSettings', [
            'host' => 'mail.majujaya.test', 'port' => 465, 'encryption' => 'ssl', 'username' => 'recruitment@majujaya.test', 'password' => '',
            'from_address' => 'recruitment@majujaya.test', 'is_enabled' => false,
        ])
        ->assertHasActionErrors(['password' => 'required']);

    expect(CompanyMailSetting::acrossCompanies()->count())->toBe(0);
});

it('sends a test email from the company page and says what came of it', function () {
    Mail::fake();
    ($this->mailbox)();
    $this->actingAs($this->admin);

    Livewire::test(EditCompany::class, ['record' => $this->company->getKey()])
        ->callAction('testEmail', ['to' => 'admin@majujaya.test'])
        ->assertNotified('Email uji terkirim ke admin@majujaya.test.');

    Mail::assertSent(CompanyEmail::class, fn (CompanyEmail $mail): bool => $mail->hasTo('admin@majujaya.test') && str_contains($mail->subjectLine, 'PT Maju Jaya'));
    expect(EmailMessage::acrossCompanies()->first()->kind)->toBe(EmailKind::Test);
});

it('shows a failed test with the reason, and offers the test only once a mailbox is saved', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCompany::class, ['record' => $this->company->getKey()])->assertActionHidden('testEmail');

    ($this->mailbox)();
    Mail::shouldReceive('purge');
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Authentication failed'));

    Livewire::test(EditCompany::class, ['record' => $this->company->getKey()])
        ->callAction('testEmail', ['to' => 'admin@majujaya.test'])
        ->assertNotified('Email uji gagal dikirim.');
});

it('lists the emails for an admin, who can send a failed one again or stop one that is waiting', function () {
    Queue::fake();
    $failed = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Failed)->create(['subject' => 'Gagal terkirim', 'error' => 'Authentication failed']);
    $waiting = EmailMessage::factory()->forCompany($this->company)->create(['subject' => 'Menunggu pagi', 'send_at' => now()->addDay()]);
    $sent = EmailMessage::factory()->forCompany($this->company)->status(EmailStatus::Sent)->create(['subject' => 'Sudah terkirim']);
    $this->actingAs($this->admin);

    Livewire::test(ManageEmailMessages::class)
        ->assertCanSeeTableRecords([$failed, $waiting, $sent])
        ->assertTableActionVisible('resend', $failed)
        ->assertTableActionHidden('resend', $sent)
        ->assertTableActionVisible('cancel', $waiting)
        ->assertTableActionHidden('cancel', $sent)
        ->callTableAction('resend', $failed)
        ->callTableAction('cancel', $waiting);

    expect($failed->refresh()->status)->toBe(EmailStatus::Pending)->and($waiting->refresh()->status)->toBe(EmailStatus::Cancelled);
    Queue::assertPushed(DeliverEmail::class, 1);
});

it('keeps the email log and the mailbox settings from everyone but an admin', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);

    $this->actingAs($hr)->get('/admin/email-messages')->assertForbidden();
});
