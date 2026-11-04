<?php

namespace App\Workflows\Guards;

use App\Models\Requisition;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * Blocks the move to Screening until the listing is recorded as posted on at least one platform (REC-PST-003).
 */
class HasBeenPosted implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $requisition = Requisition::acrossCompanies()->find($instance->subject_id);

        return $requisition instanceof Requisition && $requisition->postings()->exists();
    }

    public function message(WorkflowInstance $instance): string
    {
        return 'Tandai lowongan sudah diposting di setidaknya satu platform dulu.';
    }
}
