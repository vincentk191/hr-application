<?php

namespace App\Workflows\Definitions;

use App\Enums\OffboardingCaseStage as Stage;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use App\Workflows\Guards\ChainFullyApproved;
use BackedEnum;

/**
 * Offboarding's three-stage pipeline (context.md §9.4, OFF-PIP-002).
 *
 * The first definition built on the engine, and the one the spec pins down most precisely, so
 * it doubles as the worked example for the modules that follow.
 *
 * Two shapes worth noting. The pipeline is forward-only except for one deliberate return:
 * a rejection sends the case back to Draft for the requester to correct (OFF-APR-006). And
 * entering Sign-off is guarded, because the case cannot proceed while a checklist item is
 * outstanding (OFF-CHK-001) — that second guard arrives with the checklist table; the
 * approval-chain guard is wired now.
 */
class OffboardingCaseWorkflow implements WorkflowDefinition
{
    public function key(): string
    {
        return 'offboarding_case';
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
            // A rejection returns the case to its requester (OFF-APR-006).
            Stage::Submitted->value => [Stage::Checklist->value, Stage::Draft->value],
            Stage::Checklist->value => [Stage::SignOff->value],
            Stage::SignOff->value => [Stage::Closed->value],
            Stage::Closed->value => [],
        ];
    }

    /**
     * @return array<string, list<class-string<TransitionGuard>>>
     */
    public function guards(): array
    {
        return [
            // HR only builds the checklist once the supervisor has approved (OFF-APR-001/004).
            Stage::Checklist->value => [ChainFullyApproved::class],
            // Sign-off additionally needs every checklist item ticked (OFF-CHK-001); that
            // guard lands with the checklist table.
            Stage::SignOff->value => [ChainFullyApproved::class],
        ];
    }
}
