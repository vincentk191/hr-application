<?php

namespace App\Console\Commands;

use App\Support\Recruitment\GoogleInterviewSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The routine check of interviews made through Google Calendar (REC-INT), run every fifteen minutes: follows a time changed
 * on Google, flags an event deleted there, and keeps the Meet link and the answers up to date. Running it again changes
 * nothing that is already up to date.
 */
#[Signature('recruitment:sync-google-events')]
#[Description('Follow changes made on Google Calendar to the interviews invited through it')]
class SyncGoogleInterviews extends Command
{
    public function handle(GoogleInterviewSync $sync): int
    {
        $totals = $sync->run();

        $this->info("{$totals['checked']} interview(s) checked, {$totals['moved']} moved, {$totals['deleted']} deleted on Google.");

        return self::SUCCESS;
    }
}
