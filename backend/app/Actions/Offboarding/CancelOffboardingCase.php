<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingAudience;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR cancels a case, with a reason, until the exit has been applied: the resignation was withdrawn.
 * Nothing has been switched off by then, so nothing is undone; the case is kept as "Dibatalkan" and the
 * person can be offboarded again later.
 */
class CancelOffboardingCase
{
    public function __construct(private WorkflowEngine $engine, private OffboardingAudience $audience) {}

    /**
     * @throws ValidationException
     */
    public function handle(OffboardingCase $case, string $reason, User $by): void
    {
        if ($case->exitApplied()) {
            throw ValidationException::withMessages(['reason' => 'Keluarnya sudah diterapkan; kasus tidak bisa dibatalkan.']);
        }

        $audience = $this->audience->everyone($case);

        DB::transaction(function () use ($case, $reason, $by): void {
            $this->engine->comment($case->workflow, $by, "Kasus dibatalkan: {$reason}");
            $case->update(['cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by_user_id' => $by->id]);
            $this->engine->transitionTo($case->workflow->refresh(), OffboardingCaseStage::Cancelled, $by);
        });

        app(Notifier::class)->offboardingEvent($case, $audience, $by, "{$case->reference()}: kasus dibatalkan", $reason);
    }
}
