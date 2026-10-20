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
        $this->call([
            CompanySeeder::class,
            RoleSeeder::class,
        ]);

        // A throwaway account for trying the app by hand; never on a real server, where it would be a known login
        // (and where the faker package its factory needs is not installed).
        if (app()->isProduction()) {
            return;
        }

        // A parent-wide account: no company, so it may switch and view combined.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
