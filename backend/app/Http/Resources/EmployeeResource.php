<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employeeNumber' => $this->employee_number,
            'fullName' => $this->full_name,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'position' => $this->whenLoaded('position', fn () => $this->position?->name),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager?->full_name),
            'status' => $this->employment_status->value,
        ];
    }
}
