<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\ReportingLine;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

/**
 * The Management -> Supervisor -> Employee line (CORE-RBA-004) and the role-change audit
 * (CORE-RBA-007) — the two gaps left in the RBAC foundation.
 */
beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->line = app(ReportingLine::class);
});

it('walks the whole line, not just direct reports', function () {
    $head = Employee::factory()->forCompany($this->company)->create();
    $supervisor = Employee::factory()->forCompany($this->company)->reportingTo($head)->create();
    $staff = Employee::factory()->forCompany($this->company)->reportingTo($supervisor)->create();
    $deep = Employee::factory()->forCompany($this->company)->reportingTo($staff)->create();

    // Three levels down, not one.
    expect($this->line->downlineIds($head))
        ->toHaveCount(3)
        ->toContain($supervisor->id, $staff->id, $deep->id);
});

it('does not include the manager in their own downline', function () {
    $supervisor = Employee::factory()->forCompany($this->company)->create();
    Employee::factory()->forCompany($this->company)->reportingTo($supervisor)->create();

    expect($this->line->downlineIds($supervisor))->not->toContain($supervisor->id)
        ->and($this->line->scopeIds($supervisor))->toContain($supervisor->id);
});

it('returns nothing for someone with no reports', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();

    expect($this->line->downlineIds($employee))->toBe([]);
});

it('never walks into another company', function () {
    $other = Company::factory()->create();
    $supervisor = Employee::factory()->forCompany($this->company)->create();

    // A manager_id pointing across companies should not drag that record in: the resolver
    // runs as a raw query, so CompanyScope is not there to catch it.
    $foreign = Employee::factory()->forCompany($other)->create(['manager_id' => $supervisor->id]);
    $mine = Employee::factory()->forCompany($this->company)->reportingTo($supervisor)->create();

    expect($this->line->downlineIds($supervisor))
        ->toContain($mine->id)
        ->not->toContain($foreign->id);
});

it('answers whether one employee manages another', function () {
    $supervisor = Employee::factory()->forCompany($this->company)->create();
    $staff = Employee::factory()->forCompany($this->company)->reportingTo($supervisor)->create();
    $unrelated = Employee::factory()->forCompany($this->company)->create();

    expect($this->line->manages($supervisor, $staff))->toBeTrue()
        ->and($this->line->manages($supervisor, $unrelated))->toBeFalse()
        ->and($this->line->manages($staff, $supervisor))->toBeFalse();
});

it('scopes a query to a downline', function () {
    $supervisor = Employee::factory()->forCompany($this->company)->create();
    Employee::factory()->count(2)->forCompany($this->company)->reportingTo($supervisor)->create();
    Employee::factory()->forCompany($this->company)->create();

    expect(Employee::inDownlineOf($supervisor)->count())->toBe(2);
});

it('records who was granted a role', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();

    $user->assignRole(Role::HrAdmin->value);

    $activity = Activity::inLog('roles')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['action'])->toBe('granted')
        ->and($activity->properties['roles'])->toContain(Role::HrAdmin->value)
        ->and($activity->subject_id)->toBe($user->id);
});

it('records a role being revoked', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::HrAdmin->value);

    $user->removeRole(Role::HrAdmin->value);

    $activity = Activity::inLog('roles')->latest('id')->first();

    expect($activity->properties['action'])->toBe('revoked')
        ->and($activity->properties['roles'])->toContain(Role::HrAdmin->value);
});
