<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Availability on the interview calendar (REC-INT): the company's holidays, and the leave of the requisition's PIC, the
 * new hire's direct superior and HR, so interviews are not set on a day someone is away.
 *
 * Today is Monday 2 November 2026. 7 and 8 November are a weekend.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Jakarta'));
    config(['attendance.device_timezone' => 'Asia/Jakarta']);

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = availabilityUser(Role::HrAdmin, $this->company);
    $this->hrEmployee = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Hana HR', 'user_id' => $this->hr->id, 'joined_on' => '2020-01-01']);

    $this->pic = availabilityUser(Role::Supervisor, $this->company);
    $this->picEmployee = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Rudi PIC', 'user_id' => $this->pic->id, 'joined_on' => '2020-01-01']);

    $this->boss = availabilityUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Made Atasan', 'user_id' => $this->boss->id, 'joined_on' => '2020-01-01']);

    $this->other = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Orang Lain', 'joined_on' => '2020-01-01']);

    $this->requisition = Requisition::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'created_by_user_id' => $this->pic->id,
        'manager_employee_id' => $this->bossEmployee->id,
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($this->requisition, new RequisitionWorkflow, [], $this->pic)->update(['current_stage' => 'interview']);
});

function availabilityUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/** Leave for someone, filed by HR and approved unless told otherwise. */
function leaveOf(Employee $employee, string $from, string $to, ?string $decision = 'approve'): LeaveRequest
{
    test()->actingAs(test()->hr)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id, 'leave_type' => 'annual', 'start_on' => $from, 'end_on' => $to, 'reason' => 'Perlu',
    ])->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->latest('id')->firstOrFail();

    if ($decision !== null) {
        test()->actingAs(test()->hr)->put(route('leave-requests.decision', $leave), ['decision' => $decision, 'note' => $decision === 'reject' ? 'Tidak' : null])->assertRedirect();
    }

    return $leave;
}

/**
 * @return array<string, mixed>
 */
function availabilityOf(Requisition $requisition, User $as): ?array
{
    return test()->actingAs($as)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['availability'];
}

it('shows the leave of the PIC, the direct superior and HR, with who they are and whether it is approved', function () {
    leaveOf($this->bossEmployee, '2026-11-10', '2026-11-11');
    leaveOf($this->picEmployee, '2026-11-12', '2026-11-12', decision: null);
    leaveOf($this->hrEmployee, '2026-11-13', '2026-11-13');

    $absences = collect(availabilityOf($this->requisition, $this->hr)['absences']);

    expect($absences->where('date', '2026-11-10')->sole())->toMatchArray(['person' => 'Made Atasan', 'roles' => ['Atasan'], 'status' => 'approved'])
        ->and($absences->where('date', '2026-11-11'))->toHaveCount(1)
        ->and($absences->where('date', '2026-11-12')->sole())->toMatchArray(['person' => 'Rudi PIC', 'roles' => ['PIC'], 'status' => 'submitted'])
        ->and($absences->where('date', '2026-11-13')->sole())->toMatchArray(['person' => 'Hana HR', 'roles' => ['HR']]);
});

it('leaves out everyone else, rejected leave, and the weekend a leave runs across', function () {
    leaveOf($this->other, '2026-11-10', '2026-11-10');
    leaveOf($this->bossEmployee, '2026-11-17', '2026-11-17', decision: 'reject');
    leaveOf($this->bossEmployee, '2026-11-06', '2026-11-09');

    $dates = collect(availabilityOf($this->requisition, $this->hr)['absences'])->pluck('date')->all();

    expect($dates)->toBe(['2026-11-06', '2026-11-09']);
});

it('shows a person who holds several of the roles once, with all of them', function () {
    $this->requisition->update(['created_by_user_id' => $this->hr->id, 'manager_employee_id' => $this->hrEmployee->id]);
    leaveOf($this->hrEmployee, '2026-11-10', '2026-11-10');

    $absences = availabilityOf($this->requisition, $this->hr)['absences'];

    expect($absences)->toHaveCount(1)->and($absences[0]['roles'])->toBe(['PIC', 'Atasan', 'HR']);
});

it('shows the company\'s holidays and nothing meant for another place', function () {
    $day = fn (array $attributes) => CalendarEvent::query()->create(['kind' => 'holiday', 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-20', 'title' => 'Libur', ...$attributes]);
    $day(['title' => 'Libur nasional', 'company_id' => null]);
    $day(['title' => 'Libur perusahaan', 'company_id' => $this->company->id, 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-24']);
    $day(['title' => 'Libur perusahaan lain', 'company_id' => Company::factory()->create()->id]);
    $day(['title' => 'Libur satu departemen', 'company_id' => $this->company->id, 'department_id' => $this->department->id]);
    $day(['title' => 'Libur satu cabang', 'company_id' => $this->company->id, 'branch_id' => Branch::factory()->create(['company_id' => $this->company->id])->id]);
    CalendarEvent::query()->create(['kind' => 'event', 'title' => 'Gathering', 'starts_on' => '2026-11-25', 'ends_on' => '2026-11-25', 'company_id' => $this->company->id]);

    $holidays = availabilityOf($this->requisition, $this->hr)['holidays'];

    expect($holidays)->toBe([
        ['date' => '2026-11-20', 'title' => 'Libur nasional'],
        ['date' => '2026-11-23', 'title' => 'Libur perusahaan'],
        ['date' => '2026-11-24', 'title' => 'Libur perusahaan'],
    ]);
});

it('covers four weeks back and sixteen ahead, and no further', function () {
    leaveOf($this->bossEmployee, '2026-10-01', '2026-10-01');
    leaveOf($this->bossEmployee, '2026-10-05', '2026-10-05');
    leaveOf($this->bossEmployee, '2027-02-26', '2027-02-26');
    leaveOf($this->bossEmployee, '2027-03-01', '2027-03-01');

    $availability = availabilityOf($this->requisition, $this->hr);

    expect($availability['from'])->toBe('2026-10-05')->and($availability['to'])->toBe('2027-02-28')
        ->and(collect($availability['absences'])->pluck('date')->all())->toBe(['2026-10-05', '2027-02-26']);
});

it('gives it to those who see the candidates, at Interview and Offer only', function () {
    leaveOf($this->bossEmployee, '2026-11-10', '2026-11-10');

    expect(availabilityOf($this->requisition, $this->boss))->not->toBeNull()
        ->and(availabilityOf($this->requisition, $this->hr))->not->toBeNull();

    $this->requisition->workflow->update(['current_stage' => 'screening']);
    expect(availabilityOf($this->requisition, $this->hr))->toBeNull();

    $this->requisition->workflow->update(['current_stage' => 'offer']);
    expect(availabilityOf($this->requisition, $this->hr))->not->toBeNull();

    $requester = availabilityUser(Role::Employee, $this->company);
    $this->actingAs($requester)->get(route('recruitment.show', $this->requisition))->assertForbidden();
    $this->actingAs($this->pic)->get(route('recruitment.show', $this->requisition))
        ->assertInertia(fn (Assert $page) => $page->where('requisition.availability', null));
});
