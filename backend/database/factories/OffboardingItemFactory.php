<?php

namespace Database\Factories;

use App\Enums\OffboardingItemKind;
use App\Models\OffboardingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffboardingItem>
 */
class OffboardingItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'kind' => OffboardingItemKind::Physical,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
