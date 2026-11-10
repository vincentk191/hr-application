<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;

/**
 * "Lanjut ke Penawaran ›" (REC-PIP): HR moves a requisition whose direct superior has chosen a candidate on to 05
 * Penawaran. The stage's guard refuses while nobody is chosen.
 */
class AdvanceToOffer
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @throws TransitionBlocked nobody is chosen yet
     */
    public function handle(Requisition $requisition, User $by): void
    {
        $this->engine->transitionTo($requisition->workflow, RequisitionStage::Offer, $by);
    }
}
