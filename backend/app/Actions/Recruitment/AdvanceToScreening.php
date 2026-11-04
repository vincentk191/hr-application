<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;

/**
 * "Lanjut ke Screening ›" (REC-PST-003): HR moves a posted requisition on to 03 Screening. The stage's guard refuses
 * until the listing is recorded on at least one platform.
 */
class AdvanceToScreening
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @throws TransitionBlocked nothing is recorded as posted yet
     */
    public function handle(Requisition $requisition, User $by): void
    {
        $this->engine->transitionTo($requisition->workflow, RequisitionStage::Screening, $by);
    }
}
