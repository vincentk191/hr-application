<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // A real server gets the roles and one company-less System Admin, who then creates the real companies
        // and each company's first HR Admin in the admin panel. The placeholder companies are for local work only.
        if (app()->isProduction()) {
            $this->call([
                RoleSeeder::class,
                SystemAdminSeeder::class,
            ]);

            return;
        }

        $this->call([
            CompanySeeder::class,
            RoleSeeder::class,
        ]);

        // A throwaway account for trying the app by hand, with a parent-wide scope: no company, so it may switch
        // and view combined. Never on a real server, where it would be a known login.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
