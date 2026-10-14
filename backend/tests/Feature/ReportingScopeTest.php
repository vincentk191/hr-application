<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may see whom follows the reporting line (CORE-RBA-004, CORE-RBA-008): everyone sees
 * themselves and everything below them, nobody sees a colleague on their own level or anyone above,
 * and only the company-wide permission lifts that.
 *
 * The tree: Gita (no login) manages Anton and Elvira; Anton manages Bayu and Citra; Bayu manages Dewi.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();

    $this->gita = scopeMember($this->company, 'Gita', null);
    $this->anton = scopeMember($this->company, 'Anton', $this->gita, Role::Supervisor);
    $this->elvira = scopeMember($this->company, 'Elvira', $this->gita, Role::Supervisor);
    $this->bayu = scopeMember($this->company, 'Bayu', $this->anton, Role::Employee);
    $this->citra = scopeMember($this->company, 'Citra', $this->anton);
    $this->dewi = scopeMember($this->company, 'Dewi', $this->bayu);
});

function scopeMember(Company $company, string $name, ?Employee $manager, ?Role $role = null): Employee
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
function directoryNames(User $user): array
{
    $names = test()->actingAs($user)->get(route('employees.index'))->assertOk()->viewData('page')['props']['employees'];

    return collect($names)->pluck('name')->sort()->values()->all();
}

it('shows a supervisor only themselves and their whole downline', function () {
    expect(directoryNames($this->anton->user))->toBe(['Anton', 'Bayu', 'Citra', 'Dewi'])
        ->and(directoryNames($this->elvira->user))->toBe(['Elvira']);
});

it('keeps colleagues on the same level and everyone above out of every view of the directory', function () {
    $viewer = $this->bayu->user;

    expect(directoryNames($viewer))->toBe(['Bayu', 'Dewi']);

    $this->actingAs($viewer)->get(route('employees.show', $this->dewi))->assertOk();
    $this->actingAs($viewer)->get(route('employees.show', $this->citra))->assertForbidden();
    $this->actingAs($viewer)->get(route('employees.show', $this->anton))->assertForbidden();
    $this->actingAs($viewer)->getJson(route('employees.summary', $this->citra))->assertForbidden();

    $export = $this->actingAs($viewer)->get(route('employees.export'))->assertOk()->streamedContent();
    expect($export)->toContain('Dewi')->not->toContain('Citra')->not->toContain('Anton')->not->toContain('Gita');
});

it('lets the reporting line decide, not the role', function () {
    // Bayu has the plain employee role but has Dewi reporting to him.
    expect($this->bayu->user->hasRole(Role::Employee->value))->toBeTrue()
        ->and($this->bayu->user->can('viewAny', Employee::class))->toBeTrue();

    $this->actingAs($this->bayu->user)->get(route('employees.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.canViewEmployees', true));

    // Citra has an account and nobody reporting to her: no directory at all.
    $citra = User::factory()->create(['company_id' => $this->company->id]);
    $this->citra->update(['user_id' => $citra->id]);
    $citra->assignRole(Role::Employee->value);

    $this->actingAs($citra)->get(route('employees.index'))->assertForbidden();
    $this->actingAs($citra)->get(route('employees.show', $this->citra))->assertOk();
});

it('shows a supervisor with nobody under them only themselves', function () {
    expect(directoryNames($this->elvira->user))->toBe(['Elvira']);

    $this->actingAs($this->elvira->user)->get(route('employees.show', $this->anton))->assertForbidden();
});

it('limits the search to the same people', function () {
    $found = fn (string $query): array => collect(
        $this->actingAs($this->bayu->user)->getJson(route('search', ['q' => $query]))->assertOk()->json('groups'),
    )->firstWhere('key', 'employees')['items'] ?? [];

    expect($found('Dewi'))->toHaveCount(1)
        ->and($found('Citra'))->toBe([])
        ->and($found('Anton'))->toBe([]);
});

it('lists only the departments and positions of the people they may see', function () {
    $warehouse = Department::factory()->forCompany($this->company)->create(['name' => 'Gudang']);
    $secret = Department::factory()->forCompany($this->company)->create(['name' => 'Direksi']);
    $this->dewi->update(['department_id' => $warehouse->id]);
    $this->gita->update(['department_id' => $secret->id]);

    $this->actingAs($this->bayu->user)->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('departments', ['Gudang']));

    $this->actingAs(scopeHr($this->company))->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('departments', ['Direksi', 'Gudang']));
});

function scopeHr(Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole(Role::HrAdmin->value);

    return $user;
}

it('lets the company-wide permission see everyone, and takes that away when it is removed', function () {
    $management = User::factory()->create(['company_id' => $this->company->id]);
    $management->assignRole(Role::Management->value);

    expect(directoryNames($management))->toHaveCount(6);

    $management->roles()->firstOrFail()->revokePermissionTo(Permission::ViewAllEmployees->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // No employee record behind the account, so nothing is left to show.
    expect(directoryNames($management->fresh()))->toBe([]);
});

it('gives the company-wide permission to HR Admin, System Admin and Management but not Supervisor', function () {
    foreach ([Role::HrAdmin, Role::SystemAdmin, Role::Management] as $role) {
        expect($role->permissions())->toContain(Permission::ViewAllEmployees);
    }

    expect(Role::Supervisor->permissions())->not->toContain(Permission::ViewAllEmployees)
        ->and(Role::Employee->permissions())->not->toContain(Permission::ViewAllEmployees);
});

it('limits the Pengajuan queue to the viewer\'s own, their downline\'s and those waiting on them', function () {
    foreach ([$this->dewi, $this->citra, $this->gita] as $employee) {
        scopeLeave($employee);
    }

    $queue = fn (User $user): array => collect(
        $this->actingAs($user)->get(route('leave-requests.index'))->assertOk()->viewData('page')['props']['requests'],
    )->pluck('employee')->sort()->values()->all();

    expect($queue($this->bayu->user))->toBe(['Dewi'])
        ->and($queue($this->anton->user))->toBe(['Citra', 'Dewi'])
        ->and($queue(scopeHr($this->company)))->toBe(['Citra', 'Dewi', 'Gita']);

    $dewiRequest = LeaveRequest::query()->where('employee_id', $this->dewi->id)->firstOrFail();
    $citraRequest = LeaveRequest::query()->where('employee_id', $this->citra->id)->firstOrFail();

    expect($this->bayu->user->can('view', $dewiRequest))->toBeTrue()
        ->and($this->bayu->user->can('view', $citraRequest))->toBeFalse();
});

function scopeLeave(Employee $employee): void
{
    test()->actingAs(scopeHr($employee->company))->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_on' => '2026-11-02',
        'end_on' => '2026-11-04',
        'reason' => 'Acara keluarga',
    ])->assertRedirect();
}

it('keeps the Tim tab to people the viewer may see', function () {
    $team = fn (User $user, Employee $employee): array => $this->actingAs($user)
        ->getJson(route('employees.summary', $employee))->assertOk()->json('team');

    // Bayu looking at Dewi: Bayu is above her, but his own manager and peers are out of sight.
    $seen = $team($this->bayu->user, $this->dewi);
    expect($seen['supervisor']['name'])->toBe('Bayu')
        ->and($seen['grandSupervisor'])->toBeNull()
        ->and(collect($seen['peers'])->pluck('name')->all())->toBe(['Dewi']);

    // Bayu looking at himself: nobody above him, no peers.
    $own = $team($this->bayu->user, $this->bayu);
    expect($own['supervisor'])->toBeNull()
        ->and(collect($own['peers'])->pluck('name')->all())->toBe(['Bayu'])
        ->and(collect($own['reports'])->pluck('name')->all())->toBe(['Dewi']);

    // HR sees the whole slice.
    $full = $team(scopeHr($this->company), $this->bayu);
    expect($full['supervisor']['name'])->toBe('Anton')
        ->and($full['grandSupervisor']['name'])->toBe('Gita')
        ->and(collect($full['peers'])->pluck('name')->sort()->values()->all())->toBe(['Bayu', 'Citra']);
});
