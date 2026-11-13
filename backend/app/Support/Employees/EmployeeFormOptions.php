<?php

namespace App\Support\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\Zona;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\CompanyContext;

/**
 * What every employee form offers to choose from (EMP-ADD): the companies, Cabang, departments, positions and managers in
 * view, and the fixed lists. Shared by the add and edit pages and by the form that makes a profile from a hire.
 */
class EmployeeFormOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        return [
            // Only offered while every company is being viewed: a new record then needs one chosen.
            'companies' => app(CompanyContext::class)->companyId() === null
                ? Company::orderBy('name')->get(['id', 'name'])
                : null,
            'branches' => Branch::orderBy('name')->get(['id', 'name', 'company_id']),
            'departments' => Department::orderBy('name')->get(['id', 'name', 'company_id']),
            'positions' => Position::orderBy('name')->get(['id', 'name', 'department_id', 'company_id']),
            'managers' => Employee::query()->active()->orderBy('full_name')->get(['id', 'full_name', 'employee_number', 'company_id'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'company_id' => $employee->company_id,
                    'label' => "{$employee->full_name} ({$employee->employee_number})",
                ]),
            // A profile is only closed by offboarding, so the forms offer nothing but active.
            'employmentStatuses' => [['value' => EmploymentStatus::Active->value, 'label' => EmploymentStatus::Active->label()]],
            'employmentTypes' => array_map(
                fn (EmploymentType $type): array => ['value' => $type->value, 'label' => $type->label()],
                EmploymentType::cases(),
            ),
            'zonas' => array_map(
                fn (Zona $zona): array => ['value' => $zona->value, 'label' => $zona->label()],
                Zona::cases(),
            ),
            'genders' => array_map(fn (Gender $gender): array => ['value' => $gender->value, 'label' => $gender->label()], Gender::cases()),
            'religions' => array_map(fn (Religion $religion): array => ['value' => $religion->value, 'label' => $religion->label()], Religion::cases()),
            'maritalStatuses' => array_map(fn (MaritalStatus $status): array => ['value' => $status->value, 'label' => $status->label()], MaritalStatus::cases()),
            'ptkpStatuses' => array_map(fn (PtkpStatus $status): array => ['value' => $status->value, 'label' => $status->label()], PtkpStatus::cases()),
        ];
    }
}
