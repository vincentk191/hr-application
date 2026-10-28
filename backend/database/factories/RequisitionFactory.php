<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Requisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisition>
 */
class RequisitionFactory extends Factory
{
    /**
     * A requisition with every field the wizard asks for, ready to be sent. The target start is a fixed far-off
     * date, so a test does not start failing once a calendar date passes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'company_id' => fn (array $attributes) => Department::acrossCompanies()->whereKey($attributes['department_id'])->value('company_id'),
            'position_name' => $this->faker->randomElement(['Staf Gudang', 'Admin Keuangan', 'Supir', 'Staf HR']),
            'quota' => 2,
            'employment_type' => EmploymentType::Contract,
            'contract_months' => 12,
            'reason' => 'Volume kerja bertambah',
            'target_start_on' => now()->addMonths(2)->toDateString(),
            'about' => 'Kami mencari staf yang teliti.',
            'responsibilities' => [['title' => 'Operasional harian', 'items' => ['Menyiapkan barang keluar.']]],
            'benefits' => ['BPJS Kesehatan & BPJS Ketenagakerjaan.'],
            'checklist' => [
                'education' => ['Minimal SMA/SMK'],
                'experience' => [],
                'hard' => ['Menguasai Microsoft Excel'],
                'soft' => [],
                'bonus' => [],
            ],
            'draft_step' => 1,
        ];
    }
}
