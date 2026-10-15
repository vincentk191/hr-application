<?php

namespace Database\Factories;

use App\Enums\OffboardingExitType;
use App\Models\Employee;
use App\Models\OffboardingCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffboardingCase>
 */
class OffboardingCaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attributes) => Employee::acrossCompanies()->whereKey($attributes['employee_id'])->value('company_id'),
            'exit_type' => OffboardingExitType::Resign,
            'notice_on' => '2026-11-02',
            'last_working_on' => '2026-12-02',
            'reason' => 'Pindah kota',
            'compensation_months' => 0,
        ];
    }
}
