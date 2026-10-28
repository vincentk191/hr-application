<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;

/**
 * "Lanjut ke Posting ›" (REC-APR-003): HR moves an approved requisition on to 02 Diposting. The stage's guard
 * refuses while any approval is still open.
 */
class AdvanceToPosting
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @throws TransitionBlocked an approval is still missing
     */
    public function handle(Requisition $requisition, User $by): void
    {
        $this->engine->transitionTo($requisition->workflow, RequisitionStage::Posting, $by);
    }
}
