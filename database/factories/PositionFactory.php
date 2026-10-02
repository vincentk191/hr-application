<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $department = Department::factory();

        return [
            'department_id' => $department,
            // Keep the position in the same company as its department.
            'company_id' => fn (array $attributes): int => Department::acrossCompanies()
                ->whereKey($attributes['department_id'])
                ->value('company_id'),
            'name' => $this->faker->unique()->jobTitle(),
        ];
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes): array => [
            'department_id' => $department->id,
            'company_id' => $department->company_id,
        ]);
    }
}
