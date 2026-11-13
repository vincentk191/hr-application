<?php

namespace App\Support\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Position;
use App\Models\Requisition;

/**
 * What the employee form is filled in with when a hire becomes an employee (REC-FIL-006): the candidate's name and contact,
 * and the company, department, Cabang, position, contract, direct superior, start date and starting salary of the
 * requisition and the accepted offer. What is not known (the employee number, the NIK, and a Cabang when the requisition
 * named none) is left for HR to fill in.
 */
class HireProfile
{
    /**
     * @return array{companyId: int, name: string, email: string|null, phone: string|null, branchId: int|null, departmentId: int, positionId: int|null, jobTitle: string, employmentType: string|null, joinedOn: string|null, managerId: int|null, baseSalary: int|null, probationMonths: int|null, position: string}
     */
    public function defaults(Requisition $requisition, CandidateOffer $offer): array
    {
        $candidate = $offer->candidate;

        return [
            'companyId' => $requisition->company_id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'branchId' => $requisition->branch_id,
            'departmentId' => $requisition->department_id,
            'positionId' => $this->positionOf($requisition)?->id,
            'jobTitle' => $requisition->position_name,
            'employmentType' => $requisition->employment_type?->value,
            'joinedOn' => $offer->start_on?->toDateString(),
            'managerId' => $requisition->manager_employee_id,
            'baseSalary' => $offer->salary,
            'probationMonths' => $offer->probation_months,
            'position' => $requisition->position_name,
        ];
    }

    /**
     * The company's position of the same name, preferring the requisition's department; none when the company has no such
     * position (the requisition's name is free text), in which case the form leaves it to HR and keeps the name as the job title.
     */
    private function positionOf(Requisition $requisition): ?Position
    {
        $matches = Position::acrossCompanies()
            ->where('company_id', $requisition->company_id)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($requisition->position_name))])
            ->get();

        return $matches->firstWhere('department_id', $requisition->department_id) ?? $matches->first();
    }
}
