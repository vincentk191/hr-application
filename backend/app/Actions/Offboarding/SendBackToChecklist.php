<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingAudience;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * HR found something missing or wrong at the last stage and sends the case back to the checklist with a note
 * (OFF-SIG-005).
 */
class SendBackToChecklist
{
    public function __construct(private WorkflowEngine $engine, private OffboardingAudience $audience) {}

    public function handle(OffboardingCase $case, User $by, string $note): void
    {
        DB::transaction(function () use ($case, $by, $note): void {
            // The note goes into the thread first, so it is tagged with the sign-off stage it was written in.
            $this->engine->comment($case->workflow, $by, $note);
            $case->update(['checklist_approved_at' => null, 'checklist_approved_by_user_id' => null]);
            $this->engine->transitionTo($case->workflow->refresh(), OffboardingCaseStage::Checklist, $by);
        });

        app(Notifier::class)->offboardingEvent($case, $this->audience->everyone($case), $by, "{$case->reference()}: dikembalikan ke checklist", $note);
    }
}
