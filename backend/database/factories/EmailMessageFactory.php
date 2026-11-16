<?php

namespace Database\Factories;

use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Models\Company;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    /**
     * An email waiting to be sent now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'kind' => EmailKind::Test,
            'to_email' => $this->faker->unique()->safeEmail(),
            'subject' => 'Email uji',
            'body' => 'Halo, ini email uji.',
            'status' => EmailStatus::Pending,
            'send_at' => now(),
        ];
    }

    public function status(EmailStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }
}
