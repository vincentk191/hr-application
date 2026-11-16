<?php

namespace App\Support\Email;

use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Jobs\DeliverEmail;
use App\Mail\CompanyEmail;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Models\EmailMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * How the app sends email on a company's behalf (email automation): every email is first written down (EmailMessage, the log
 * and the outbox in one), then sent from a queued job through the company's own mailbox, from its own address. Nothing here
 * ever blocks the action that caused the email: a failure is recorded and shown, not thrown at the person.
 *
 * - An email can wait for a time (`$sendAt`) and be cancelled before it goes; `emails:dispatch-due` picks up what is due.
 * - Only one worker sends a given email, and only once: it is claimed before it is sent.
 * - A transient failure (the mail server not answering) is tried again a few times; a missing or disabled mailbox setting
 *   fails at once, since trying again would change nothing.
 */
class CompanyMailer
{
    /** How many times one email is tried before it is marked failed. */
    public const MAX_ATTEMPTS = 3;

    /** An email still "being sent" after this long was lost with a worker that stopped, and goes back to waiting. */
    private const STUCK_AFTER_MINUTES = 15;

    /**
     * Whether the company can send email now: it has a mailbox and it is switched on. Where it cannot, whoever is about to send
     * is told so instead of an email failing later.
     */
    public function isReady(int $companyId): bool
    {
        return CompanyMailSetting::acrossCompanies()->where('company_id', $companyId)->where('is_enabled', true)->exists();
    }

    /**
     * Writes the email down and, when it is due now, queues the sending.
     *
     * @param  list<array{name: string, mime: string, path?: string, data?: string}>  $attachments
     */
    public function queue(
        Company|int $company,
        EmailKind $kind,
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        ?CarbonInterface $sendAt = null,
        ?Model $related = null,
        ?User $by = null,
        ?string $toName = null,
    ): EmailMessage {
        $message = $this->write($company, $kind, $to, $subject, $body, $attachments, $sendAt, $related, $by, $toName);

        if ($sendAt === null || $sendAt->lessThanOrEqualTo(now())) {
            DeliverEmail::dispatch($message->id)->afterCommit();
        }

        return $message;
    }

    /**
     * Writes the email down and sends it here and now, once, without the queue, so the person who asked sees the result at
     * once (the test email). A failure is on the returned email, not thrown.
     */
    public function sendNow(Company|int $company, EmailKind $kind, string $to, string $subject, string $body, ?User $by = null): EmailMessage
    {
        $message = $this->write($company, $kind, $to, $subject, $body, [], null, null, $by, null);

        try {
            $this->deliver($message);
        } catch (Throwable $exception) {
            $this->fail($message->refresh(), Str::limit($exception->getMessage(), 500));
        }

        return $message->refresh();
    }

    /**
     * @param  list<array{name: string, mime: string, path?: string, data?: string}>  $attachments
     */
    private function write(Company|int $company, EmailKind $kind, string $to, string $subject, string $body, array $attachments, ?CarbonInterface $sendAt, ?Model $related, ?User $by, ?string $toName): EmailMessage
    {
        return EmailMessage::acrossCompanies()->create([
            'company_id' => $company instanceof Company ? $company->id : $company,
            'kind' => $kind,
            'to_email' => $to,
            'to_name' => $toName,
            'subject' => Str::limit($subject, 300, ''),
            'body' => $body,
            'attachments' => $attachments === [] ? null : $attachments,
            'status' => EmailStatus::Pending,
            'send_at' => $sendAt ?? now(),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'created_by_user_id' => $by?->id,
        ]);
    }

    /**
     * Sends the email now, if it is still waiting. A failure is written on the email; the exception is thrown only when the
     * queue should try again.
     *
     * @throws Throwable a transient failure, with tries left
     */
    public function deliver(EmailMessage $message): void
    {
        $claimed = EmailMessage::acrossCompanies()
            ->whereKey($message->id)
            ->where('status', EmailStatus::Pending->value)
            ->update(['status' => EmailStatus::Sending->value]);

        if ($claimed === 0) {
            return;
        }

        $message->refresh();
        $settings = CompanyMailSetting::acrossCompanies()->where('company_id', $message->company_id)->first();

        if (! $settings instanceof CompanyMailSetting || ! $settings->is_enabled) {
            $this->fail($message, 'Email pengirim perusahaan belum diatur atau belum diaktifkan.');

            return;
        }

        // A mailer of its own per company, rebuilt each time: a worker that runs for an hour must not keep an old password.
        $mailer = 'company-'.$settings->company_id;
        config(["mail.mailers.{$mailer}" => $settings->mailerConfig()]);
        Mail::purge($mailer);

        $message->attempts++;

        try {
            Mail::mailer($mailer)
                ->to(new Address($message->to_email, (string) $message->to_name))
                ->send(new CompanyEmail($message->subject, $message->body, $settings->from_address, $settings->from_name, $settings->reply_to, $message->attachments ?? []));
        } catch (Throwable $exception) {
            $error = Str::limit($exception->getMessage(), 500);

            if ($message->attempts < self::MAX_ATTEMPTS) {
                $message->forceFill(['status' => EmailStatus::Pending, 'error' => $error])->save();

                throw $exception;
            }

            $this->fail($message, $error);

            return;
        }

        $message->forceFill(['status' => EmailStatus::Sent, 'sent_at' => now(), 'error' => null])->save();
    }

    /**
     * Tries a failed email again, from the start.
     */
    public function resend(EmailMessage $message): bool
    {
        $reset = EmailMessage::acrossCompanies()
            ->whereKey($message->id)
            ->where('status', EmailStatus::Failed->value)
            ->update(['status' => EmailStatus::Pending->value, 'attempts' => 0, 'error' => null, 'failed_at' => null, 'send_at' => now()]);

        if ($reset === 0) {
            return false;
        }

        DeliverEmail::dispatch($message->id)->afterCommit();

        return true;
    }

    /**
     * Stops an email that has not gone yet (a rejection waiting for the morning).
     */
    public function cancel(EmailMessage $message): bool
    {
        return EmailMessage::acrossCompanies()
            ->whereKey($message->id)
            ->where('status', EmailStatus::Pending->value)
            ->update(['status' => EmailStatus::Cancelled->value]) > 0;
    }

    /**
     * Queues the sending of every email that is due, and puts back those a stopped worker left half-sent.
     *
     * @return int how many were queued
     */
    public function dispatchDue(): int
    {
        EmailMessage::acrossCompanies()
            ->where('status', EmailStatus::Sending->value)
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->update(['status' => EmailStatus::Pending->value]);

        $due = EmailMessage::acrossCompanies()
            ->where('status', EmailStatus::Pending->value)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit(200)
            ->pluck('id');

        foreach ($due as $id) {
            DeliverEmail::dispatch((int) $id);
        }

        return $due->count();
    }

    private function fail(EmailMessage $message, string $error): void
    {
        $message->forceFill(['status' => EmailStatus::Failed, 'error' => $error, 'failed_at' => now()])->save();
    }
}
