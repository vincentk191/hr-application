<?php

namespace App\Support\Employees;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\Religion;
use App\Enums\TaxMethod;
use App\Enums\Zona;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\Pdf\CompanyLogo;
use Illuminate\Support\Carbon;

/**
 * What the "Formulir data induk karyawan" says, worked out from an employee and their company, for the view that draws it.
 *
 * The form is for the new employee to complete and sign, so it carries what HR already knows and leaves the rest as empty
 * lines; salary is never printed (payroll is not in the dossier the employee holds, and the form's own pay block stays empty,
 * as on the paper one). Choices are returned as `[label, ticked]` pairs, in the order the paper form prints them.
 *
 * Reads across companies on purpose: it is also run from the queue, where no company is active.
 */
class InductionFormData
{
    /**
     * @return array<string, mixed>
     */
    public function for(Employee $employee): array
    {
        $company = Company::query()->find($employee->company_id);
        $department = $employee->department_id === null ? null : Department::acrossCompanies()->find($employee->department_id);
        $position = $employee->position_id === null ? null : Position::acrossCompanies()->find($employee->position_id);

        return [
            'company' => $this->company($company),
            'date' => Carbon::now()->format('d/m/Y'),
            'branches' => $this->branches($employee),
            'employee' => $this->employee($employee, $department?->name, $position?->name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function company(?Company $company): array
    {
        return [
            'name' => $company?->name,
            'address' => $company?->address,
            'logo' => CompanyLogo::dataUri($company),
            'npwp16' => $company?->npwp_16,
            'taxMethods' => $this->choices(TaxMethod::cases(), $company?->tax_method, fn (TaxMethod $method): string => $method->label()),
        ];
    }

    /**
     * Every office of the company with its own numbers, the employee's marked: the paper form lists them all and ticks one.
     *
     * @return list<array{name: string, nitku: string|null, npwp15: string|null, bpjsEmployment: string|null, bpjsHealth: string|null, mine: bool}>
     */
    private function branches(Employee $employee): array
    {
        $branches = Branch::acrossCompanies()
            ->where('company_id', $employee->company_id)
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $branch): array => [
                'name' => $branch->name,
                'nitku' => $branch->nitku,
                'npwp15' => $branch->npwp_15,
                'bpjsEmployment' => $branch->bpjs_employment_number,
                'bpjsHealth' => $branch->bpjs_health_number,
                'mine' => $branch->id === $employee->branch_id,
            ])
            ->all();

        return array_values($branches);
    }

    /**
     * @return array<string, mixed>
     */
    private function employee(Employee $employee, ?string $department, ?string $position): array
    {
        $branch = Branch::acrossCompanies()->find($employee->branch_id);

        // Only two marital statuses have a box; anyone else is written out beside them so nothing is lost.
        $maritalBoxes = [MaritalStatus::Single, MaritalStatus::Married];
        $maritalOther = $employee->marital_status !== null && ! in_array($employee->marital_status, $maritalBoxes, true)
            ? $employee->marital_status->label()
            : null;

        return [
            'number' => $employee->employee_number,
            'status' => $this->choices(
                [EmploymentType::Probation, EmploymentType::Permanent, EmploymentType::Contract],
                $employee->employment_type,
                fn (EmploymentType $type): string => mb_strtoupper($type->label()),
            ),
            'joinedOn' => $employee->joined_on?->format('d/m/Y'),
            'branch' => $branch?->name,
            'division' => $department,
            'jobTitle' => $employee->job_title,
            'position' => $position,
            'zona' => $this->choices(Zona::cases(), $employee->zona, fn (Zona $zona): string => mb_strtoupper($zona->label())),

            'name' => $employee->full_name,
            'birthPlace' => $employee->birth_place,
            'birthDate' => $employee->birth_date?->format('d/m/Y'),
            'gender' => $this->choices(
                [Gender::Male, Gender::Female],
                $employee->gender,
                fn (Gender $gender): string => $gender === Gender::Male ? 'PRIA' : 'WANITA',
            ),
            'religion' => $this->choices(
                [Religion::Islam, Religion::Christian, Religion::Catholic, Religion::Hindu, Religion::Buddhist, Religion::Confucian],
                $employee->religion,
                fn (Religion $religion): string => mb_strtoupper($religion->label()),
            ),
            'marital' => $this->choices($maritalBoxes, $employee->marital_status, fn (MaritalStatus $status): string => mb_strtoupper($status->label())),
            'maritalOther' => $maritalOther,
            'dependants' => $employee->ptkp_status?->value,
            'motherName' => $employee->mother_name,
            'familyCard' => $employee->family_card_number,
            'idNumber' => $this->idNumber($employee),

            'phone' => $employee->phone,
            'email' => $employee->email,

            'idAddress' => $this->street($employee->id_address, $employee->id_block, $employee->id_house_number),
            'idRtRw' => $this->join('/', [$employee->id_rt, $employee->id_rw]),
            'idCity' => $employee->id_city,
            'idVillage' => $employee->id_village,
            'idProvince' => $employee->id_province,
            'idDistrict' => $employee->id_district,
            'idPostal' => $employee->id_postal_code,

            'homeAddress' => $employee->address,
            'homeCity' => $employee->residence_city,
            'homeVillage' => $employee->residence_village,
            'homeProvince' => $employee->residence_province,
            'homeDistrict' => $employee->residence_district,
            'homePostal' => $employee->residence_postal_code,

            'bankNumber' => $employee->bank_account_number,
            'bankHolder' => $employee->bank_account_holder,
            'bankName' => $employee->bank_name,
            'bankBranch' => $employee->bank_branch,

            'emergencyRelation' => $employee->emergency_contact_relation,
            'emergencyName' => $employee->emergency_contact_name,
            'emergencyPhone' => $employee->emergency_contact_phone,

            'education' => $employee->education_level,

            'bpjsEmploymentCard' => $employee->bpjs_employment_number,
            'bpjsHealthCard' => $employee->bpjs_health_number,
        ];
    }

    /**
     * The paper form has one line for "KTP / NPWP 16": the NIK, with the NPWP beside it only when it is a different number.
     */
    private function idNumber(Employee $employee): ?string
    {
        if ($employee->tax_number !== null && $employee->tax_number !== $employee->national_id) {
            return $this->join(' / ', [$employee->national_id, $employee->tax_number]);
        }

        return $employee->national_id;
    }

    private function street(?string $street, ?string $block, ?string $number): ?string
    {
        $extra = $this->join(' ', [$block === null ? null : "Blok {$block}", $number === null ? null : "No. {$number}"]);

        return $this->join(', ', [$street, $extra]);
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function join(string $glue, array $parts): ?string
    {
        $filled = array_values(array_filter($parts, fn (?string $part): bool => $part !== null && $part !== ''));

        return $filled === [] ? null : implode($glue, $filled);
    }

    /**
     * Options as `[label, ticked]`, in the order given.
     *
     * @template T of \UnitEnum
     *
     * @param  list<T>  $options
     * @param  callable(T): string  $label
     * @return list<array{0: string, 1: bool}>
     */
    private function choices(array $options, ?\UnitEnum $chosen, callable $label): array
    {
        return array_map(fn (\UnitEnum $option): array => [$label($option), $option === $chosen], $options);
    }
}
