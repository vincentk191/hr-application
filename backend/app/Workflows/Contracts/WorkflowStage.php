<?php

namespace App\Workflows\Contracts;

/**
 * A stage a workflow can be in.
 *
 * Implemented by a backed enum per module, because the stages genuinely differ: recruitment
 * has six, offboarding three plus a draft, a leave request only submitted and decided. What
 * is shared is the mechanics around them, not the list.
 */
interface WorkflowStage
{
    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string;

    /**
     * Whether reaching this stage ends the workflow.
     */
    public function isFinal(): bool;
}
