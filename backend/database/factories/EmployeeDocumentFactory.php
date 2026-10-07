<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attributes): int => Employee::acrossCompanies()
                ->whereKey($attributes['employee_id'])
                ->value('company_id'),
            'category' => DocumentCategory::Contract,
            'title' => $this->faker->words(3, true),
            'is_restricted' => false,
            'expires_on' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes): array => [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn (array $attributes): array => ['is_restricted' => true]);
    }

    public function expiringOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['expires_on' => $date]);
    }
}
