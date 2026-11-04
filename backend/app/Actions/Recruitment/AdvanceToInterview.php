<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;

/**
 * "Lanjut ke Interview ›" (REC-SCR): HR moves a requisition with a shortlist on to 04 Interview. The stage's guard
 * refuses while nobody is shortlisted.
 */
class AdvanceToInterview
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @throws TransitionBlocked nobody is shortlisted yet
     */
    public function handle(Requisition $requisition, User $by): void
    {
        $this->engine->transitionTo($requisition->workflow, RequisitionStage::Interview, $by);
    }
}
