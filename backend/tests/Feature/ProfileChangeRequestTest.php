<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * An employee asks to change their own profile and HR decides (EMP-CHG).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->hr = changeUser(Role::HrAdmin, $this->company);
    $this->staffUser = changeUser(Role::Employee, $this->company);
    $this->staff = Employee::factory()->forCompany($this->company)->create([
        'user_id' => $this->staffUser->id,
        'full_name' => 'Wulan',
        'phone' => '0811-0000-0000',
        'address' => 'Jl. Lama 1',
        'bank_name' => 'BCA',
        'bank_account_number' => '111222333',
    ]);
});

function changeUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $fields
 */
function askForChange(User $as, Employee $employee, array $fields)
{
    return test()->actingAs($as)->post(route('employees.changes.store', $employee), $fields);
}

function latestChange(): ProfileChangeRequest
{
    return ProfileChangeRequest::query()->latest('id')->firstOrFail();
}

// --- Asking ---

it('records only the fields that differ, with what the employee saw, and changes nothing yet', function () {
    askForChange($this->staffUser, $this->staff, [
        'phone' => '0812-9999-0000',
        'address' => 'Jl. Lama 1',
        'bank_name' => 'BCA',
        'bank_account_number' => '999888777',
    ])->assertRedirect(route('employees.changes.create', $this->staff));

    $change = latestChange();

    expect($change->status->value)->toBe('pending')
        ->and($change->changes)->toBe([
            'phone' => ['before' => '0811-0000-0000', 'after' => '0812-9999-0000'],
            'bank_account_number' => ['before' => '111222333', 'after' => '999888777'],
        ])
        ->and($this->staff->refresh()->phone)->toBe('0811-0000-0000')
        ->and($this->staff->bank_account_number)->toBe('111222333');
});

it('lets an emptied field mean "clear it"', function () {
    askForChange($this->staffUser, $this->staff, ['address' => ''])->assertRedirect();

    expect(latestChange()->changes)->toBe(['address' => ['before' => 'Jl. Lama 1', 'after' => null]]);
});

it('never takes employment, pay or NIK fields from an employee', function () {
    $department = Department::factory()->forCompany($this->company)->create();

    askForChange($this->staffUser, $this->staff, [
        'phone' => '0812-0000-0001',
        'department_id' => $department->id,
        'base_salary_idr' => 99000000,
        'national_id' => '3174000000009999',
        'employment_status' => 'inactive',
    ])->assertRedirect();

    expect(array_keys(latestChange()->changes))->toBe(['phone'])
        ->and($this->staff->refresh()->department_id)->toBeNull()
        ->and($this->staff->base_salary_idr)->toBeNull();
});

it('refuses a request that changes nothing', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0811-0000-0000'])
        ->assertSessionHasErrors(['form' => 'Tidak ada perubahan untuk diajukan.']);

    expect(ProfileChangeRequest::count())->toBe(0);
});

it('lets an employee have one open request, until they withdraw it', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-0000-0001'])->assertRedirect();
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-0000-0002'])->assertSessionHasErrors('form');

    expect(ProfileChangeRequest::count())->toBe(1);

    $this->actingAs($this->staffUser)->delete(route('profile-changes.destroy', latestChange()))->assertRedirect();

    expect(latestChange()->status->value)->toBe('withdrawn');

    askForChange($this->staffUser, $this->staff, ['phone' => '0812-0000-0002'])->assertRedirect();

    expect(ProfileChangeRequest::count())->toBe(2);
});

it('lets only the owner of a record ask for a change to it, and only while they work here', function () {
    $colleague = changeUser(Role::Employee, $this->company);

    askForChange($colleague, $this->staff, ['phone' => '0812-0000-0001'])->assertForbidden();
    askForChange($this->hr, $this->staff, ['phone' => '0812-0000-0001'])->assertForbidden();
    $this->actingAs($colleague)->get(route('employees.changes.create', $this->staff))->assertForbidden();

    $this->staff->update(['employment_status' => EmploymentStatus::Inactive]);
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-0000-0001'])->assertForbidden();

    expect(ProfileChangeRequest::count())->toBe(0);
});

it('offers the form on the employee\'s own profile only', function () {
    $this->actingAs($this->staffUser)->get(route('employees.show', $this->staff))
        ->assertInertia(fn (Assert $page) => $page->where('canRequestChange', true)->where('canUpdate', false));

    $this->actingAs($this->hr)->get(route('employees.show', $this->staff))
        ->assertInertia(fn (Assert $page) => $page->where('canRequestChange', false)->where('canUpdate', true));
});

it('keeps a withdrawn or rejected picture off the disk', function () {
    $photo = fn (): UploadedFile => UploadedFile::fake()->create('foto.jpg', 20, 'image/jpeg');

    askForChange($this->staffUser, $this->staff, ['photo' => $photo()])->assertRedirect();
    $pending = latestChange()->changes['photo_path']['after'];
    Storage::disk('local')->assertExists($pending);

    $this->actingAs($this->staffUser)->delete(route('profile-changes.destroy', latestChange()))->assertRedirect();
    Storage::disk('local')->assertMissing($pending);

    askForChange($this->staffUser, $this->staff, ['photo' => $photo()])->assertRedirect();
    $second = latestChange()->changes['photo_path']['after'];
    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'reject', 'note' => 'Foto kurang jelas'])->assertRedirect();
    Storage::disk('local')->assertMissing($second);
});

it('shows a waiting picture to HR and its owner through an authorised route, and to nobody else', function () {
    askForChange($this->staffUser, $this->staff, ['photo' => UploadedFile::fake()->create('foto.jpg', 20, 'image/jpeg')])->assertRedirect();
    $change = latestChange();
    $other = changeUser(Role::Employee, $this->company);

    $this->actingAs($this->hr)->get(route('profile-changes.photo', [$change, 'after']))->assertOk();
    $this->actingAs($this->staffUser)->get(route('profile-changes.photo', [$change, 'after']))->assertOk();
    $this->actingAs($this->hr)->get(route('profile-changes.photo', [$change, 'sideways']))->assertNotFound();
    $this->actingAs($other)->get(route('profile-changes.photo', [$change, 'after']))->assertForbidden();

    $this->actingAs($this->hr)->get(route('profile-changes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('waiting.0.changes.0.afterUrl', fn ($url) => str_contains($url, '/photo/after')));
});

// --- Reviewing ---

it('shows HR only the changed fields, with the current and the asked-for value', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-9999-0000', 'bank_account_number' => '999888777'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('profile-changes.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('waiting', 1)
            ->where('waiting.0.employee.name', 'Wulan')
            ->has('waiting.0.changes', 2)
            ->where('waiting.0.changes.0.label', 'Telepon')
            ->where('waiting.0.changes.0.before', '0811-0000-0000')
            ->where('waiting.0.changes.0.after', '0812-9999-0000')
            ->where('waiting.0.canDecide', true));
});

it('withholds bank values from a reviewer who may not see sensitive data', function () {
    $reviewer = changeUser(Role::Supervisor, $this->company);
    $reviewer->givePermissionTo('update-employees');
    askForChange($this->staffUser, $this->staff, ['bank_account_number' => '999888777'])->assertRedirect();

    $this->actingAs($reviewer)->get(route('profile-changes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('waiting.0.changes.0.masked', true)
            ->where('waiting.0.changes.0.before', null)
            ->where('waiting.0.changes.0.after', null));
});

it('keeps the review list and the decision to people who may update employees', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-0000-0001'])->assertRedirect();
    $supervisor = changeUser(Role::Supervisor, $this->company);

    $this->actingAs($supervisor)->get(route('profile-changes.index'))->assertForbidden();
    $this->actingAs($supervisor)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($this->staffUser)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertForbidden();

    expect($this->staff->refresh()->phone)->toBe('0811-0000-0000');
});

it('counts what is waiting for the menu badge, for HR only', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-0000-0001'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingProfileChanges', 1)->where('auth.canReviewProfileChanges', true));
    $this->actingAs($this->staffUser)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingProfileChanges', 0)->where('auth.canReviewProfileChanges', false));
});

// --- Deciding ---

it('applies an approved change and writes who asked and who approved to the audit trail', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-9999-0000', 'address' => 'Jl. Baru 9'])->assertRedirect();

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertRedirect();

    $this->staff->refresh();
    $change = latestChange();

    expect($this->staff->phone)->toBe('0812-9999-0000')
        ->and($this->staff->address)->toBe('Jl. Baru 9')
        ->and($change->status->value)->toBe('approved')
        ->and($change->decided_by_user_id)->toBe($this->hr->id)
        ->and($change->conflicts)->toBeNull();

    $entry = Activity::query()->where('description', 'Perubahan data dari karyawan disetujui')->firstOrFail();

    expect($entry->causer_id)->toBe($this->hr->id)
        ->and($entry->properties['requested_by'])->toBe($this->staffUser->name)
        ->and($entry->properties['applied'])->toBe(['phone', 'address']);
});

it('does not overwrite a field HR changed after the request was made, and says so', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-9999-0000', 'address' => 'Jl. Baru 9'])->assertRedirect();

    // HR corrects the phone number directly while the request waits.
    $this->actingAs($this->hr)->put(route('employees.update', $this->staff), [
        'employee_number' => $this->staff->employee_number, 'national_id' => $this->staff->national_id,
        'full_name' => 'Wulan', 'employment_status' => 'active', 'phone' => '0813-1111-1111', 'address' => 'Jl. Lama 1',
    ])->assertRedirect();

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertRedirect();

    $this->staff->refresh();

    expect($this->staff->phone)->toBe('0813-1111-1111')
        ->and($this->staff->address)->toBe('Jl. Baru 9')
        ->and(latestChange()->conflicts)->toBe(['Telepon']);
});

it('replaces the picture on approval and deletes the old file', function () {
    $old = UploadedFile::fake()->create('lama.jpg', 20, 'image/jpeg')->store('employee-photos', 'local');
    $this->staff->update(['photo_path' => $old]);

    askForChange($this->staffUser, $this->staff, ['photo' => UploadedFile::fake()->create('baru.jpg', 20, 'image/jpeg')])->assertRedirect();
    $new = latestChange()->changes['photo_path']['after'];

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertRedirect();

    expect($this->staff->refresh()->photo_path)->toBe($new);
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($new);
});

it('needs a reason to reject, and leaves the profile alone', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-9999-0000'])->assertRedirect();

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'reject'])
        ->assertSessionHasErrors(['note' => 'Tulis alasan penolakan.']);
    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'reject', 'note' => 'Nomor tidak aktif'])->assertRedirect();

    expect($this->staff->refresh()->phone)->toBe('0811-0000-0000')
        ->and(latestChange()->status->value)->toBe('rejected')
        ->and(latestChange()->decision_note)->toBe('Nomor tidak aktif');
});

it('cannot be decided twice', function () {
    askForChange($this->staffUser, $this->staff, ['phone' => '0812-9999-0000'])->assertRedirect();
    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertRedirect();

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'reject', 'note' => 'Terlambat'])->assertForbidden();

    expect(latestChange()->status->value)->toBe('approved');
});

// --- HR's own record ---

it('has HR edit other people\'s records directly, but their own only through another HR Admin', function () {
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->hr->id, 'phone' => '0811-5555-5555']);
    $otherHr = changeUser(Role::HrAdmin, $this->company);

    expect($this->hr->can('update', $this->staff))->toBeTrue()
        ->and($this->hr->can('update', $mine))->toBeFalse();
    $this->actingAs($this->hr)->get(route('employees.edit', $mine))->assertForbidden();

    askForChange($this->hr, $mine, ['phone' => '0812-5555-5555'])->assertRedirect();

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertForbidden();
    expect($mine->refresh()->phone)->toBe('0811-5555-5555');

    $this->actingAs($otherHr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'approve'])->assertRedirect();
    expect($mine->refresh()->phone)->toBe('0812-5555-5555');
});

// --- Notifications ---

it('tells every other HR Admin about a request, and the employee about the decision', function () {
    $otherHr = changeUser(Role::HrAdmin, $this->company);

    askForChange($this->staffUser, $this->staff, ['phone' => '0812-9999-0000'])->assertRedirect();

    expect($this->hr->notifications()->count())->toBe(1)
        ->and($otherHr->notifications()->count())->toBe(1)
        ->and($this->staffUser->notifications()->count())->toBe(0);

    $this->actingAs($this->hr)->put(route('profile-changes.decide', latestChange()), ['decision' => 'reject', 'note' => 'Salah ketik'])->assertRedirect();

    $notice = $this->staffUser->notifications()->firstOrFail();

    expect($notice->data['title'])->toContain('ditolak')->and($notice->data['body'])->toContain('Salah ketik');
});

it('does not tell an HR Admin about their own request', function () {
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->hr->id]);
    $otherHr = changeUser(Role::HrAdmin, $this->company);

    askForChange($this->hr, $mine, ['phone' => '0812-5555-5555'])->assertRedirect();

    expect($this->hr->notifications()->count())->toBe(0)->and($otherHr->notifications()->count())->toBe(1);
});
