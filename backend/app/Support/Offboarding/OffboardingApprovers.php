<?php

namespace App\Support\Offboarding;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
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
        $seen = [$leaver->id];
        $current = $leaver->manager_id === null ? null : Employee::acrossCompanies()->with('user')->find($leaver->manager_id);

        while ($current !== null && ! in_array($current->id, $seen, strict: true)) {
            $seen[] = $current->id;
            $user = $current->user;

            if ($user !== null && $user->is_active && $user->isA(Role::Management) && $user->canAccessCompany($leaver->company_id)
                && $user->isNot($leaver->user)) {
                return $user;
            }

            $current = $current->manager_id === null ? null : Employee::acrossCompanies()->with('user')->find($current->manager_id);
        }

        return null;
    }
}
