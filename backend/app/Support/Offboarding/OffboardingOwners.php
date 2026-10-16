<?php

namespace App\Support\Offboarding;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

/**
 * Who owns a department's part of a checklist: the department's supervisor (OFF-CHK-003). With no
 * supervisor set, or one who cannot act (no login, deactivated, or the person leaving), nobody is
 * named and HR Admin owns it, so no group is ever left without someone.
 */
class OffboardingOwners
{
    public function ownerOf(?Department $department, Employee $leaver): ?User
    {
        $supervisor = $department?->supervisor_employee_id === null
            ? null
            : Employee::acrossCompanies()->with('user')->find($department->supervisor_employee_id);
        $user = $supervisor?->user;

        if ($user === null || ! $user->is_active || $user->is($leaver->user) || ! $user->canAccessCompany($leaver->company_id)) {
            return null;
        }

        return $user;
    }
}
