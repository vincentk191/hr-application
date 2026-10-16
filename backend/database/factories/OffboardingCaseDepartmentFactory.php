<?php

namespace Database\Factories;

use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffboardingCaseDepartment>
 */
class OffboardingCaseDepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offboarding_case_id' => OffboardingCase::factory(),
            'company_id' => fn (array $attributes) => OffboardingCase::acrossCompanies()->whereKey($attributes['offboarding_case_id'])->value('company_id'),
            'name' => fake()->word(),
            'not_involved' => false,
        ];
    }
}
