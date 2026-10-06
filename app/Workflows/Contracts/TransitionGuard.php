<?php

namespace App\Workflows\Contracts;

use App\Models\WorkflowInstance;

/**
 * A rule that can block a stage change (CORE-WFL-004).
 *
 * Guards are the reason "Lanjut ke sign-off" stays disabled while a checklist item is
 * outstanding, and why a requisition cannot reach Screening before every platform is posted.
 * They answer only "may this move happen"; they never perform the move.
 */
interface TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool;

    /**
     * Why the move was refused, phrased for the person who attempted it.
     */
    public function message(WorkflowInstance $instance): string;
}
