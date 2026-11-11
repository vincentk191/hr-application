<?php

namespace App\Workflows\Guards;

use App\Models\Requisition;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * Blocks the move to Terisi until as many candidates have accepted as the requisition asked for (REC-FIL-001).
 */
class QuotaFilled implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $requisition = Requisition::acrossCompanies()->find($instance->subject_id);

        return $requisition instanceof Requisition && $requisition->isQuotaFilled();
    }

    public function message(WorkflowInstance $instance): string
    {
        return 'Kuota belum terpenuhi.';
    }
}
