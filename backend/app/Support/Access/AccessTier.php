<?php

namespace App\Support\Access;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OffboardingCaseDepartment;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\ReportingLine;

/**
 * Whether an account is elevated (CORE-ACC-003): anyone who can do or see more than a plain employee, who
 * therefore needs the private entrance for it. A role above Karyawan, people reporting to them, a department
 * they head, an approval bound to them, or a share of an offboarding checklist. On the public entrance an
 * elevated account gets the same limited self-service view as everyone else.
 */
class AccessTier
{
    public function __construct(private Entrance $entrance, private ReportingLine $line) {}

    public function isElevated(User $user): bool
    {
        // Asked as the private entrance: this is about what the account is, not what the request may do.
        return $this->entrance->withoutLimits(function () use ($user): bool {
            if ($user->roles()->where('name', '!=', 'employee')->exists()) {
                return true;
            }

            if ($this->line->hasReports($user)) {
                return true;
            }

            $own = Employee::acrossCompanies()->where('user_id', $user->id)->value('id');

            return ($own !== null && Department::acrossCompanies()->where('supervisor_employee_id', $own)->exists())
                || WorkflowStep::query()->where('approver_user_id', $user->id)->exists()
                || OffboardingCaseDepartment::query()->where('owner_user_id', $user->id)->exists();
        });
    }
}
