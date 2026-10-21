<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first System Admin on a new server (CORE-USR-007).
 *
 * The account belongs to no company, so it can create the companies and then each company's first
 * HR Admin from the admin panel; nothing else is created for it, not even a company. It is built
 * from config/initial_admin.php, has no default password, and must change the one it is given at its
 * first sign-in. An account that already exists with that email is left exactly as it is, so running
 * this again never resets a password or changes a role.
 */
class SystemAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The shortest password the admin panel accepts for an account, repeated here.
     */
    private const MINIMUM_PASSWORD_LENGTH = 12;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $name = trim((string) config('initial_admin.name'));
        $email = Str::lower(trim((string) config('initial_admin.email')));
        $password = (string) config('initial_admin.password');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            $this->command->error('No System Admin was created. Set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD (at least '.self::MINIMUM_PASSWORD_LENGTH.' characters), then run this seeder again.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command->info("An account for {$email} already exists and was left unchanged.");

            return;
        }

        $user = new User([
            'name' => $name !== '' ? $name : 'Admin Sistem',
            'email' => $email,
            'password' => $password,
            'company_id' => null,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'must_change_password' => true,
            'is_active' => true,
        ])->save();

        $user->assignRole(Role::SystemAdmin->value);

        $this->command->info("System Admin {$email} created. It must change its password at the first sign-in.");
    }
}
