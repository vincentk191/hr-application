<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;

/**
 * Management's decision on a submitted case (OFF-APR-003, OFF-APR-006).
 *
 * Approving opens the checklist stage. Rejecting needs a reason and returns the case to Draft, for
 * HR to correct and send again.
 */
class DecideOffboardingCase
{
    public function __construct(private WorkflowEngine $engine) {}

    public function approve(OffboardingCase $case, User $by, ?string $note = null): void
    {
        $instance = $case->workflow;
        $step = $this->engine->waitingOn($instance);

        $this->engine->approve($step, $by, $note);
        $this->remember($instance, $by, $note);
        $this->engine->transitionTo($instance->refresh(), OffboardingCaseStage::Checklist, $by);

        app(StartChecklist::class)->handle($case);

        app(Notifier::class)->offboardingDecided($case->refresh(), true, $note, $by);
    }

    /**
     * A note given with a decision is also a comment, so it shows in the thread at once and stays with
     * the stage it was written in. Written before the stage changes, which is what tags it.
     */
    private function remember(\App\Models\WorkflowInstance $instance, User $by, ?string $note): void
    {
        if (trim((string) $note) !== '') {
            $this->engine->comment($instance, $by, (string) $note);
        }
    }

    public function reject(OffboardingCase $case, User $by, string $note): void
    {
        $instance = $case->workflow;
        $step = $this->engine->waitingOn($instance);

        $this->engine->reject($step, $by, $note);
        $this->remember($instance, $by, $note);
        $this->engine->transitionTo($instance->refresh(), OffboardingCaseStage::Draft, $by);

        app(Notifier::class)->offboardingDecided($case->refresh(), false, $note, $by);
    }
}
