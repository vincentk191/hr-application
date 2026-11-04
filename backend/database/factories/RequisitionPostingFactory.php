<?php

namespace Database\Factories;

use App\Enums\JobPlatform;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequisitionPosting>
 */
class RequisitionPostingFactory extends Factory
{
    /**
     * A listing on Glints, posted on a fixed past date.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisition_id' => Requisition::factory(),
            'platform' => JobPlatform::Glints,
            'url' => 'https://glints.com/id/opportunities/jobs/example',
            'posted_on' => '2026-11-02',
        ];
    }
}
