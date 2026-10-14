<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * A dossier record with no login, which is the common case: most staff are data-only
     * (CORE-RBA-006). Use `withLogin()` when the employee needs an account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'employee_number' => $this->faker->unique()->numerify('EMP-####'),
            'full_name' => $this->faker->name(),
            'national_id' => $this->faker->unique()->numerify('################'),
            'employment_status' => EmploymentStatus::Active,
            'employment_type' => EmploymentType::Permanent,
            'joined_on' => $this->faker->dateTimeBetween('-8 years', '-1 month'),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $company->id,
        ]);
    }

    /**
     * Give the employee a login in their own company.
     */
    public function withLogin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => User::factory()->state([
                'company_id' => $attributes['company_id'] ?? null,
            ]),
        ]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $department->company_id,
            'department_id' => $department->id,
        ]);
    }

    public function reportingTo(Employee $manager): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $manager->company_id,
            'manager_id' => $manager->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'employment_status' => EmploymentStatus::Inactive,
            'exited_on' => now()->subMonth(),
        ]);
    }
}
