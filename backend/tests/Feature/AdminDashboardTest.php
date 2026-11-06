<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\CalendarEvent;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;

/**
 * The cards the dashboard adds for the people who run HR: what waits on their decision, the month calendar of leave,
 * interviews and holidays, and how recruitment is going.
 *
 * Today is fixed at Tuesday 20 October 2026; the calendar tests look at November, where 7–8 Nov is a weekend.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00', 'Asia/Jakarta'));
    config(['attendance.device_timezone' => 'Asia/Jakarta']);

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);
    $this->hr = adminUser(Role::HrAdmin, $this->company);
    $this->budi = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Budi', 'joined_on' => '2020-01-01']);
});

function adminUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * The dashboard's props for a user, with the closures evaluated.
 *
 * @return array<string, mixed>
 */
function dashboardProps(User $user, string $query = ''): array
{
    /** @var TestResponse $response */
    $response = test()->actingAs($user)->get('/dashboard'.$query)->assertOk();

    return $response->viewData('page')['props'];
}

/**
 * Leave for Budi filed by HR, approved unless told otherwise.
 */
function adminLeave(string $from, string $to, ?string $decision = 'approve'): LeaveRequest
{
    test()->actingAs(test()->hr)->post(route('leave-requests.store'), [
        'employee_id' => test()->budi->id, 'leave_type' => 'annual', 'start_on' => $from, 'end_on' => $to, 'reason' => 'Perlu',
    ])->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->latest('id')->firstOrFail();

    if ($decision !== null) {
        test()->actingAs(test()->hr)->put(route('leave-requests.decision', $leave), ['decision' => $decision, 'note' => $decision === 'reject' ? 'Tidak' : null])->assertRedirect();
    }

    return $leave;
}

function adminRequisition(RequisitionStage $stage): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->hr->id,
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->hr)
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

// --- Who sees what ---

it('gives a plain employee none of the admin cards', function () {
    $employee = adminUser(Role::Employee, $this->company);

    $props = dashboardProps($employee);

    expect($props['waiting'])->toBeNull()
        ->and($props['adminCalendar'])->toBeNull()
        ->and($props['recruitment'])->toBeNull();
});

it('gives HR all three cards', function () {
    $props = dashboardProps($this->hr);

    expect($props['waiting'])->not->toBeNull()
        ->and($props['adminCalendar'])->not->toBeNull()
        ->and($props['recruitment'])->not->toBeNull();
});

// --- Menunggu keputusan ---

it('counts what waits on this person the same way the approval queue does', function () {
    adminLeave('2026-11-03', '2026-11-03', decision: null);

    $items = collect(dashboardProps($this->hr)['waiting']['items'])->keyBy('key');

    expect($items['leave']['count'])->toBe(WorkflowStep::waitingOn($this->hr, LeaveRequest::class)->count())
        ->and($items->keys()->all())->toBe(['leave', 'requisition', 'offboarding', 'profile']);
});

// --- The calendar ---

it('puts leave on the working days it covers, waiting and approved, and leaves rejected leave out', function () {
    // Friday 6 to Monday 9 November: the weekend between is not leave on the calendar.
    adminLeave('2026-11-06', '2026-11-09');
    adminLeave('2026-11-12', '2026-11-12', decision: null);
    adminLeave('2026-11-16', '2026-11-16', decision: 'reject');

    $leave = collect(dashboardProps($this->hr, '?calendar=2026-11')['adminCalendar']['entries'])->where('kind', 'leave');

    expect($leave->pluck('date')->all())->toBe(['2026-11-06', '2026-11-09', '2026-11-12'])
        ->and($leave->firstWhere('date', '2026-11-06')['status'])->toBe('approved')
        ->and($leave->firstWhere('date', '2026-11-12')['status'])->toBe('submitted')
        ->and($leave->first()['title'])->toBe('Budi');
});

it('shows interviews and holidays on the calendar, and moves by month', function () {
    $requisition = adminRequisition(RequisitionStage::Interview);
    Candidate::factory()->create([
        'requisition_id' => $requisition->id, 'name' => 'Sari',
        'interview_at' => CarbonImmutable::parse('2026-11-10 10:00', 'Asia/Jakarta')->utc(), 'interview_mode' => 'online',
    ]);
    CalendarEvent::create(['title' => 'Cuti bersama', 'kind' => 'holiday', 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-11']);

    $calendar = dashboardProps($this->hr, '?calendar=2026-11')['adminCalendar'];
    $entries = collect($calendar['entries']);

    expect($calendar['month'])->toBe('2026-11')
        ->and($calendar['prev'])->toBe('2026-10')
        ->and($calendar['next'])->toBe('2026-12')
        ->and($entries->firstWhere('kind', 'interview'))->toMatchArray(['date' => '2026-11-10', 'title' => 'Sari', 'time' => '10:00'])
        ->and($entries->firstWhere('kind', 'holiday'))->toMatchArray(['date' => '2026-11-11', 'title' => 'Cuti bersama']);

    // Without a month asked for, it is this month, which has none of them.
    expect(dashboardProps($this->hr)['adminCalendar']['month'])->toBe('2026-10');
});

it('leaves interviews off the calendar of someone who does not recruit', function () {
    $requisition = adminRequisition(RequisitionStage::Interview);
    Candidate::factory()->create(['requisition_id' => $requisition->id, 'interview_at' => CarbonImmutable::parse('2026-11-10 10:00', 'Asia/Jakarta')->utc()]);
    $management = adminUser(Role::Management, $this->company);

    // Management plans around leave too, so they get the calendar, but candidates are HR's.
    $props = dashboardProps($management, '?calendar=2026-11');

    expect($props['adminCalendar'])->not->toBeNull()
        ->and(collect($props['adminCalendar']['entries'])->where('kind', 'interview'))->toBeEmpty()
        ->and($props['recruitment'])->toBeNull();
});

it('gives a System Admin, who runs accounts and setup, none of the HR cards when nothing waits on them', function () {
    $props = dashboardProps(adminUser(Role::SystemAdmin, $this->company));

    expect($props['waiting'])->toBeNull()
        ->and($props['adminCalendar'])->toBeNull()
        ->and($props['recruitment'])->toBeNull();
});

// --- Rekrutmen berjalan ---

it('counts running requisitions by stage and lists the invitation links that lapsed unfilled', function () {
    $interviewing = adminRequisition(RequisitionStage::Interview);
    adminRequisition(RequisitionStage::Posting);
    adminRequisition(RequisitionStage::Cancelled);

    Candidate::factory()->create([
        'requisition_id' => $interviewing->id, 'name' => 'Lewat',
        'invited_at' => now()->subDays(3), 'invite_expires_at' => now()->subDay(),
    ]);
    Candidate::factory()->create([
        'requisition_id' => $interviewing->id, 'name' => 'Sudah isi',
        'invited_at' => now()->subDays(3), 'invite_expires_at' => now()->subDay(), 'form_submitted_at' => now()->subDays(2),
    ]);
    Candidate::factory()->create([
        'requisition_id' => $interviewing->id, 'name' => 'Besok',
        'interview_at' => now()->addDay(),
    ]);

    $recruitment = dashboardProps($this->hr)['recruitment'];
    $stages = collect($recruitment['stages'])->keyBy('stage');

    expect($recruitment['open'])->toBe(2)
        ->and($stages['interview']['count'])->toBe(1)
        ->and($stages['posting']['count'])->toBe(1)
        ->and($recruitment['lapsedLinks'])->toBe(1)
        ->and($recruitment['lapsed'][0]['name'])->toBe('Lewat')
        ->and($recruitment['interviewsThisWeek'])->toBe(1)
        ->and($recruitment['upcoming'][0]['name'])->toBe('Besok');
});
