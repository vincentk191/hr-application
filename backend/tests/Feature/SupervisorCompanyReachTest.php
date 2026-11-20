<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Http\Middleware\SetActiveCompany;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Access\SupervisorReach;
use App\Support\CompanyContext;
use App\Support\ReportingLine;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A Supervisor whose team works in other companies (their own company, plus the ones their reports are in): the switcher offers
 * exactly those, "all" only when there is more than one, and what they may see of a report there stays read-only. Nobody else's
 * reach changes: a Karyawan who happens to have reports, HR, and the public entrance all stay in one company.
 *
 * The line: Sita (Supervisor, company A) manages Rani (A) and Budi (B); Budi manages Chandra (C). Dodi (B) is nobody's report.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->a = Company::factory()->create(['name' => 'Alfa']);
    $this->b = Company::factory()->create(['name' => 'Beta']);
    $this->c = Company::factory()->create(['name' => 'Gama']);

    $this->sita = reachMember($this->a, 'Sita', null, Role::Supervisor);
    $this->rani = reachMember($this->a, 'Rani', $this->sita);
    $this->budi = reachMember($this->b, 'Budi', $this->sita, Role::Employee);
    $this->chandra = reachMember($this->c, 'Chandra', $this->budi);
    $this->dodi = reachMember($this->b, 'Dodi', null);
});

function reachMember(Company $company, string $name, ?Employee $manager, ?Role $role = null): Employee
{
    $attributes = ['full_name' => $name, 'manager_id' => $manager?->id];

    if ($role !== null) {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role->value);
        $attributes['user_id'] = $user->id;
    }

    return Employee::factory()->forCompany($company)->create($attributes);
}

/**
 * @return list<string>
 */
function reachDirectory(User $user, ?string $selection = null): array
{
    $request = test()->actingAs($user);

    if ($selection !== null) {
        $request = $request->withSession([SetActiveCompany::SESSION_KEY => $selection]);
    }

    return collect($request->get(route('employees.index'))->assertOk()->viewData('page')['props']['employees'])
        ->pluck('name')->sort()->values()->all();
}

// --- What the reach is ---

it('reaches the companies a supervisor\'s whole line works in, their own first', function () {
    $reach = app(SupervisorReach::class);

    expect($reach->companyIds($this->sita->user))->toBe([$this->a->id, $this->b->id, $this->c->id])
        ->and($reach->spansCompanies($this->sita->user))->toBeTrue();
});

it('leaves out a company that is switched off', function () {
    $this->c->update(['is_active' => false]);

    expect(app(SupervisorReach::class)->companyIds($this->sita->user))->toBe([$this->a->id, $this->b->id]);
});

it('gives a Karyawan who has reports no reach beyond their own company', function () {
    $tono = reachMember($this->a, 'Tono', null, Role::Employee);
    Employee::factory()->forCompany($this->b)->create(['manager_id' => $tono->id]);

    expect(app(SupervisorReach::class)->companyIds($tono->user))->toBe([$this->a->id]);
});

it('walks the line across companies only when asked to', function () {
    $line = app(ReportingLine::class);

    expect($line->downlineIdsAcrossCompanies($this->sita))->toEqualCanonicalizing([$this->rani->id, $this->budi->id, $this->chandra->id])
        ->and($line->downlineIds($this->sita))->toBe([$this->rani->id]);
});

// --- The switcher and the filter ---

it('offers a supervisor their reach, starting in their own company', function () {
    $this->actingAs($this->sita->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('companySwitcher.canCombine', true)
        ->where('companySwitcher.locked', false)
        ->where('companySwitcher.selected', (string) $this->a->id)
        ->where('companySwitcher.companies', fn ($companies) => collect($companies)->pluck('id')->all() === [$this->a->id, $this->b->id, $this->c->id]));
});

it('keeps the switcher locked for a supervisor whose team is all in one company', function () {
    $lone = reachMember($this->b, 'Lina', null, Role::Supervisor);
    Employee::factory()->forCompany($this->b)->create(['manager_id' => $lone->id]);

    $this->actingAs($lone->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('companySwitcher.locked', true)
        ->where('companySwitcher.canCombine', false));
});

it('keeps the switcher locked for a Karyawan with reports and on the public entrance', function () {
    $tono = reachMember($this->a, 'Tono', null, Role::Employee);
    Employee::factory()->forCompany($this->b)->create(['manager_id' => $tono->id]);

    $this->actingAs($tono->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('companySwitcher.locked', true));

    $this->actingAs($this->sita->user)->withHeaders(['X-Entrance' => 'public'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companySwitcher.locked', true)
            ->where('companySwitcher.companies', fn ($companies) => collect($companies)->pluck('id')->all() === [$this->a->id]));
});

it('shows a supervisor only the company they have chosen, or all they reach', function () {
    expect(reachDirectory($this->sita->user))->toBe(['Rani', 'Sita'])
        ->and(reachDirectory($this->sita->user, (string) $this->b->id))->toBe(['Budi'])
        ->and(reachDirectory($this->sita->user, SetActiveCompany::COMBINED))->toBe(['Budi', 'Chandra', 'Rani', 'Sita']);
});

it('lets a supervisor choose among their reach and refuses a company outside it', function () {
    $outside = Company::factory()->create();

    $this->actingAs($this->sita->user)->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => $this->b->id])->assertRedirect(route('dashboard'));
    expect(session(SetActiveCompany::SESSION_KEY))->toBe((string) $this->b->id);

    $this->actingAs($this->sita->user)->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => SetActiveCompany::COMBINED])->assertRedirect(route('dashboard'));
    expect(session(SetActiveCompany::SESSION_KEY))->toBe(SetActiveCompany::COMBINED);

    $this->actingAs($this->sita->user)->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => $outside->id])->assertSessionHasErrors('company');
});

it('ignores a selection in the session that the reach does not cover', function () {
    $outside = Company::factory()->create();
    Employee::factory()->forCompany($outside)->create(['full_name' => 'Orang Luar']);

    expect(reachDirectory($this->sita->user, (string) $outside->id))->toBe(['Rani', 'Sita']);
});

it('refuses the switcher to a Karyawan with reports, a supervisor with one company, and the public entrance', function () {
    $tono = reachMember($this->a, 'Tono', null, Role::Employee);
    Employee::factory()->forCompany($this->b)->create(['manager_id' => $tono->id]);

    $this->actingAs($tono->user)->put(route('active-company.update'), ['company' => $this->b->id])->assertForbidden();

    $this->actingAs($this->sita->user)->withHeaders(['X-Entrance' => 'public'])
        ->put(route('active-company.update'), ['company' => $this->b->id])->assertForbidden();
});

// --- What a supervisor may do with a report in another company ---

it('lets a supervisor open the profile of a report in another company, and not anyone else there', function () {
    $this->actingAs($this->sita->user)->withSession([SetActiveCompany::SESSION_KEY => SetActiveCompany::COMBINED]);

    $this->getJson(route('employees.summary', $this->budi))->assertOk();
    $this->getJson(route('employees.summary', $this->chandra))->assertOk();
    $this->getJson(route('employees.summary', $this->dodi))->assertForbidden();
});

it('keeps every HR-style action closed on a report in another company', function () {
    $document = EmployeeDocument::factory()->forEmployee($this->budi)->create();
    $sita = $this->sita->user;

    expect($sita->can('view', $this->budi))->toBeTrue()
        ->and($sita->can('update', $this->budi))->toBeFalse()
        ->and($sita->can('viewAttendance', $this->budi))->toBeFalse()
        ->and($sita->can('correctAttendance', $this->budi))->toBeFalse()
        ->and($sita->can('view', $document))->toBeFalse()
        ->and($sita->can('create', EmployeeDocument::class))->toBeFalse()
        ->and($sita->canAccessCompany($this->b))->toBeFalse();

    $this->actingAs($sita)->withSession([SetActiveCompany::SESSION_KEY => SetActiveCompany::COMBINED])
        ->get(route('employees.edit', $this->budi))->assertForbidden();
});

it('keeps the sensitive fields off a report in another company', function () {
    $this->budi->update(['base_salary_idr' => 9000000]);

    $profile = $this->actingAs($this->sita->user)->withSession([SetActiveCompany::SESSION_KEY => SetActiveCompany::COMBINED])
        ->getJson(route('employees.summary', $this->budi))->assertOk();

    expect(json_encode($profile->json()))->not->toContain('9000000');
});

it('shows a supervisor the leave of a report in another company, and not of anyone else there', function () {
    $hrB = User::factory()->create(['company_id' => $this->b->id]);
    $hrB->assignRole(Role::HrAdmin->value);

    foreach ([$this->budi, $this->dodi] as $employee) {
        $this->actingAs($hrB)->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'start_on' => '2026-11-02',
            'end_on' => '2026-11-04',
            'reason' => 'Acara keluarga',
        ])->assertRedirect();
    }

    $budiLeave = LeaveRequest::acrossCompanies()->where('employee_id', $this->budi->id)->firstOrFail();
    $dodiLeave = LeaveRequest::acrossCompanies()->where('employee_id', $this->dodi->id)->firstOrFail();

    expect($this->sita->user->can('view', $budiLeave))->toBeTrue()
        ->and($this->sita->user->can('view', $dodiLeave))->toBeFalse();

    $names = collect(
        $this->actingAs($this->sita->user)->withSession([SetActiveCompany::SESSION_KEY => SetActiveCompany::COMBINED])
            ->get(route('leave-requests.index'))->assertOk()->viewData('page')['props']['requests'],
    )->pluck('employee')->all();

    expect(json_encode($names))->toContain('Budi')->not->toContain('Dodi');
});

it('opens a supervisor\'s own profile while another company is being viewed', function () {
    $this->actingAs($this->sita->user)->withSession([SetActiveCompany::SESSION_KEY => (string) $this->b->id])
        ->get(route('employees.show', $this->sita))->assertOk();
});

it('makes a record in the supervisor\'s own company while all they reach are being viewed', function () {
    app(CompanyContext::class)->setCompanies([$this->a->id, $this->b->id], $this->a->id);

    expect(Branch::create(['name' => 'Cabang Baru'])->company_id)->toBe($this->a->id);
});

// --- Saving a report whose manager is in another company ---

it('keeps the manager of a report when HR saves a profile whose manager works in another company', function () {
    $hrB = User::factory()->create(['company_id' => $this->b->id]);
    $hrB->assignRole(Role::HrAdmin->value);
    $branch = Branch::factory()->forCompany($this->b)->create();
    $payload = fn (array $overrides = []): array => [
        'employee_number' => $this->budi->employee_number,
        'national_id' => $this->budi->national_id ?? '3174000000001234',
        'full_name' => 'Budi',
        'branch_id' => $branch->id,
        'employment_status' => EmploymentStatus::Active->value,
        ...$overrides,
    ];

    // Saved as it stands: the manager in company A is not asked to be someone in company B.
    $this->actingAs($hrB)->put(route('employees.update', $this->budi), $payload(['manager_id' => $this->sita->id]))
        ->assertSessionDoesntHaveErrors('manager_id');

    // Choosing someone else from another company is still refused.
    $this->actingAs($hrB)->put(route('employees.update', $this->budi), $payload(['manager_id' => $this->rani->id]))
        ->assertSessionHasErrors('manager_id');
});
