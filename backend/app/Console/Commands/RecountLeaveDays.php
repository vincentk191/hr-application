<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use Illuminate\Console\Command;

/**
 * Recount the working days on filed leave requests.
 *
 * A request's day count is fixed when it is filed, so a holiday added afterwards, or requests filed
 * before working days were counted (they were counted in calendar days), keep the old figure. This
 * brings them in line. It rewrites a stored number, so it asks which requests to touch: by default
 * only those that are not yet settled as approved, and `--all` for every request.
 */
class RecountLeaveDays extends Command
{
    protected $signature = 'leave:recount-days {--all : Recount approved requests too} {--dry-run : Show the changes without saving}';

    protected $description = 'Recount working days on leave requests (weekends and holidays excluded)';

    public function handle(): int
    {
        $changed = 0;

        LeaveRequest::acrossCompanies()
            ->with(['employee' => fn ($query) => $query->withoutGlobalScopes(), 'workflow'])
            ->orderBy('id')
            ->each(function (LeaveRequest $leave) use (&$changed): void {
                if (! $this->option('all') && $leave->workflow?->current_stage === 'approved') {
                    return;
                }

                if ($leave->employee === null) {
                    return;
                }

                $days = LeaveRequest::workingDays($leave->start_on, $leave->end_on, $leave->employee);

                if ($days === $leave->days) {
                    return;
                }

                $this->line("{$leave->reference()}: {$leave->days} → {$days}");
                $changed++;

                if (! $this->option('dry-run')) {
                    $leave->forceFill(['days' => $days])->save();
                }
            });

        $this->info($this->option('dry-run') ? "{$changed} would change." : "{$changed} updated.");

        return self::SUCCESS;
    }
}
