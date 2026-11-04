<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One login per role, plus a second Staff, for checking what each account can and cannot do by hand.
 *
 * The line is Director → Manager → Supervisor → two Staff, all in the Design department of one
 * company. Director and Manager both hold the Management role ("directors and one level below"),
 * the Supervisor holds Supervisor, and the Staff hold Employee. The two Staff are peers under the
 * same Supervisor, so one can be used to confirm the other's record, leave and pay stay out of reach.
 * The System Admin and HR Admin are not made here; DevAccountSeeder has those.
 *
 * Also adds Marketing and Sales to every company, which already have Design.
 *
 * Idempotent: accounts are matched by email, and an existing one is re-pointed at its role and place
 * in the line rather than duplicated. Local only — never run this where real data lives.
 *
 *     php artisan db:seed --class=RoleTestAccountSeeder
 */
class RoleTestAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public const PASSWORD = 'password';

    /**
     * The home department of the test line, which already exists in the dev database.
     */
    public const HOME_DEPARTMENT = 'Design';

    /**
     * Departments added to every company.
     *
     * @var list<string>
     */
    private const EXTRA_DEPARTMENTS = ['Marketing', 'Sales'];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('RoleTestAccountSeeder is for local use only.');

            return;
        }

        $this->call([
            CompanySeeder::class,
            RoleSeeder::class,
        ]);

        DB::transaction(function (): void {
            Company::orderBy('id')->get()->each(fn (Company $company) => $this->addExtraDepartments($company));

            $this->seedLine($this->homeCompany());
        });
    }

    /**
     * The company that holds the Design department, else the first company.
     */
    private function homeCompany(): Company
    {
        $design = Department::acrossCompanies()->where('name', self::HOME_DEPARTMENT)->orderBy('id')->first();

        return $design === null
            ? Company::orderBy('id')->firstOrFail()
            : Company::findOrFail($design->company_id);
    }

    private function addExtraDepartments(Company $company): void
    {
        foreach (self::EXTRA_DEPARTMENTS as $name) {
            Department::acrossCompanies()->firstOrCreate(['company_id' => $company->id, 'name' => $name]);
        }
    }

    private function seedLine(Company $company): void
    {
        $design = Department::acrossCompanies()->firstOrCreate([
            'company_id' => $company->id,
            'name' => self::HOME_DEPARTMENT,
        ]);

        $director = $this->person($company, $design, 'Dewi Direktur', 'director@hr.test', Role::Management, 'Direktur', null);
        $manager = $this->person($company, $design, 'Made Manajer', 'manager@hr.test', Role::Management, 'Manajer Design', $director);
        $supervisor = $this->person($company, $design, 'Sari Supervisor', 'supervisor@hr.test', Role::Supervisor, 'Supervisor Design', $manager);
        $staffOne = $this->person($company, $design, 'Budi Staf', 'staff1@hr.test', Role::Employee, 'Staf Design', $supervisor);
        $staffTwo = $this->person($company, $design, 'Rina Staf', 'staff2@hr.test', Role::Employee, 'Staf Design', $supervisor);

        if ($design->supervisor_employee_id === null) {
            $design->update(['supervisor_employee_id' => $supervisor->id]);
        }

        $this->command->newLine();
        $this->command->line("  Company: {$company->name}, department: {$design->name}. All accounts use the password: ".self::PASSWORD);

        foreach ([$director, $manager, $supervisor, $staffOne, $staffTwo] as $employee) {
            $this->command->line(sprintf(
                '  %-20s %-12s %s%s',
                $employee->user->email,
                $employee->user->roles->first()->name,
                $employee->full_name,
                $employee->manager_id === null ? '' : ' (reports to '.Employee::acrossCompanies()->find($employee->manager_id)->full_name.')',
            ));
        }

        $this->command->newLine();
    }

    /**
     * Create or re-point one login and the dossier record behind it.
     */
    private function person(Company $company, Department $department, string $name, string $email, Role $role, string $positionName, ?Employee $manager): Employee
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'company_id' => $company->id,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([$role->value]);

        $position = Position::acrossCompanies()->firstOrCreate([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => $positionName,
        ]);

        $employee = Employee::acrossCompanies()->where('user_id', $user->id)->first()
            ?? Employee::factory()->forCompany($company)->create([
                'user_id' => $user->id,
                'employee_number' => $this->nextEmployeeNumber($company),
                'joined_on' => now()->subYears(2)->toDateString(),
            ]);

        $employee->update([
            'full_name' => $name,
            'email' => $email,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $manager?->id,
        ]);

        return $employee->load('user.roles');
    }

    private function nextEmployeeNumber(Company $company): string
    {
        $last = Employee::acrossCompanies()->where('company_id', $company->id)->pluck('employee_number')
            ->map(fn (string $number): int => (int) preg_replace('/\D/', '', $number))
            ->max() ?? 0;

        return sprintf('EMP-%04d', $last + 1);
    }
}
