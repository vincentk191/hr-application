<?php

use App\Enums\DocumentCategory;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Employee documents (EMP-DOC) and the sensitive groups on the profile (EMP-DET-006).
 */
beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->forCompany($this->company)->create();
    $this->hr = docUser(Role::HrAdmin, $this->company);
});

function docUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function pdf(string $name = 'kontrak.pdf', int $kilobytes = 50): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

function fileDocument(Employee $employee, array $attributes = []): EmployeeDocument
{
    $document = EmployeeDocument::factory()->forEmployee($employee)->create($attributes);
    $path = pdf()->store("employee-documents/{$employee->id}", 'local');
    $document->versions()->create([
        'version_no' => 1,
        'path' => $path,
        'original_name' => 'kontrak.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);

    return $document;
}

// --- Filing and categorising (EMP-DOC-001) ---

it('files a categorised document as version one on the private disk', function () {
    $this->actingAs($this->hr)
        ->post(route('documents.store', $this->employee), [
            'title' => 'Kontrak kerja 2026',
            'category' => DocumentCategory::Contract->value,
            'expires_on' => '2027-06-30',
            'file' => pdf(),
        ])
        ->assertRedirect();

    $document = EmployeeDocument::firstWhere('title', 'Kontrak kerja 2026');

    expect($document->category)->toBe(DocumentCategory::Contract)
        ->and($document->company_id)->toBe($this->company->id)
        ->and($document->expires_on->toDateString())->toBe('2027-06-30')
        ->and($document->versions)->toHaveCount(1)
        ->and($document->versions->first()->version_no)->toBe(1);

    Storage::disk('local')->assertExists($document->versions->first()->path);
});

it('rejects a missing, oversized or disallowed file', function () {
    $payload = ['title' => 'X', 'category' => DocumentCategory::Other->value];

    $this->actingAs($this->hr)->post(route('documents.store', $this->employee), $payload)
        ->assertSessionHasErrors('file');

    $this->actingAs($this->hr)->post(route('documents.store', $this->employee), [
        ...$payload, 'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
    ])->assertSessionHasErrors('file');

    $this->actingAs($this->hr)->post(route('documents.store', $this->employee), [
        ...$payload, 'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
    ])->assertSessionHasErrors('file');

    expect(EmployeeDocument::count())->toBe(0);
});

it('refuses filing to a role without the manage permission', function () {
    $this->actingAs(docUser(Role::Supervisor, $this->company))
        ->post(route('documents.store', $this->employee), [
            'title' => 'X', 'category' => DocumentCategory::Other->value, 'file' => pdf(),
        ])
        ->assertForbidden();
});

it('updates the metadata of a document', function () {
    $document = fileDocument($this->employee);

    $this->actingAs($this->hr)
        ->put(route('documents.update', $document), [
            'title' => 'Kontrak revisi',
            'category' => DocumentCategory::Certificate->value,
            'expires_on' => '2028-01-01',
        ])
        ->assertRedirect();

    expect($document->fresh()->title)->toBe('Kontrak revisi')
        ->and($document->fresh()->category)->toBe(DocumentCategory::Certificate);
});

it('soft-deletes a document and keeps its files', function () {
    $document = fileDocument($this->employee);
    $path = $document->versions->first()->path;

    $this->actingAs($this->hr)->delete(route('documents.destroy', $document))->assertRedirect();

    expect(EmployeeDocument::find($document->id))->toBeNull()
        ->and(EmployeeDocument::withTrashed()->find($document->id))->not->toBeNull();
    Storage::disk('local')->assertExists($path);
});

// --- Versions (EMP-DOC-003) ---

it('keeps the old file when a new version is filed, and takes the new expiry', function () {
    $document = fileDocument($this->employee, ['expires_on' => '2026-12-31']);

    $this->actingAs($this->hr)
        ->post(route('documents.versions.store', $document), [
            'file' => pdf('kontrak-baru.pdf'),
            'note' => 'Perpanjangan',
            'expires_on' => '2027-12-31',
        ])
        ->assertRedirect();

    $document->refresh()->load('versions', 'currentVersion');

    expect($document->versions->pluck('version_no')->all())->toBe([2, 1])
        ->and($document->currentVersion->version_no)->toBe(2)
        ->and($document->currentVersion->note)->toBe('Perpanjangan')
        ->and($document->expires_on->toDateString())->toBe('2027-12-31');

    foreach ($document->versions as $version) {
        Storage::disk('local')->assertExists($version->path);
    }
});

it('keeps the expiry when a version is filed without a new date', function () {
    $document = fileDocument($this->employee, ['expires_on' => '2026-12-31']);

    $this->actingAs($this->hr)
        ->post(route('documents.versions.store', $document), ['file' => pdf()])
        ->assertRedirect();

    expect($document->fresh()->expires_on->toDateString())->toBe('2026-12-31');
});

// --- Serving files ---

it('serves a file to someone who may see the document', function () {
    $document = fileDocument($this->employee);
    $version = $document->versions->first();

    $this->actingAs($this->hr)
        ->get(route('documents.download', [$document, $version]))
        ->assertOk()
        ->assertDownload('kontrak.pdf');
});

it('will not serve a file to a role without document access', function () {
    $document = fileDocument($this->employee);

    $this->actingAs(docUser(Role::Supervisor, $this->company))
        ->get(route('documents.download', [$document, $document->versions->first()]))
        ->assertForbidden();
});

it('lets an employee read their own ordinary documents but not restricted ones', function () {
    $owner = docUser(Role::Employee, $this->company);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $owner->id]);
    $ordinary = fileDocument($mine);
    $restricted = fileDocument($mine, ['is_restricted' => true]);

    $this->actingAs($owner)->get(route('documents.download', [$ordinary, $ordinary->versions->first()]))->assertOk();
    $this->actingAs($owner)->get(route('documents.download', [$restricted, $restricted->versions->first()]))->assertForbidden();
});

it('refuses a version that belongs to a different document', function () {
    $one = fileDocument($this->employee);
    $two = fileDocument($this->employee);

    $this->actingAs($this->hr)
        ->get(route('documents.download', [$one, $two->versions->first()]))
        ->assertNotFound();
});

it('hides another company\'s document completely', function () {
    $otherCompany = Company::factory()->create();
    $foreign = fileDocument(Employee::factory()->forCompany($otherCompany)->create());

    $this->actingAs($this->hr)
        ->get(route('documents.download', [$foreign, $foreign->versions->first()]))
        ->assertNotFound();
});

// --- Per-document access control (EMP-DOC-004) ---

it('shows a restricted document only to roles holding the sensitive-data permission', function () {
    fileDocument($this->employee, ['title' => 'Surat peringatan', 'is_restricted' => true]);
    fileDocument($this->employee, ['title' => 'Kontrak biasa']);

    // A viewer who may read documents in general, but not the restricted ones.
    $reader = docUser(Role::Management, $this->company);
    $reader->givePermissionTo(Permission::ViewDocuments->value);

    $this->actingAs($reader)->get(route('employees.show', $this->employee))
        ->assertInertia(fn (Assert $page) => $page->has('documents', 1)->where('documents.0.title', 'Kontrak biasa'));

    $this->actingAs($this->hr)->get(route('employees.show', $this->employee))
        ->assertInertia(fn (Assert $page) => $page->has('documents', 2));
});

it('refuses a restricted download to a reader without the permission', function () {
    $document = fileDocument($this->employee, ['is_restricted' => true]);
    $reader = docUser(Role::Management, $this->company);
    $reader->givePermissionTo(Permission::ViewDocuments->value);

    $this->actingAs($reader)
        ->get(route('documents.download', [$document, $document->versions->first()]))
        ->assertForbidden();
});

it('records the download of a restricted document on the audit trail', function () {
    $document = fileDocument($this->employee, ['is_restricted' => true]);

    $this->actingAs($this->hr)->get(route('documents.download', [$document, $document->versions->first()]))->assertOk();

    expect(Activity::where('log_name', 'documents')->where('description', 'Restricted document downloaded')->exists())->toBeTrue();
});

it('lets a filer without the sensitive-data permission neither set nor clear the restricted flag', function () {
    $filer = docUser(Role::Management, $this->company);
    $filer->givePermissionTo([Permission::ViewDocuments->value, Permission::ManageDocuments->value]);

    $this->actingAs($filer)->post(route('documents.store', $this->employee), [
        'title' => 'Dokumen', 'category' => DocumentCategory::Other->value, 'is_restricted' => '1', 'file' => pdf(),
    ])->assertRedirect();

    expect(EmployeeDocument::firstWhere('title', 'Dokumen')->is_restricted)->toBeFalse();
});

// --- Expiry (EMP-DOC-002) ---

it('flags expired and expiring documents and leaves the rest alone', function () {
    $expired = EmployeeDocument::factory()->forEmployee($this->employee)->expiringOn(now()->subDay()->toDateString())->create();
    $soon = EmployeeDocument::factory()->forEmployee($this->employee)->expiringOn(now()->addDays(10)->toDateString())->create();
    $later = EmployeeDocument::factory()->forEmployee($this->employee)->expiringOn(now()->addDays(90)->toDateString())->create();
    $never = EmployeeDocument::factory()->forEmployee($this->employee)->create();

    expect($expired->expiryStatus())->toBe('expired')
        ->and($soon->expiryStatus())->toBe('expiring')
        ->and($later->expiryStatus())->toBeNull()
        ->and($never->expiryStatus())->toBeNull()
        ->and(EmployeeDocument::needingAttention()->pluck('id')->sort()->values()->all())
        ->toBe([$expired->id, $soon->id]);
});

// --- Sensitive groups on the profile (EMP-DET-006) ---

it('withholds bank, payroll and health from roles without the sensitive-data permission', function () {
    $this->employee->update(['base_salary_idr' => 9000000, 'bank_name' => 'BCA']);

    $supervisor = docUser(Role::Supervisor, $this->company);
    $boss = Employee::factory()->forCompany($this->company)->create(['user_id' => $supervisor->id]);
    $this->employee->update(['manager_id' => $boss->id]);

    $this->actingAs($supervisor)
        ->get(route('employees.show', $this->employee))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.bank', null)
            ->where('employee.payroll', null)
            ->where('employee.health', null)
            ->where('employee.contact.email', $this->employee->email));

    $this->actingAs($this->hr)
        ->get(route('employees.show', $this->employee))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.payroll.base_salary_idr', 9000000)
            ->where('employee.bank.bank_name', 'BCA'));
});

it('lets an employee see their own bank and payroll', function () {
    $owner = docUser(Role::Employee, $this->company);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $owner->id, 'base_salary_idr' => 5000000]);

    $this->actingAs($owner)->get(route('employees.show', $mine))
        ->assertInertia(fn (Assert $page) => $page->where('employee.payroll.base_salary_idr', 5000000));
});

it('does not let an editor without the permission blank the sensitive fields', function () {
    $this->employee->update(['base_salary_idr' => 9000000, 'bank_name' => 'BCA']);
    $editor = docUser(Role::Supervisor, $this->company);
    $editor->givePermissionTo(Permission::UpdateEmployees->value);

    $this->actingAs($editor)->put(route('employees.update', $this->employee), [
        'employee_number' => $this->employee->employee_number,
        'national_id' => $this->employee->national_id,
        'full_name' => 'Nama Baru',
        'employment_status' => $this->employee->employment_status->value,
        'base_salary_idr' => '',
        'bank_name' => '',
    ])->assertRedirect();

    $this->employee->refresh();

    expect($this->employee->full_name)->toBe('Nama Baru')
        ->and($this->employee->base_salary_idr)->toBe(9000000)
        ->and($this->employee->bank_name)->toBe('BCA');
});

// --- Admin panel link ---

it('tells the sidebar whether to offer the admin panel', function () {
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole(Role::SystemAdmin->value);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessAdminPanel', true));

    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessAdminPanel', false));
});

// --- Expiry reminder on the dashboard (EMP-DOC-002) ---

it('lists lapsing documents on the dashboard for roles that look after documents', function () {
    $expired = fileDocument($this->employee, ['title' => 'Visa', 'expires_on' => now()->subDays(3)->toDateString()]);
    fileDocument($this->employee, ['title' => 'Aman', 'expires_on' => now()->addDays(200)->toDateString()]);
    fileDocument($this->employee, ['title' => 'Rahasia', 'is_restricted' => true, 'expires_on' => now()->addDays(5)->toDateString()]);

    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('expiringDocuments.total', 2)
            ->where('expiringDocuments.items.0.id', $expired->id)
            ->where('expiringDocuments.items.0.expiryStatus', 'expired'));
});

it('gives roles without document access no reminder card at all', function () {
    fileDocument($this->employee, ['expires_on' => now()->subDay()->toDateString()]);

    $this->actingAs(docUser(Role::Supervisor, $this->company))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('expiringDocuments', null));
});

it('leaves a restricted lapsing document off the reminder for a reader without the permission', function () {
    fileDocument($this->employee, ['is_restricted' => true, 'expires_on' => now()->subDay()->toDateString()]);
    $reader = docUser(Role::Management, $this->company);
    $reader->givePermissionTo(Permission::ViewDocuments->value);

    $this->actingAs($reader)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('expiringDocuments.total', 0));
});
