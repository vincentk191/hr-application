<?php

namespace App\Workflows\Guards;

use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\WorkflowEngine;

/**
 * Blocks a stage change until every step of the approval chain has been approved.
 *
 * The common gate: "Lanjut ke Posting" stays disabled until the requisition's approvals are in
 * (REC-APR-003), and offboarding's Next waits on the supervisor's decision (OFF-PIP-005).
 */
class ChainFullyApproved implements TransitionGuard
{
    public function __construct(private WorkflowEngine $engine) {}

    public function passes(WorkflowInstance $instance): bool
    {
        return $this->engine->chainIsApproved($instance);
    }

    public function message(WorkflowInstance $instance): string
    {
        $waiting = $this->engine->waitingOn($instance);

        if ($this->engine->wasRejected($instance)) {
            return 'Pengajuan ini ditolak, jadi tidak bisa dilanjutkan.';
        }

        return $waiting === null
            ? 'Alur persetujuan belum disiapkan.'
            : "Masih menunggu persetujuan: {$waiting->label}.";
    }
}
