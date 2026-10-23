<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\CompanyContext;

/**
 * Company scoping is the project's central data rule: one database, three companies, strict
 * separation (context.md §9.1, CORE-NAV-002/004). Getting it wrong leaks HR data, so these
 * tests cover the bypasses as well as the happy path.
 */
function companyContext(): CompanyContext
{
    return app(CompanyContext::class);
}

it('filters queries to the active company', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    $mine = Employee::factory()->forCompany($acme)->create();
    Employee::factory()->forCompany($other)->create();

    companyContext()->setCompany($acme);

    expect(Employee::pluck('id')->all())->toBe([$mine->id]);
});

it('hides another company record from a lookup by its id', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    $theirs = Employee::factory()->forCompany($other)->create();

    companyContext()->setCompany($acme);

    // This is what stops a company-pinned user reaching a record by typing its URL: route
    // model binding resolves through the same scoped query.
    expect(Employee::find($theirs->id))->toBeNull();
});

it('applies to every company-tagged model', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    $department = Department::factory()->forCompany($acme)->create();
    Position::factory()->forDepartment($department)->create();

    $otherDepartment = Department::factory()->forCompany($other)->create();
    Position::factory()->forDepartment($otherDepartment)->create();

    companyContext()->setCompany($acme);

    expect(Department::count())->toBe(1)
        ->and(Position::count())->toBe(1);
});

it('does not filter when a parent-wide role views companies combined', function () {
    Employee::factory()->forCompany(Company::factory()->create())->create();
    Employee::factory()->forCompany(Company::factory()->create())->create();

    companyContext()->setCombined();

    expect(Employee::count())->toBe(2);
});

it('does not filter when no company context has been resolved', function () {
    Employee::factory()->forCompany(Company::factory()->create())->create();
    Employee::factory()->forCompany(Company::factory()->create())->create();

    // Console commands, queued jobs and tests run here. Nothing is filtered, which is why
    // code in those contexts has to scope its own queries.
    expect(companyContext()->isResolved())->toBeFalse()
        ->and(Employee::count())->toBe(2);
});

it('tags a new record with the active company', function () {
    $acme = Company::factory()->create();

    companyContext()->setCompany($acme);

    $employee = Employee::create([
        'branch_id' => Branch::factory()->forCompany($acme)->create()->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Budi Santoso',
    ]);

    expect($employee->company_id)->toBe($acme->id);
});

it('keeps an explicitly set company when creating a record', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    companyContext()->setCompany($acme);

    // An explicit company_id is honoured, so parent-wide tooling can still place a record.
    $employee = Employee::create([
        'company_id' => $other->id,
        'branch_id' => Branch::factory()->forCompany($other)->create()->id,
        'employee_number' => 'EMP-0002',
        'full_name' => 'Siti Aminah',
    ]);

    expect($employee->company_id)->toBe($other->id);
});

it('reaches every company only through the explicit bypass', function () {
    $acme = Company::factory()->create();
    Employee::factory()->forCompany($acme)->create();
    Employee::factory()->forCompany(Company::factory()->create())->create();

    companyContext()->setCompany($acme);

    expect(Employee::count())->toBe(1)
        ->and(Employee::acrossCompanies()->count())->toBe(2);
});

it('scopes relationship queries through the owning company', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    $department = Department::factory()->forCompany($acme)->create();
    Employee::factory()->inDepartment($department)->create();

    companyContext()->setCompany($other);

    // Reading through a relationship must not sidestep the scope either.
    expect(Department::acrossCompanies()->find($department->id)->employees()->count())->toBe(0);
});
