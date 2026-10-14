<?php

use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The profile's "Tim" tab and the JSON summary behind the directory's side drawer.
 *
 * Today is fixed at Tuesday 2026-10-20.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);

    $this->grand = teamMember($this->company, 'Joko', null);
    $this->sari = teamMember($this->company, 'Sari', $this->grand);
    $this->agus = teamMember($this->company, 'Agus', $this->sari);
    $this->hendra = teamMember($this->company, 'Hendra', $this->sari);
    $this->fitri = teamMember($this->company, 'Fitri', $this->sari);
});

function teamMember(Company $company, string $name, ?Employee $manager): Employee
{
    $employee = Employee::factory()->forCompany($company)->create([
        'full_name' => $name, 'manager_id' => $manager?->id, 'joined_on' => '2020-01-01',
    ]);
    AttendanceEnrolMap::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id]);

    return $employee;
}

function teamScan(Employee $employee, string $local): void
{
    AttendancePunch::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
}

/**
 * @return array<string, mixed>
 */
function teamOf(User $viewer, Employee $employee): array
{
    return test()->actingAs($viewer)->getJson(route('employees.summary', $employee))->assertOk()->json('team');
}

it('shows the supervisor, the supervisor\'s supervisor and the colleagues who share them', function () {
    $team = teamOf($this->hr, $this->hendra);

    expect($team['grandSupervisor']['name'])->toBe('Joko')
        ->and($team['supervisor']['name'])->toBe('Sari')
        ->and(collect($team['peers'])->pluck('name')->all())->toBe(['Agus', 'Fitri', 'Hendra'])
        ->and(collect($team['peers'])->firstWhere('isSelf', true)['name'])->toBe('Hendra')
        ->and($team['reports'])->toBe([])
        ->and($team['basis'])->toBe('peers')
        ->and($team['size'])->toBe(3);
});

it('counts a supervisor\'s direct reports as their team', function () {
    $team = teamOf($this->hr, $this->sari);

    expect($team['basis'])->toBe('reports')
        ->and($team['size'])->toBe(3)
        ->and(collect($team['reports'])->pluck('name')->all())->toBe(['Agus', 'Fitri', 'Hendra'])
        ->and($team['grandSupervisor'])->toBeNull()
        ->and($team['supervisor']['name'])->toBe('Joko');
});

it('leaves out former employees, and copes with someone who has no supervisor', function () {
    Employee::factory()->forCompany($this->company)->inactive()->create(['full_name' => 'Lama', 'manager_id' => $this->sari->id]);

    expect(collect(teamOf($this->hr, $this->hendra)['peers'])->pluck('name')->all())->not->toContain('Lama');

    $alone = teamOf($this->hr, $this->grand);
    expect($alone['supervisor'])->toBeNull()->and($alone['peers'])->toHaveCount(1)->and($alone['size'])->toBe(1);
});

it('says how many of the team are in today and who is on leave', function () {
    teamScan($this->agus, '2026-10-20 07:55:00');
    teamScan($this->hendra, '2026-10-20 08:30:00');

    $this->actingAs($this->hr)->post(route('leave-requests.store'), [
        'employee_id' => $this->fitri->id, 'leave_type' => 'annual', 'start_on' => '2026-10-20', 'end_on' => '2026-10-20', 'reason' => 'Perlu',
    ])->assertRedirect();
    $this->actingAs($this->hr)->put(route('leave-requests.decision', LeaveRequest::query()->latest('id')->first()), ['decision' => 'approve'])->assertRedirect();

    $team = teamOf($this->hr, $this->hendra);

    // Late still counts as in.
    expect($team['presentToday'])->toBe(2)->and($team['onLeaveToday'])->toBe(['Fitri']);
});

it('does not read a day the log has not reached as nobody coming in', function () {
    teamScan($this->agus, '2026-10-19 08:00:00');

    $team = teamOf($this->hr, $this->hendra);

    expect($team['presentToday'])->toBeNull()->and($team['onLeaveToday'])->toBe([]);
});

it('leaves the attendance figures out for someone who may not see attendance', function () {
    $viewer = User::factory()->create(['company_id' => $this->company->id]);
    $viewer->assignRole(Role::Employee->value);
    $viewer->givePermissionTo('view-employees');
    $this->sari->update(['user_id' => $viewer->id]);

    $team = teamOf($viewer, $this->hendra);

    expect($team['presentToday'])->toBeNull()->and($team['onLeaveToday'])->toBeNull();
});

it('gives an employee looking at their own record no team', function () {
    $own = Employee::factory()->forCompany($this->company)->withLogin()->create(['manager_id' => $this->sari->id]);

    $this->actingAs($own->user)->getJson(route('employees.summary', $own))->assertOk()->assertJsonPath('team', null);
});

it('does not serve another company\'s employee, or anyone to a viewer who may not see them', function () {
    $elsewhere = teamMember(Company::factory()->create(), 'Orang Lain', null);

    $this->actingAs($this->hr)->getJson(route('employees.summary', $elsewhere))->assertNotFound();

    $stranger = User::factory()->create(['company_id' => $this->company->id]);
    $stranger->assignRole(Role::Employee->value);
    $this->actingAs($stranger)->getJson(route('employees.summary', $this->hendra))->assertForbidden();
});

it('serves the same profile data as the page, read-only, for the drawer', function () {
    $json = $this->actingAs($this->hr)->getJson(route('employees.summary', $this->hendra))->assertOk();

    $json->assertJsonPath('employee.name', 'Hendra')
        ->assertJsonStructure(['employee', 'documents', 'requests', 'leaveTakenThisYear', 'canUpdate', 'canCreateLeave', 'attendance', 'team']);
});

it('adds the team to the full profile page', function () {
    $this->actingAs($this->hr)->get(route('employees.show', $this->hendra))
        ->assertInertia(fn (Assert $page) => $page->component('employees/show')
            ->where('team.supervisor.name', 'Sari')
            ->where('team.size', 3));
});
