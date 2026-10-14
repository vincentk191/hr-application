<?php

namespace Database\Seeders;

use App\Enums\DocumentCategory;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local accounts and sample staff for trying the dashboard by hand.
 *
 * Gives one account of each kind, because the difference is the whole point of the company
 * switcher: a parent-wide account may switch companies and view them combined, a pinned
 * account is locked to one (CORE-USR-004, CORE-NAV-003/004). The employee account is the
 * ordinary person's view of the same app, with their own record, pay and documents behind it.
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
            ['Karyawan '.$first->name, 'employee.a@hr.test', $first->id, Role::Employee],
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

        $this->seedEmployeePerspective($first);

        // Then give every company the full Management → Supervisor → Employee line.
        $this->call(OrgHierarchySeeder::class);

        $this->command->newLine();
        $this->command->line('  All accounts use the password: password');
        $this->command->line('  group@hr.test         HR Admin, parent-wide   (switcher unlocked)');
        $this->command->line('  hr.a@hr.test          HR Admin, '.$first->name.'   (switcher locked)');
        $this->command->line('  admin@hr.test         System Admin, parent-wide');
        $this->command->line('  supervisor.a@hr.test  Supervisor, '.$first->name.'  (read-only)');
        $this->command->line('  employee.a@hr.test    Employee, '.$first->name.'  (own profile and requests only)');
        $this->command->newLine();
    }

    /**
     * Link the employee login to a dossier record and give that record something to look at: a
     * salary and bank details (which an employee may see on their own record only), and two
     * documents, one ordinary and one restricted (which they must not see).
     */
    private function seedEmployeePerspective(Company $company): void
    {
        $user = User::where('email', 'employee.a@hr.test')->first();

        if ($user === null) {
            return;
        }

        $employee = Employee::acrossCompanies()->where('user_id', $user->id)->first()
            ?? Employee::acrossCompanies()
                ->where('company_id', $company->id)
                ->whereNull('user_id')
                ->whereDoesntHave('documents')
                ->orderByDesc('id')
                ->first();

        if ($employee === null) {
            return;
        }

        $employee->update([
            'user_id' => $user->id,
            'base_salary_idr' => $employee->base_salary_idr ?? 7500000,
            'bank_name' => $employee->bank_name ?? 'BCA',
            'bank_account_number' => $employee->bank_account_number ?? '1234567890',
        ]);

        // The login and the dossier should read as the same person.
        $user->update(['name' => $employee->full_name]);

        if ($employee->documents()->exists()) {
            return;
        }

        $this->fileDocument($employee, 'Kontrak kerja', DocumentCategory::Contract, false, now()->addDays(20)->toDateString());
        $this->fileDocument($employee, 'Surat peringatan', DocumentCategory::Other, true, null);
    }

    /**
     * File a small placeholder PDF as a document, so downloads work in the demo.
     */
    private function fileDocument(Employee $employee, string $title, DocumentCategory $category, bool $restricted, ?string $expiresOn): void
    {
        $document = $employee->documents()->create([
            'company_id' => $employee->company_id,
            'category' => $category,
            'title' => $title,
            'is_restricted' => $restricted,
            'expires_on' => $expiresOn,
        ]);

        $path = "employee-documents/{$employee->id}/".Str::uuid().'.pdf';

        Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        $document->versions()->create([
            'version_no' => 1,
            'path' => $path,
            'original_name' => Str::slug($title).'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => Storage::disk('local')->size($path),
        ]);
    }
}
