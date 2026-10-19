<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;

/**
 * HR moves a case with a complete checklist on to the last stage (OFF-CHK-011). There are no signatures
 * there: whoever asked for the case fills in the exit details and closes it, so they are told it is theirs
 * when someone else moved it on.
 */
class ProceedToSignOff
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(OffboardingCase $case, User $by): void
    {
        $this->engine->transitionTo($case->workflow, OffboardingCaseStage::SignOff, $by);

        $case->loadMissing('creator', 'employee');

        if ($case->creator !== null && $case->creator->is_active && ! $case->creator->is($by)) {
            app(Notifier::class)->offboardingEvent($case, [$case->creator], $by, "{$case->reference()}: siap diselesaikan", "Checklist offboarding {$case->employee->full_name} lengkap. Isi wawancara keluar lalu tutup kasus setelah hari kerja terakhir.");
        }
    }
}
