<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives every company a full Management → Supervisor → Employee line, for trying the
 * dashboard by hand (CORE-ORG-003, CORE-RBA-004).
 *
 * The shape, per company:
 *  - one Management employee, who has no superior;
 *  - up to {@see self::MAX_SUPERVISORS} Supervisors, all reporting to Management;
 *  - every other employee reporting to a Supervisor, never straight to Management.
 *
 * Each department has a head: Management for the first department and for any department
 * no supervisor sits in, otherwise one of that department's supervisors.
 *
 * Idempotent. It reuses the management and supervisor accounts that already exist, only adds
 * what is missing to reach the target headcount, and leaves an employee's current supervisor
 * alone when that supervisor is still a valid one. It does not touch company names, so it is
 * safe to run on a database whose companies were renamed by hand.
 *
 * Local only — never run this where real data lives.
 */
class OrgHierarchySeeder extends Seeder
{
    use WithoutModelEvents;

    public const MAX_SUPERVISORS = 10;

    /**
     * The director of a company whose real person is known, keyed by company code.
     *
     * @var array<string, string>
     */
    private const DIRECTORS = [
        'CMP-C' => 'Brad Mayert V',
    ];

    /**
     * The departments every company gets. The first is headed by Management.
     *
     * @var list<string>
     */
    private const DEPARTMENTS = ['Operasional', 'Gudang', 'Pengiriman', 'Finance', 'HR', 'Administrasi'];

    /**
     * How many supervisors and how many staff (everyone below the supervisors) each company
     * is topped up to, in company order. A company beyond the list uses the last entry.
     *
     * @var list<array{supervisors: int, staff: int}>
     */
    private const SIZES = [
        ['supervisors' => 8, 'staff' => 30],
        ['supervisors' => 6, 'staff' => 20],
        ['supervisors' => 10, 'staff' => 40],
    ];

    /** Every n-th new staff member also gets a login, so the Employee role has accounts to try. */
    private const STAFF_LOGIN_EVERY = 5;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('OrgHierarchySeeder is for local use only.');

            return;
        }

        $companies = Company::orderBy('id')->get();

        if ($companies->isEmpty()) {
            $this->command->error('No companies yet. Run CompanySeeder first.');

            return;
        }

        $this->call(RoleSeeder::class);

        $this->command->newLine();
        $this->command->line('  All accounts use the password: password');

        foreach ($companies->values() as $index => $company) {
            $size = self::SIZES[min($index, count(self::SIZES) - 1)];

            DB::transaction(fn () => $this->seedCompany($company, $size['supervisors'], $size['staff']));
        }

        $this->command->newLine();
    }

    private function seedCompany(Company $company, int $supervisorTarget, int $staffTarget): void
    {
        $letter = strtolower(substr($company->code, -1));
        $supervisorTarget = min($supervisorTarget, self::MAX_SUPERVISORS);

        $departments = $this->departments($company);
        $management = $this->management($company, $letter);
        $supervisors = $this->supervisors($company, $letter, $supervisorTarget);

        $this->placeManagement($management, $departments->first());
        $this->placeSupervisors($supervisors, $management, $departments);
        $this->placeStaff($company, $letter, $management, $supervisors, $staffTarget);
        $this->nameDepartmentHeads($company, $management, $supervisors);

        $this->command->line(sprintf(
            '  %s: %s (management), %d supervisors, %d staff',
            $company->name,
            $management->full_name,
            $supervisors->count(),
            $this->staffQuery($company, $management, $supervisors)->count(),
        ));
    }

    /**
     * @return Collection<int, Department>
     */
    private function departments(Company $company): Collection
    {
        return (new Collection(self::DEPARTMENTS))->map(
            fn (string $name): Department => Department::acrossCompanies()->firstOrCreate([
                'company_id' => $company->id,
                'name' => $name,
            ]),
        );
    }

    /**
     * The company's single Management employee: the one that already holds the role, else the
     * known director, else a new person.
     */
    private function management(Company $company, string $letter): Employee
    {
        $existing = $this->employeesIn($company)
            ->whereHas('user', fn ($users) => $users->role(Role::Management->value))
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $director = self::DIRECTORS[$company->code] ?? null;

        $employee = $director === null
            ? null
            : $this->employeesIn($company)->where('full_name', $director)->first();

        $email = $director === null ? "management.{$letter}@hr.test" : 'brad.mayert@hr.test';

        return $this->giveLogin($employee ?? $this->newEmployee($company, $director), $email, Role::Management);
    }

    /**
     * The company's supervisors: those that already hold the role, topped up to the target.
     *
     * @return Collection<int, Employee>
     */
    private function supervisors(Company $company, string $letter, int $target): Collection
    {
        $supervisors = $this->employeesIn($company)
            ->whereHas('user', fn ($users) => $users->role(Role::Supervisor->value))
            ->orderBy('id')
            ->limit($target)
            ->get();

        for ($n = 1; $supervisors->count() < $target; $n++) {
            $email = "supervisor{$n}.{$letter}@hr.test";

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $supervisors->push($this->giveLogin($this->newEmployee($company), $email, Role::Supervisor));
        }

        return $supervisors->values();
    }

    private function placeManagement(Employee $management, Department $department): void
    {
        $management->update([
            'manager_id' => null,
            'department_id' => $department->id,
            'position_id' => $this->position($department, 'Direktur')->id,
        ]);
    }

    /**
     * Spread the supervisors over the departments in turn, each reporting to Management.
     *
     * @param  Collection<int, Employee>  $supervisors
     * @param  Collection<int, Department>  $departments
     */
    private function placeSupervisors(Collection $supervisors, Employee $management, Collection $departments): void
    {
        foreach ($supervisors as $index => $supervisor) {
            $department = $departments[$index % $departments->count()];

            $supervisor->update([
                'manager_id' => $management->id,
                'department_id' => $department->id,
                'position_id' => $this->position($department, 'Supervisor '.$department->name)->id,
            ]);
        }
    }

    /**
     * Everyone who is neither Management nor a Supervisor goes under a supervisor. Staff whose
     * current supervisor is still valid stay where they are; the rest, and any new staff, go to
     * whichever supervisor has the fewest people.
     *
     * @param  Collection<int, Employee>  $supervisors
     */
    private function placeStaff(Company $company, string $letter, Employee $management, Collection $supervisors, int $target): void
    {
        $staff = $this->staffQuery($company, $management, $supervisors)->orderBy('id')->get();

        for ($n = 1; $staff->count() < $target; $n++) {
            $employee = $this->newEmployee($company);

            if ($n % self::STAFF_LOGIN_EVERY === 0) {
                $this->giveLogin($employee, $this->freeEmail("employee%d.{$letter}@hr.test", $n), Role::Employee);
            }

            $staff->push($employee);
        }

        $load = $supervisors->mapWithKeys(
            fn (Employee $supervisor): array => [$supervisor->id => $staff->where('manager_id', $supervisor->id)->count()],
        )->all();

        foreach ($staff as $employee) {
            if (! array_key_exists((int) $employee->manager_id, $load)) {
                asort($load);
                $employee->manager_id = array_key_first($load);
                $load[$employee->manager_id]++;
            }

            $supervisor = $supervisors->firstWhere('id', $employee->manager_id);

            if ($employee->department_id === null) {
                $employee->department_id = $supervisor->department_id;
            }

            if ($employee->position_id === null) {
                $department = Department::acrossCompanies()->findOrFail($employee->department_id);
                $employee->position_id = $this->position($department, 'Staf '.$department->name)->id;
            }

            $employee->save();
        }
    }

    /**
     * Management heads its own department and any department without a supervisor; every
     * other department, including ones the company had before this ran, is headed by its first
     * supervisor.
     *
     * @param  Collection<int, Employee>  $supervisors
     */
    private function nameDepartmentHeads(Company $company, Employee $management, Collection $supervisors): void
    {
        Department::acrossCompanies()->where('company_id', $company->id)->orderBy('id')->get()
            ->each(function (Department $department) use ($management, $supervisors): void {
                $head = $department->id === $management->department_id
                    ? null
                    : $supervisors->firstWhere('department_id', $department->id);

                $department->update(['supervisor_employee_id' => ($head ?? $management)->id]);
            });
    }

    /**
     * @return Builder<Employee>
     */
    private function employeesIn(Company $company): Builder
    {
        return Employee::acrossCompanies()->where('company_id', $company->id);
    }

    /**
     * @param  Collection<int, Employee>  $supervisors
     * @return Builder<Employee>
     */
    private function staffQuery(Company $company, Employee $management, Collection $supervisors): Builder
    {
        return $this->employeesIn($company)->whereNotIn('id', [$management->id, ...$supervisors->modelKeys()]);
    }

    private function position(Department $department, string $name): Position
    {
        return Position::acrossCompanies()->firstOrCreate([
            'company_id' => $department->company_id,
            'department_id' => $department->id,
            'name' => $name,
        ]);
    }

    /**
     * A new data-only dossier record, numbered after the company's highest existing number.
     */
    private function newEmployee(Company $company, ?string $name = null): Employee
    {
        $last = $this->employeesIn($company)->pluck('employee_number')
            ->map(fn (string $number): int => (int) preg_replace('/\D/', '', $number))
            ->max() ?? 0;

        return Employee::factory()->forCompany($company)->create(array_filter([
            'employee_number' => sprintf('EMP-%04d', $last + 1),
            'full_name' => $name,
        ]));
    }

    /**
     * Create a login for an employee and give it the role, unless they already have one.
     */
    private function giveLogin(Employee $employee, string $email, Role $role): Employee
    {
        if ($employee->user_id === null) {
            $user = User::factory()->create([
                'name' => $employee->full_name,
                'email' => $email,
                'company_id' => $employee->company_id,
            ]);

            $employee->update(['user_id' => $user->id, 'email' => $email]);
        }

        $employee->user->syncRoles([$role->value]);

        $this->command->line(sprintf('  %-26s %s, %s', $employee->user->email, $role->label(), $employee->full_name));

        return $employee;
    }

    /**
     * The first unused email from a pattern with one %d, counting up from $from.
     */
    private function freeEmail(string $pattern, int $from): string
    {
        while (User::where('email', sprintf($pattern, $from))->exists()) {
            $from++;
        }

        return sprintf($pattern, $from);
    }
}
