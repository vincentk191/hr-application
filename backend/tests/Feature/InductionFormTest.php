<?php

use App\Actions\Employees\CreateInductionForm;
use App\Enums\DocumentCategory;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Support\Access\PublicRoutes;
use App\Support\Employees\InductionFormData;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The induction form (Formulir data induk karyawan): made when an employee is added, printed, signed and filed back within a
 * week, otherwise left to show as expired.
 *
 * "Now" is 2026-11-20 12:00 in Jakarta.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Karya Logistik', 'address' => 'Ocean Park Blok 38-FA', 'npwp_16' => '0028607349032000']);
    $this->branch = Branch::factory()->forCompany($this->company)->create(['name' => 'Jakarta', 'nitku' => '0024263094150000000000', 'bpjs_health_number' => 'BU02211847']);

    $this->hr = inductionUser(Role::HrAdmin, $this->company);
});

function inductionUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * A new employee, with their own login, who has been sent the form.
 *
 * @return array{0: Employee, 1: User, 2: EmployeeDocument}
 */
function inductionFormFor(Company $company, Branch $branch): array
{
    $employee = Employee::factory()->forCompany($company)->create(['branch_id' => $branch->id]);
    $user = inductionUser(Role::Employee, $company);
    $employee->update(['user_id' => $user->id]);

    return [$employee, $user, app(CreateInductionForm::class)->handle($employee)];
}

function inductionScan(string $name = 'formulir-ttd.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 80, 'application/pdf');
}

it('files the form as a PDF that expires a week after the employee is added', function () {
    $this->actingAs($this->hr)->post(route('employees.store'), [
        'employee_number' => 'EMP-9001',
        'national_id' => '3174000000009001',
        'full_name' => 'Dewi Lestari',
        'branch_id' => $this->branch->id,
        'employment_status' => EmploymentStatus::Active->value,
    ])->assertRedirect();

    $document = Employee::firstWhere('employee_number', 'EMP-9001')->documents()->sole();
    $version = $document->versions()->sole();

    expect($document->kind)->toBe(EmployeeDocument::KIND_INDUCTION_FORM)
        ->and($document->category)->toBe(DocumentCategory::Form)
        ->and($document->is_restricted)->toBeFalse()
        ->and($document->expires_on->toDateString())->toBe('2026-11-27')
        ->and($document->awaitsSignedCopy())->toBeTrue()
        ->and($version->mime_type)->toBe('application/pdf')
        ->and(Storage::disk('local')->get($version->path))->toStartWith('%PDF');
});

it('files the form only once for an employee', function () {
    [$employee, , $form] = inductionFormFor($this->company, $this->branch);

    expect(app(CreateInductionForm::class)->handle($employee)->is($form))->toBeTrue()
        ->and($employee->documents()->count())->toBe(1)
        ->and($form->versions()->count())->toBe(1);
});

it('does not file a form for an employee made without the add form, such as an import', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();

    expect($employee->documents()->count())->toBe(0);
});

it('fills the form with the employee and their own Cabang, and never the salary', function () {
    $employee = Employee::factory()->forCompany($this->company)->create([
        'branch_id' => $this->branch->id,
        'full_name' => 'Ramdoni Amirrudin',
        'base_salary_idr' => 7654321,
    ]);

    $form = app(InductionFormData::class)->for($employee);

    expect($form['company']['name'])->toBe('PT Karya Logistik')
        ->and($form['company']['address'])->toBe('Ocean Park Blok 38-FA')
        ->and($form['employee']['name'])->toBe('Ramdoni Amirrudin')
        ->and(collect($form['branches'])->firstWhere('mine', true)['name'])->toBe('Jakarta')
        ->and(json_encode($form))->not->toContain('7654321');
});

// --- Filing the signed copy ---

it('lets the employee file the signed copy, which marks the form signed and lifts the expiry', function () {
    [, $user, $form] = inductionFormFor($this->company, $this->branch);

    $this->actingAs($user)
        ->post(route('documents.signed-copy.store', $form), ['file' => inductionScan()])
        ->assertRedirect();

    $form->refresh();

    expect($form->awaitsSignedCopy())->toBeFalse()
        ->and($form->signed_at)->not->toBeNull()
        ->and($form->expires_on)->toBeNull()
        ->and($form->versions()->count())->toBe(2)
        ->and($form->versions()->latest('version_no')->first()->uploaded_by_user_id)->toBe($user->id);
});

it('lets HR file the signed copy for the employee', function () {
    [, , $form] = inductionFormFor($this->company, $this->branch);

    $this->actingAs($this->hr)
        ->post(route('documents.signed-copy.store', $form), ['file' => inductionScan()])
        ->assertRedirect();

    expect($form->refresh()->awaitsSignedCopy())->toBeFalse();
});

it('refuses another employee, and a second signed copy', function () {
    [, $user, $form] = inductionFormFor($this->company, $this->branch);
    $stranger = inductionUser(Role::Employee, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->post(route('documents.signed-copy.store', $form), ['file' => inductionScan()])
        ->assertForbidden();

    $this->actingAs($user)->post(route('documents.signed-copy.store', $form), ['file' => inductionScan()]);

    $this->actingAs($user)
        ->post(route('documents.signed-copy.store', $form), ['file' => inductionScan()])
        ->assertForbidden();

    expect($form->versions()->count())->toBe(2);
});

it('refuses a signed copy for an ordinary document', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();
    $document = EmployeeDocument::factory()->forEmployee($employee)->create();

    $this->actingAs($this->hr)
        ->post(route('documents.signed-copy.store', $document), ['file' => inductionScan()])
        ->assertForbidden();
});

it('only takes a PDF or a picture as the signed copy', function () {
    [, $user, $form] = inductionFormFor($this->company, $this->branch);

    $this->actingAs($user)
        ->post(route('documents.signed-copy.store', $form), ['file' => UploadedFile::fake()->create('formulir.docx', 10)])
        ->assertSessionHasErrors('file');

    expect($form->refresh()->awaitsSignedCopy())->toBeTrue();
});

it('shows the employee the form waiting for their signature on their own profile', function () {
    [$employee, $user] = inductionFormFor($this->company, $this->branch);

    $this->actingAs($user)->get(route('employees.show', $employee))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.awaitsSignedCopy', true)
            ->where('documents.0.canFileSignedCopy', true)
            ->where('documents.0.expiryStatus', 'expiring'));
});

it('lets the form show as expired once the week has passed unsigned', function () {
    [, , $form] = inductionFormFor($this->company, $this->branch);

    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00', 'Asia/Jakarta'));

    expect($form->refresh()->expiryStatus())->toBe('expired');
});

it('serves the signed copy route on the public entrance, as the employee uses it', function () {
    expect(PublicRoutes::PUBLIC_NAMES)->toContain('documents.signed-copy.store');
});
