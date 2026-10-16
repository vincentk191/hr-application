<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use App\Models\OffboardingSignoff;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingAudience;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * Moves a case with a complete checklist on to sign-off and sets up the signatures (OFF-CHK-011,
 * OFF-SIG-005): the leaver's Atasan first, then HR. With no usable Atasan (none set, no login,
 * deactivated, or leaving too) HR signs alone.
 */
class ProceedToSignOff
{
    public function __construct(private WorkflowEngine $engine, private OffboardingAudience $audience) {}

    public function handle(OffboardingCase $case, User $by): void
    {
        DB::transaction(function () use ($case, $by): void {
            $this->engine->transitionTo($case->workflow, OffboardingCaseStage::SignOff, $by);

            $case->signoffs()->delete();

            $steps = array_values(array_filter([
                ($atasan = $this->audience->atasan($case)) === null ? null : ['label' => 'Atasan langsung', 'user_id' => $atasan->id],
                ['label' => 'HR Admin', 'user_id' => null],
            ]));

            foreach ($steps as $index => $step) {
                OffboardingSignoff::create([
                    'company_id' => $case->company_id,
                    'offboarding_case_id' => $case->id,
                    'position' => $index + 1,
                    'label' => $step['label'],
                    'user_id' => $step['user_id'],
                ]);
            }
        });

        $first = $case->signoffs()->with('user')->first();

        if ($first?->user !== null) {
            app(Notifier::class)->offboardingEvent($case, [$first->user], $by, "{$case->reference()}: giliran Anda menandatangani", "Checklist offboarding {$case->employee->full_name} lengkap. Periksa lalu tandatangani.");
        }
    }
}
