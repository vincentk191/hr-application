<?php

namespace App\Console\Commands;

use App\Support\Email\CompanyMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sends the emails whose time has come (email automation), every minute: those that waited for a set time, and any that were
 * queued but never picked up. Running it again changes nothing: each email is claimed before it is sent.
 */
#[Signature('emails:dispatch-due')]
#[Description('Queue the sending of every email in the outbox that is due')]
class DispatchDueEmails extends Command
{
    public function handle(CompanyMailer $mailer): int
    {
        $this->info($mailer->dispatchDue().' email(s) queued.');

        return self::SUCCESS;
    }
}
