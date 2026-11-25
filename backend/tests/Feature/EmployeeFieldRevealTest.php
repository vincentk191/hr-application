<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Sensitive profile fields are masked when a profile is read and shown in full only on request, with the request
 * written to the activity log (UI/UX audit UX-01).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();

    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);

    $this->employee = Employee::factory()->forCompany($this->company)->create([
        'national_id' => '3174000000001234',
        'phone' => '0812-0000-5678',
        'bank_account_number' => '1234567890',
        'base_salary_idr' => 9000000,
        'bpjs_health_number' => null,
    ]);
});

it('masks the sensitive fields on the profile and lists which ones can be revealed', function () {
    $this->actingAs($this->hr)
        ->get(route('employees.show', $this->employee))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.contact.national_id', '••••1234')
            ->where('employee.contact.phone', '••••5678')
            ->where('employee.bank.bank_account_number', '••••7890')
            ->where('employee.payroll.base_salary_idr', '••••')
            ->where('employee.contact.email', $this->employee->email)
            ->where('employee.masked', fn ($masked) => collect($masked)->sort()->values()->all() === [
                'bank_account_number', 'base_salary_idr', 'national_id', 'phone',
            ]));
});

it('masks the same fields in the directory drawer', function () {
    $this->actingAs($this->hr)
        ->getJson(route('employees.summary', $this->employee))
        ->assertOk()
        ->assertJsonPath('employee.contact.national_id', '••••1234')
        ->assertJsonPath('employee.bank.bank_account_number', '••••7890')
        ->assertJsonPath('employee.masked', fn (array $masked) => in_array('national_id', $masked, true));
});

it('keeps real values on the edit form, which has to change them', function () {
    $this->actingAs($this->hr)
        ->get(route('employees.edit', $this->employee))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.contact.national_id', '3174000000001234')
            ->where('employee.payroll.base_salary_idr', 9000000)
            ->where('employee.masked', []));
});

it('reveals one field in full and writes it to the activity log without the value', function () {
    $this->actingAs($this->hr)
        ->postJson(route('employees.reveal', $this->employee), ['field' => 'national_id'])
        ->assertOk()
        ->assertExactJson(['value' => '3174000000001234']);

    $this->actingAs($this->hr)
        ->postJson(route('employees.reveal', $this->employee), ['field' => 'base_salary_idr'])
        ->assertOk()
        ->assertExactJson(['value' => 9000000]);

    $entries = Activity::query()->where('description', 'Sensitive field revealed')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->causer_id)->toBe($this->hr->id)
        ->and($entries->first()->subject_id)->toBe($this->employee->id)
        ->and($entries->first()->properties->all())->toBe(['field' => 'national_id'])
        ->and($entries->pluck('properties')->toJson())->not->toContain('3174000000001234');
});

it('lets a supervisor reveal a report\'s contact details but not their bank or payroll', function () {
    $supervisor = User::factory()->create(['company_id' => $this->company->id]);
    $supervisor->assignRole(Role::Supervisor->value);
    $boss = Employee::factory()->forCompany($this->company)->create(['user_id' => $supervisor->id]);
    $this->employee->update(['manager_id' => $boss->id]);

    $this->actingAs($supervisor)
        ->postJson(route('employees.reveal', $this->employee), ['field' => 'phone'])
        ->assertOk()
        ->assertJsonPath('value', '0812-0000-5678');

    $this->actingAs($supervisor)
        ->postJson(route('employees.reveal', $this->employee), ['field' => 'bank_account_number'])
        ->assertForbidden();

    $this->actingAs($supervisor)
        ->postJson(route('employees.reveal', $this->employee), ['field' => 'base_salary_idr'])
        ->assertForbidden();

    // Only the allowed reveal was recorded.
    expect(Activity::query()->where('description', 'Sensitive field revealed')->count())->toBe(1);
});

it('lets an employee reveal their own bank details but nobody else\'s', function () {
    $owner = User::factory()->create(['company_id' => $this->company->id]);
    $owner->assignRole(Role::Employee->value);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $owner->id, 'bank_account_number' => '9988776655']);

    $this->actingAs($owner)
        ->postJson(route('employees.reveal', $mine), ['field' => 'bank_account_number'])
        ->assertOk()
        ->assertJsonPath('value', '9988776655');

    $this->actingAs($owner)
        ->postJson(route('employees.reveal', $this->employee), ['field' => 'phone'])
        ->assertForbidden();
});

it('refuses a field that is not one of the maskable ones', function (string $field) {
    $this->actingAs($this->hr)
        ->postJson(route('employees.reveal', $this->employee), ['field' => $field])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('field');

    expect(Activity::query()->where('description', 'Sensitive field revealed')->count())->toBe(0);
})->with(['an unknown column' => 'remember_token', 'a plain field' => 'full_name', 'nothing' => '']);

it('does not reveal a record from another company', function () {
    $foreign = Employee::factory()->forCompany(Company::factory()->create())->create(['national_id' => '3174000000009999']);

    $this->actingAs($this->hr)
        ->postJson(route('employees.reveal', $foreign), ['field' => 'national_id'])
        ->assertNotFound();
});

it('requires signing in', function () {
    $this->postJson(route('employees.reveal', $this->employee), ['field' => 'phone'])->assertUnauthorized();
});
