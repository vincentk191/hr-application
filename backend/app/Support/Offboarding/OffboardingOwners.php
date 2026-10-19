<?php

namespace App\Support\Offboarding;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OffboardingItem;
use App\Models\Position;
use App\Models\User;

/**
 * Who owns an offboarding item on a case (OFF-CHK-003): the person named on the item; failing that the
 * supervisor of the department it names, in the leaver's company; failing that nobody is named and HR
 * Admin owns it. The owner may belong to another company, because some departments serve several.
 *
 * Someone cannot own an item if they have no login, are deactivated, or are the person leaving.
 */
class OffboardingOwners
{
    public function ownerOf(OffboardingItem $item, Employee $leaver): ?User
    {
        $named = $item->owner_employee_id === null
            ? null
            : Employee::acrossCompanies()->with('user')->find($item->owner_employee_id);

        if ($this->canAct($named?->user, $leaver)) {
            return $named->user;
        }

        $supervisor = $this->departmentSupervisor($item->department_name, $leaver);

        return $this->canAct($supervisor?->user, $leaver) ? $supervisor->user : null;
    }

    /**
     * The owner's job title and department, as they stand now (OFF-CHK-002).
     *
     * @return array{title: string|null, department: string|null, company: string|null}
     */
    public function describe(User $owner): array
    {
        $employee = Employee::acrossCompanies()->where('user_id', $owner->id)->first();

        // Read across companies: the owner may belong to a different one than whoever is asking.
        return [
            'title' => $employee?->position_id === null ? null : Position::acrossCompanies()->whereKey($employee->position_id)->value('name'),
            'department' => $employee?->department_id === null ? null : Department::acrossCompanies()->whereKey($employee->department_id)->value('name'),
            'company' => $employee === null ? null : Company::query()->whereKey($employee->company_id)->value('code'),
        ];
    }

    private function departmentSupervisor(?string $name, Employee $leaver): ?Employee
    {
        if ($name === null) {
            return null;
        }

        $department = Department::acrossCompanies()
            ->where('company_id', $leaver->company_id)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->first();

        return $department?->supervisor_employee_id === null
            ? null
            : Employee::acrossCompanies()->with('user')->find($department->supervisor_employee_id);
    }

    private function canAct(?User $user, Employee $leaver): bool
    {
        return $user !== null && $user->is_active && $user->isNot($leaver->user);
    }
}
