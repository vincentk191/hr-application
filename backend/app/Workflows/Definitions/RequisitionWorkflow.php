<?php

namespace App\Workflows\Definitions;

use App\Enums\RequisitionStage as Stage;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use App\Workflows\Guards\ChainFullyApproved;
use BackedEnum;

/**
 * The requisition pipeline (REC-PIP): 01 Diajukan, 02 Diposting, 03 Screening, 04 Interview, 05 Penawaran,
 * 06 Terisi.
 *
 * Forward only (REC-PIP-004): a stage never goes back to an earlier one, except that a rejection at stage 01
 * returns the requisition to its requester as a draft (REC-APR-006). Posting needs every approval done
 * (REC-APR-003). The later stages have no guards yet: each gets its own when it is built.
 */
class RequisitionWorkflow implements WorkflowDefinition
{
    public function key(): string
    {
        return 'requisition';
    }

    public function initialStage(): WorkflowStage&BackedEnum
    {
        return Stage::Draft;
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
            Stage::Draft->value => [Stage::Submitted->value],
            Stage::Submitted->value => [Stage::Posting->value, Stage::Draft->value, Stage::Cancelled->value],
            Stage::Posting->value => [Stage::Screening->value, Stage::Cancelled->value],
            Stage::Screening->value => [Stage::Interview->value, Stage::Cancelled->value],
            Stage::Interview->value => [Stage::Offer->value, Stage::Cancelled->value],
            Stage::Offer->value => [Stage::Filled->value, Stage::Cancelled->value],
            Stage::Filled->value => [],
            Stage::Cancelled->value => [],
        ];
    }

    /**
     * @return array<string, list<class-string<TransitionGuard>>>
     */
    public function guards(): array
    {
        return [
            Stage::Posting->value => [ChainFullyApproved::class],
        ];
    }
}
