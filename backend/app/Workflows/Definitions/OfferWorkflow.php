<?php

namespace App\Workflows\Definitions;

use App\Enums\OfferStage as Stage;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use BackedEnum;

/**
 * An offer to one candidate (REC-OFS): proposed by HR, approved or rejected by Management, withdrawn by HR until it is
 * sent. One workflow instance per offer, so a requisition's own approvals are left alone. No guards yet: the later stages
 * (sending, the candidate's answer) get theirs when they are built.
 */
class OfferWorkflow implements WorkflowDefinition
{
    public function key(): string
    {
        return 'candidate-offer';
    }

    public function initialStage(): WorkflowStage&BackedEnum
    {
        return Stage::Proposed;
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
            Stage::Proposed->value => [Stage::Approved->value, Stage::Rejected->value, Stage::Withdrawn->value],
            Stage::Approved->value => [Stage::Sent->value, Stage::Withdrawn->value],
            Stage::Sent->value => [Stage::Accepted->value, Stage::Declined->value, Stage::Withdrawn->value],
            Stage::Rejected->value => [],
            Stage::Withdrawn->value => [],
            Stage::Accepted->value => [],
            Stage::Declined->value => [],
        ];
    }

    /**
     * @return array<string, list<class-string<TransitionGuard>>>
     */
    public function guards(): array
    {
        return [];
    }
}
