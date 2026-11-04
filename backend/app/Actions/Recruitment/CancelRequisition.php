<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * HR calls off a requisition that is out of approval (REC-PST-004): the position was filled another way, or is no
 * longer needed. The reason is kept on the requisition and written into the thread under the stage it was in, and
 * the requester is told. Cancelling is final.
 */
class CancelRequisition
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Requisition $requisition, User $by, string $reason): void
    {
        $reason = trim($reason);

        DB::transaction(function () use ($requisition, $by, $reason): void {
            $instance = $requisition->workflow;

            $requisition->update(['cancel_reason' => $reason]);
            $this->engine->comment($instance, $by, $reason);
            $this->engine->transitionTo($instance->refresh(), RequisitionStage::Cancelled, $by);
        });

        app(Notifier::class)->requisitionCancelled($requisition->refresh(), $reason, $by);
    }
}
