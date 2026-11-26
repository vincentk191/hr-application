<?php

namespace App\Console\Commands;

use App\Models\Requisition;
use App\Support\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The target start date reminders (REC-WIZ), run every morning. From a week before a requisition's target start date,
 * through the day itself, HR and the new hire's direct superior are told each day that the role is still open. A role
 * that is filled or cancelled, or a requisition not yet sent, is left alone. Each person is told once a day: running the
 * command again changes nothing.
 */
#[Signature('recruitment:start-reminders')]
#[Description('Remind HR and the direct superior, each day of the week before a target start date, that the role is still open')]
class SendRequisitionStartReminders extends Command
{
    /** How many days before the target start date the reminders begin. */
    public const DAYS_AHEAD = 7;

    public function handle(Notifier $notifier): int
    {
        $today = CarbonImmutable::now((string) config('attendance.device_timezone'))->startOfDay();

        $due = Requisition::acrossCompanies()->open()
            ->whereDate('target_start_on', '>=', $today->toDateString())
            ->whereDate('target_start_on', '<=', $today->addDays(self::DAYS_AHEAD)->toDateString())
            ->get();

        foreach ($due as $requisition) {
            $start = CarbonImmutable::parse($requisition->target_start_on->toDateString(), $today->timezone);

            $notifier->requisitionStartNear($requisition, (int) $today->diffInDays($start, false), $today);
        }

        $this->info("{$due->count()} requisition(s) reminded.");

        return self::SUCCESS;
    }
}
