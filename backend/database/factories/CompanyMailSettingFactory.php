<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyMailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyMailSetting>
 */
class CompanyMailSettingFactory extends Factory
{
    /**
     * A working, enabled mailbox.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'host' => 'mail.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'recruitment@example.test',
            'password' => 'mailbox-password',
            'from_address' => 'recruitment@example.test',
            'from_name' => 'HR PT Contoh',
            'reply_to' => null,
            'is_enabled' => true,
        ];
    }

    /**
     * Saved but not switched on: nothing is sent through it.
     */
    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }
}
