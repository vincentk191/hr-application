<?php

namespace App\Support\Recruitment;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\ReportingLine;
use App\Workflows\StepBlueprint;

/**
 * Who approves a requisition at stage 01 (REC-APR-001, decided 2026-10-28): Management, then HR.
 *
 * Management means the Management role: directors and the level below them. The step is bound to the nearest
 * Management account above the requester on the reporting line; with none, any Management account of the
 * company may decide, so a requisition is never stuck. Management also sets the salary expectation while
 * approving (REC-APR-008). HR approves after Management, never before: the chain opens one step at a time.
 * The requester never decides a step of their own requisition (the policy checks that).
 */
class RequisitionApprovers
{
    public const MANAGEMENT_LABEL = 'Manajemen';

    public const HR_LABEL = 'HR';

    /**
     * @return list<StepBlueprint>
     */
    public function chainFor(Requisition $requisition): array
    {
        $manager = $this->nearestManagement($requisition);

        return [
            $manager === null
                ? StepBlueprint::forRole(self::MANAGEMENT_LABEL, Role::Management)
                : StepBlueprint::forUserAsRole(self::MANAGEMENT_LABEL, $manager, Role::Management),
            StepBlueprint::forRole(self::HR_LABEL, Role::HrAdmin),
        ];
    }

    private function nearestManagement(Requisition $requisition): ?User
    {
        $requester = $requisition->creator;
        $own = $requester === null ? null : Employee::acrossCompanies()->where('user_id', $requester->id)->first();

        return $own === null ? null : app(ReportingLine::class)->nearestAbove($own, Role::Management, $requisition->company_id, $requester);
    }
}
