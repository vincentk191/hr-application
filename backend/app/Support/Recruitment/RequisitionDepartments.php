<?php

namespace App\Support\Recruitment;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\ReportingLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which departments someone may raise a requisition for (REC-DSH-006, context.md §9.3 step 1).
 *
 * HR, holding `manage-recruitment`, may ask for any department of the company in view. A supervisor asks for a
 * role on their own team: the departments they head, and their own department when people report to them.
 * Anyone else asks for none, and so cannot start one.
 */
class RequisitionDepartments
{
    /**
     * @return Collection<int, Department>
     */
    public function for(User $user): Collection
    {
        if ($user->can(Permission::ManageRecruitment->value)) {
            return Department::query()->orderBy('name')->get(['id', 'name', 'company_id']);
        }

        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        if ($own === null) {
            return new Collection;
        }

        $ids = Department::query()->where('supervisor_employee_id', $own->id)->pluck('id');

        if ($own->department_id !== null && app(ReportingLine::class)->hasReports($user)) {
            $ids->push($own->department_id);
        }

        return Department::query()->whereIn('id', $ids->unique()->all())->orderBy('name')->get(['id', 'name', 'company_id']);
    }

    public function allows(User $user, int $departmentId): bool
    {
        return $this->for($user)->contains('id', $departmentId);
    }
}
