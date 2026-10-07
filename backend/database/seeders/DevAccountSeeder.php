<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Local accounts and sample staff for trying the dashboard by hand.
 *
 * Gives one account of each kind, because the difference is the whole point of the company
 * switcher: a parent-wide account may switch companies and view them combined, a pinned
 * account is locked to one (CORE-USR-004, CORE-NAV-003/004).
 *
 * Local only — never run this where real data lives.
 */
class DevAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DevAccountSeeder is for local use only.');

            return;
        }

        $this->call([
            CompanySeeder::class,
            RoleSeeder::class,
        ]);

        $companies = Company::orderBy('id')->get();
        $first = $companies->firstOrFail();

        $accounts = [
            ['Group HR', 'group@hr.test', null, Role::HrAdmin],
            ['HR '.$first->name, 'hr.a@hr.test', $first->id, Role::HrAdmin],
            ['System Admin', 'admin@hr.test', null, Role::SystemAdmin],
            ['Supervisor '.$first->name, 'supervisor.a@hr.test', $first->id, Role::Supervisor],
        ];

        foreach ($accounts as [$name, $email, $companyId, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'company_id' => $companyId,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role->value]);
        }

        // A few employees per company, so switching companies visibly changes the data.
        foreach ($companies as $company) {
            if (Employee::acrossCompanies()->where('company_id', $company->id)->exists()) {
                continue;
            }

            Employee::factory()->count(5)->forCompany($company)->create();
        }

        // Give the first company a supervisor with a login, and put its staff under them, so
        // a leave request there builds the full two-step chain (supervisor then HR) rather
        // than falling back to HR alone.
        $supervisorUser = User::where('email', 'supervisor.a@hr.test')->first();

        if ($supervisorUser !== null) {
            $supervisor = Employee::acrossCompanies()
                ->where('company_id', $first->id)
                ->where('user_id', $supervisorUser->id)
                ->first()
                ?? Employee::acrossCompanies()
                    ->where('company_id', $first->id)
                    ->whereNull('user_id')
                    ->first();

            if ($supervisor !== null) {
                $supervisor->update(['user_id' => $supervisorUser->id]);

                Employee::acrossCompanies()
                    ->where('company_id', $first->id)
                    ->whereKeyNot($supervisor->id)
                    ->whereNull('manager_id')
                    ->update(['manager_id' => $supervisor->id]);
            }
        }

        $this->command->newLine();
        $this->command->line('  All accounts use the password: password');
        $this->command->line('  group@hr.test         HR Admin, parent-wide   (switcher unlocked)');
        $this->command->line('  hr.a@hr.test          HR Admin, '.$first->name.'   (switcher locked)');
        $this->command->line('  admin@hr.test         System Admin, parent-wide');
        $this->command->line('  supervisor.a@hr.test  Supervisor, '.$first->name.'  (read-only)');
        $this->command->newLine();
    }
}
