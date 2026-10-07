<?php

namespace App\Workflows\Definitions;

use App\Enums\LeaveRequestStage as Stage;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use App\Workflows\Guards\ChainFullyApproved;
use BackedEnum;

/**
 * Leave approval (context.md §9.6).
 *
 * The simplest workflow in the app, and the one that shows the engine's shape most clearly:
 * one decision, two possible outcomes, both final.
 *
 * Only the approval path is guarded. A rejection is the approver's own decision and must stay
 * reachable precisely when the chain is *not* fully approved — gating it the same way would
 * make a rejected request impossible to record.
 */
class LeaveRequestWorkflow implements WorkflowDefinition
{
    public function key(): string
    {
        return 'leave_request';
    }

    public function initialStage(): WorkflowStage&BackedEnum
    {
        return Stage::Submitted;
    }

    public function stageEnum(): string
    {
        return Stage::class;
    }

    /**
     * @return array<string, list<string>>
     */
    public function transitions(): array
    {
        return [
            Stage::Submitted->value => [Stage::Approved->value, Stage::Rejected->value],
            Stage::Approved->value => [],
            Stage::Rejected->value => [],
        ];
    }

    /**
     * @return array<string, list<class-string<TransitionGuard>>>
     */
    public function guards(): array
    {
        return [
            Stage::Approved->value => [ChainFullyApproved::class],
        ];
    }
}
