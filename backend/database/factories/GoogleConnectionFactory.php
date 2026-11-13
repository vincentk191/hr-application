<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\GoogleConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleConnection>
 */
class GoogleConnectionFactory extends Factory
{
    /**
     * A connected, working account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'google_email' => 'recruitment@example.test',
            'refresh_token' => 'refresh-'.$this->faker->unique()->lexify('????????'),
            'access_token' => null,
            'access_token_expires_at' => null,
            'connected_at' => now(),
        ];
    }

    /**
     * Google refused the refresh token: HR has to connect again.
     */
    public function needsReconnect(): static
    {
        return $this->state(fn (): array => ['needs_reconnect_at' => now()]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }
}
