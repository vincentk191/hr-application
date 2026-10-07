<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 week', '+2 months');
        $end = (clone $start)->modify('+2 days');

        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attributes): int => Employee::acrossCompanies()
                ->whereKey($attributes['employee_id'])
                ->value('company_id'),
            'leave_type' => LeaveType::Annual,
            'start_on' => $start,
            'end_on' => $end,
            'days' => 3,
            'reason' => $this->faker->sentence(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes): array => [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
        ]);
    }
}
