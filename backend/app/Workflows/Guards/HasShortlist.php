<?php

namespace App\Workflows\Guards;

use App\Models\Requisition;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * Blocks the move to Interview until at least one candidate is shortlisted (REC-SCR).
 */
class HasShortlist implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $requisition = Requisition::acrossCompanies()->find($instance->subject_id);

        return $requisition instanceof Requisition && $requisition->candidates()->exists();
    }

    public function message(WorkflowInstance $instance): string
    {
        return 'Tambahkan setidaknya satu kandidat ke shortlist dulu.';
    }
}
