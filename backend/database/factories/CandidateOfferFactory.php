<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateOffer>
 */
class CandidateOfferFactory extends Factory
{
    /**
     * An offer with no workflow yet; the actions start it (`ProposeOffers`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'requisition_id' => fn (array $attributes) => Candidate::acrossCompanies()->whereKey($attributes['candidate_id'])->value('requisition_id'),
            'company_id' => fn (array $attributes) => Candidate::acrossCompanies()->whereKey($attributes['candidate_id'])->value('company_id'),
            'proposed_at' => now(),
        ];
    }
}
