<?php

namespace Database\Factories;

use App\Enums\OffboardingFileKind;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffboardingCaseFile>
 */
class OffboardingCaseFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offboarding_case_id' => OffboardingCase::factory(),
            'company_id' => fn (array $attributes) => OffboardingCase::acrossCompanies()->whereKey($attributes['offboarding_case_id'])->value('company_id'),
            'kind' => OffboardingFileKind::ResignationLetter,
            'path' => 'offboarding-files/test/'.fake()->uuid().'.pdf',
            'original_name' => 'surat.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ];
    }
}
