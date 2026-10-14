<?php

namespace Database\Factories;

use App\Enums\ProfileChangeStatus;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileChangeRequest>
 */
class ProfileChangeRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->withLogin(),
            'company_id' => fn (array $attributes) => Employee::acrossCompanies()->whereKey($attributes['employee_id'])->value('company_id'),
            'requested_by_user_id' => fn (array $attributes) => Employee::acrossCompanies()->whereKey($attributes['employee_id'])->value('user_id'),
            'status' => ProfileChangeStatus::Pending,
            'changes' => ['phone' => ['before' => null, 'after' => '0812-1111-2222']],
        ];
    }
}
