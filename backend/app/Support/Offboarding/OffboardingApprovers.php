<?php

namespace App\Support\Offboarding;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Support\ReportingLine;
use App\Workflows\StepBlueprint;

/**
 * Who approves an offboarding case: Management, always (OFF-APR-001). Not the leaver's Atasan, who
 * sees the case and works the checklist but does not approve it.
 *
 * The decision is bound to the nearest Management account above the leaver in the reporting line.
 * With none, any Management account in the company may decide, so a case is never stuck. The person
 * leaving is never their own approver; that is checked when the decision is made.
 */
class OffboardingApprovers
{
    /**
     * @return list<StepBlueprint>
     */
    public function chainFor(Employee $leaver): array
    {
        $manager = $this->nearestManagement($leaver);

        return [
            $manager === null
                ? StepBlueprint::forRole('Manajemen', Role::Management)
                : StepBlueprint::forUserAsRole('Manajemen', $manager, Role::Management),
        ];
    }

    private function nearestManagement(Employee $leaver): ?User
    {
        return app(ReportingLine::class)->nearestAbove($leaver, Role::Management, $leaver->company_id, $leaver->user);
    }
}
