<?php

namespace App\Console\Commands;

use App\Actions\Offboarding\ApplyOffboardingExit;
use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Applies the exit of every offboarding case whose last working day has passed (OFF-CHK-010): the account
 * is switched off, the scanner mapping ends, and requests still waiting are cleared. The day after the
 * last working day, so people can work through it. Safe to run again.
 */
#[Signature('offboarding:apply-exits')]
#[Description('Switch off accounts and clear waiting requests for people whose last working day has passed')]
class ApplyOffboardingExits extends Command
{
    public function handle(ApplyOffboardingExit $exit): int
    {
        $today = CarbonImmutable::now((string) config('attendance.device_timezone'))->toDateString();
        $count = 0;

        OffboardingCase::acrossCompanies()
            ->whereNull('exit_applied_at')
            ->whereDate('last_working_on', '<', $today)
            ->whereHas('workflow', fn ($workflow) => $workflow->whereIn('current_stage', [OffboardingCaseStage::Checklist->value, OffboardingCaseStage::SignOff->value]))
            ->each(function (OffboardingCase $case) use ($exit, &$count): void {
                $exit->handle($case);
                $count++;
            });

        $this->info("{$count} exit(s) applied.");

        return self::SUCCESS;
    }
}
