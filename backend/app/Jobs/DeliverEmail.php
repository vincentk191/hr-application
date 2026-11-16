<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Support\Email\CompanyMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one email from the outbox (email automation). CompanyMailer decides whether it is still due and when it has failed for
 * good; this only asks it to, and the queue tries again while it throws.
 */
class DeliverEmail implements ShouldQueue
{
    use Queueable;

    /** More than CompanyMailer::MAX_ATTEMPTS, so the email is marked failed by its own rule, not by the queue giving up. */
    public int $tries = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public int $messageId) {}

    public function handle(CompanyMailer $mailer): void
    {
        $message = EmailMessage::acrossCompanies()->find($this->messageId);

        if ($message instanceof EmailMessage) {
            $mailer->deliver($message);
        }
    }
}
