<?php

namespace Database\Factories;

use App\Enums\OffboardingItemKind;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffboardingCaseItem>
 */
class OffboardingCaseItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offboarding_case_department_id' => OffboardingCaseDepartment::factory(),
            'offboarding_case_id' => fn (array $attributes) => OffboardingCaseDepartment::acrossCompanies()->whereKey($attributes['offboarding_case_department_id'])->value('offboarding_case_id'),
            'company_id' => fn (array $attributes) => OffboardingCaseDepartment::acrossCompanies()->whereKey($attributes['offboarding_case_department_id'])->value('company_id'),
            'name' => fake()->sentence(3),
            'kind' => OffboardingItemKind::Physical,
            'is_adhoc' => false,
        ];
    }
}
