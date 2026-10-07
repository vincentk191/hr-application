<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The parent group's three operating companies (context.md §9.1).
     *
     * Names are placeholders until the real entity names are confirmed; the codes are what
     * downstream records and the switcher key off, so they are kept stable.
     */
    public function run(): void
    {
        $companies = [
            ['code' => 'CMP-A', 'name' => 'Company A'],
            ['code' => 'CMP-B', 'name' => 'Company B'],
            ['code' => 'CMP-C', 'name' => 'Company C'],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(
                ['code' => $company['code']],
                ['name' => $company['name'], 'is_active' => true],
            );
        }
    }
}
