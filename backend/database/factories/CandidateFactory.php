<?php

namespace Database\Factories;

use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\Requisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * A candidate shortlisted from Glints.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '08'.$this->faker->numerify('##########');

        return [
            'requisition_id' => Requisition::factory(),
            'company_id' => fn (array $attributes) => Requisition::acrossCompanies()->whereKey($attributes['requisition_id'])->value('company_id'),
            'name' => $this->faker->name(),
            'phone' => $phone,
            'phone_key' => Candidate::phoneKey($phone),
            'email' => $this->faker->unique()->safeEmail(),
            'source' => CandidateSource::Glints,
        ];
    }
}
