<?php

namespace Database\Seeders;

use App\Actions\Recruitment\AdvanceToPosting;
use App\Actions\Recruitment\DecideRequisition;
use App\Actions\Recruitment\SaveRequisitionDraft;
use App\Actions\Recruitment\SubmitRequisition;
use App\Enums\EmploymentType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Requisitions at every point of the approval flow, for trying recruitment by hand.
 *
 * They are made with the same actions the pages use (save, send, approve, reject, move on to posting), so each one
 * has the real approval chain, comments, notifications and audit trail rather than a stage written into a column.
 * It builds on the accounts of RoleTestAccountSeeder (run first, here) and adds one HR Admin, hr@hr.test, because
 * the second approval step cannot be tried without one.
 *
 *  - Supervisor (Design): a half-written draft; sent and waiting for Management; Management approved and waiting
 *    for HR; approved by both and waiting for HR to move it to posting; already posted; rejected and back as a draft.
 *  - HR Admin: one for Marketing waiting for Management, and one for Sales that Management approved. HR never
 *    decides its own requisition, so that one waits for a second HR account.
 *
 * Idempotent: a requisition is matched by company, department and position name, and one that exists is left alone.
 * Works on a production server for a trial run (see RoleTestAccountSeeder for what that needs and the password
 * warning); it never creates a company.
 *
 *     php artisan db:seed --class=RecruitmentTestSeeder          (add --force on production)
 */
class RecruitmentTestSeeder extends Seeder
{
    /** What Management said the role may pay, in rupiah. */
    private const SALARY_MIN = 7_000_000;

    private const SALARY_MAX = 10_000_000;

    public function run(): void
    {
        $this->call(RoleTestAccountSeeder::class);

        $supervisorUser = User::where('email', 'supervisor@hr.test')->first();
        $management = User::where('email', 'manager@hr.test')->first();
        $supervisor = $supervisorUser === null ? null : Employee::acrossCompanies()->where('user_id', $supervisorUser->id)->first();

        if ($supervisorUser === null || $management === null || $supervisor === null) {
            $this->command->error('The test accounts do not exist yet: RoleTestAccountSeeder needs a company first.');

            return;
        }

        $company = Company::findOrFail($supervisor->company_id);
        $design = $this->department($company, RoleTestAccountSeeder::HOME_DEPARTMENT);
        $marketing = $this->department($company, 'Marketing');
        $sales = $this->department($company, 'Sales');
        $managerEmployee = Employee::acrossCompanies()->where('user_id', $management->id)->firstOrFail();
        $hr = $this->hrAdmin($company);

        $created = DB::transaction(fn (): int => collect([
            $this->draft($company, $design, $supervisorUser, $supervisor),
            $this->sent($company, $design, $supervisorUser, $supervisor, 'UI/UX Designer', 2),
            $this->managementApproved($company, $design, $supervisorUser, $supervisor, $management, 'Motion Designer', 1),
            $this->fullyApproved($company, $design, $supervisorUser, $supervisor, $management, $hr, 'Staf Produksi Kreatif', 1),
            $this->posted($company, $design, $supervisorUser, $supervisor, $management, $hr, 'Art Director', 1),
            $this->rejected($company, $design, $supervisorUser, $supervisor, $management),
            $this->sent($company, $marketing, $hr, $managerEmployee, 'Marketing Executive', 3),
            $this->managementApproved($company, $sales, $hr, $managerEmployee, $management, 'Sales Executive', 2),
        ])->filter()->count());

        $this->command->newLine();
        $this->command->line("  {$created} requisition(s) created in {$company->name} (existing ones were left alone).");
        $this->command->line('  Raise as supervisor@hr.test (Design) or hr@hr.test (any department); approve as manager@hr.test, then hr@hr.test.');
        $this->command->line('  hr@hr.test uses the password: '.RoleTestAccountSeeder::PASSWORD.' (the warning about the other test accounts applies).');
        $this->command->newLine();
    }

    private function department(Company $company, string $name): Department
    {
        return Department::acrossCompanies()->where('company_id', $company->id)->where('name', $name)->firstOrFail();
    }

    /**
     * The HR Admin the second approval step needs: one account, in an HR department of the company.
     */
    private function hrAdmin(Company $company): User
    {
        $department = Department::acrossCompanies()->firstOrCreate(['company_id' => $company->id, 'name' => 'HR']);

        return app(RoleTestAccountSeeder::class)
            ->person($company, $department, 'Hana HR', 'hr@hr.test', Role::HrAdmin, 'Admin HR', null)
            ->user;
    }

    /**
     * A requisition half written: step 01 filled in, the posting and the checklist not yet.
     */
    private function draft(Company $company, Department $department, User $by, Employee $reportsTo): ?Requisition
    {
        $position = 'Desainer Grafis';

        if ($this->exists($company, $department, $position)) {
            return null;
        }

        return $this->save($company, $department, $by, [
            'position_name' => $position,
            'quota' => 1,
            'employment_type' => EmploymentType::Contract,
            'contract_months' => 6,
            'manager_employee_id' => $reportsTo->id,
            'target_start_on' => now()->addMonths(2)->toDateString(),
            'reason' => 'Menambah kapasitas materi pemasaran.',
            'draft_step' => 2,
        ]);
    }

    /**
     * Sent, and waiting for Management to approve and set the salary expectation.
     */
    private function sent(Company $company, Department $department, User $by, Employee $reportsTo, string $position, int $quota): ?Requisition
    {
        if ($this->exists($company, $department, $position)) {
            return null;
        }

        $requisition = $this->save($company, $department, $by, $this->complete($position, $quota, $reportsTo));
        app(SubmitRequisition::class)->handle($requisition, $by);

        return $requisition;
    }

    /**
     * Management approved with a salary range; waiting for HR.
     */
    private function managementApproved(Company $company, Department $department, User $by, Employee $reportsTo, User $management, string $position, int $quota): ?Requisition
    {
        $requisition = $this->sent($company, $department, $by, $reportsTo, $position, $quota);

        if ($requisition === null) {
            return null;
        }

        app(DecideRequisition::class)->approve($requisition, $management, 'Disetujui, sesuai kebutuhan tim.', self::SALARY_MIN, self::SALARY_MAX);

        return $requisition;
    }

    /**
     * Approved by Management and HR: at 01 Diajukan, waiting for HR's "Lanjut ke Posting".
     */
    private function fullyApproved(Company $company, Department $department, User $by, Employee $reportsTo, User $management, User $hr, string $position, int $quota): ?Requisition
    {
        $requisition = $this->managementApproved($company, $department, $by, $reportsTo, $management, $position, $quota);

        if ($requisition === null) {
            return null;
        }

        app(DecideRequisition::class)->approve($requisition, $hr, null);

        return $requisition;
    }

    /**
     * Moved on to 02 Diposting.
     */
    private function posted(Company $company, Department $department, User $by, Employee $reportsTo, User $management, User $hr, string $position, int $quota): ?Requisition
    {
        $requisition = $this->fullyApproved($company, $department, $by, $reportsTo, $management, $hr, $position, $quota);

        if ($requisition === null) {
            return null;
        }

        app(AdvanceToPosting::class)->handle($requisition->refresh(), $hr);

        return $requisition;
    }

    /**
     * Rejected by Management with a reason, so it is back with its requester as a draft.
     */
    private function rejected(Company $company, Department $department, User $by, Employee $reportsTo, User $management): ?Requisition
    {
        $requisition = $this->sent($company, $department, $by, $reportsTo, 'Ilustrator Freelance', 1);

        if ($requisition === null) {
            return null;
        }

        app(DecideRequisition::class)->reject($requisition, $management, 'Anggaran kuartal ini belum tersedia. Ajukan lagi bulan depan.');

        return $requisition;
    }

    private function exists(Company $company, Department $department, string $position): bool
    {
        return Requisition::acrossCompanies()
            ->where('company_id', $company->id)
            ->where('department_id', $department->id)
            ->where('position_name', $position)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function save(Company $company, Department $department, User $by, array $fields): Requisition
    {
        return app(SaveRequisitionDraft::class)->handle(null, [
            'company_id' => $company->id,
            'department_id' => $department->id,
            'branch_id' => Branch::acrossCompanies()->firstOrCreate(['company_id' => $company->id, 'name' => 'Pusat'])->id,
            ...$fields,
        ], $by);
    }

    /**
     * Every field the wizard asks for, ready to send.
     *
     * @return array<string, mixed>
     */
    private function complete(string $position, int $quota, Employee $reportsTo): array
    {
        return [
            'position_name' => $position,
            'quota' => $quota,
            'employment_type' => EmploymentType::Contract,
            'contract_months' => 12,
            'manager_employee_id' => $reportsTo->id,
            'target_start_on' => now()->addMonths(2)->toDateString(),
            'reason' => 'Volume pekerjaan bertambah dan tim belum cukup orang.',
            'about' => "Kami mencari {$position} yang teliti dan mau belajar untuk bergabung dengan tim kami.",
            'responsibilities' => [
                ['title' => 'Pekerjaan harian', 'items' => ['Mengerjakan permintaan tim sesuai prioritas.', 'Menjaga kualitas dan ketepatan waktu.']],
                ['title' => 'Kolaborasi', 'items' => ['Berkoordinasi dengan atasan dan divisi lain.']],
            ],
            'benefits' => ['BPJS Kesehatan & BPJS Ketenagakerjaan.', 'Tunjangan makan.'],
            'checklist' => [
                'education' => ['Minimal D3/S1 yang relevan'],
                'experience' => ['Pengalaman minimal 1 tahun di bidang serupa'],
                'hard' => ['Menguasai alat kerja bidangnya', 'Menguasai Microsoft Office'],
                'soft' => ['Komunikatif', 'Mampu bekerja dalam tim'],
                'bonus' => ['Portofolio atau hasil kerja sebelumnya'],
            ],
            'draft_step' => 3,
        ];
    }
}
