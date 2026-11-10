<?php

namespace App\Workflows\Guards;

use App\Models\Candidate;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * Blocks the move to Penawaran until the direct superior has chosen a candidate who is still in the process (REC-PIP).
 */
class HasSelectedCandidate implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        return Candidate::acrossCompanies()
            ->selected()
            ->where('requisition_id', $instance->subject_id)
            ->exists();
    }

    public function message(WorkflowInstance $instance): string
    {
        return 'Atasan langsung belum memilih kandidat.';
    }
}
