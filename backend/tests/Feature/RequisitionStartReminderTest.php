<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Access\Entrance;
use App\Support\Recruitment\StartDatesOnCalendar;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The target start date of a role that is still open: HR and the new hire's direct superior are reminded each morning
 * from a week before it through the day itself, and HR sees it on the calendar. "Now" is 2026-11-09 08:00 in Jakarta.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = startUser(Role::HrAdmin, $this->company);
    $this->boss = startUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);
    $this->other = startUser(Role::Supervisor, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $this->other->id]);
});

function startUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function startRequisition(string $targetStart, RequisitionStage $stage = RequisitionStage::Posting, string $position = 'Staf Gudang'): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->hr->id,
        'manager_employee_id' => test()->bossEmployee->id,
        'position_name' => $position,
        'target_start_on' => $targetStart,
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->hr)->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

function startTitles(User $user): array
{
    return $user->notifications()->get()->map(fn ($notice): string => $notice->data['title'])->all();
}

it('reminds HR and the direct superior each day of the week before, and on the day, once a day', function () {
    $requisition = startRequisition('2026-11-12');

    $this->artisan('recruitment:start-reminders')->assertSuccessful();

    foreach ([$this->hr, $this->boss] as $person) {
        $notice = $person->notifications()->get()->sole();

        expect($notice->data['title'])->toBe('Mulai kerja 3 hari lagi: Staf Gudang')
            ->and($notice->data['body'])->toBe($requisition->reference().' · belum terisi');
    }

    $this->artisan('recruitment:start-reminders')->assertSuccessful();
    expect($this->hr->notifications()->count())->toBe(1)->and($this->boss->notifications()->count())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-11-10 08:00', 'Asia/Jakarta'));
    $this->artisan('recruitment:start-reminders')->assertSuccessful();

    expect(startTitles($this->hr))->toContain('Mulai kerja 2 hari lagi: Staf Gudang')->toHaveCount(2);

    $this->other->refresh();
    expect($this->other->notifications()->count())->toBe(0);
});

it('says today and tomorrow in words', function () {
    startRequisition('2026-11-09', position: 'Hari Ini');
    startRequisition('2026-11-10', position: 'Besok');

    $this->artisan('recruitment:start-reminders')->assertSuccessful();

    expect(startTitles($this->hr))->toContain('Mulai kerja hari ini: Hari Ini')->toContain('Mulai kerja besok: Besok');
});

it('says nothing before the last week, or after the date has passed', function () {
    startRequisition('2026-11-17', position: 'Delapan Hari');
    startRequisition('2026-11-08', position: 'Kemarin');
    startRequisition('2026-11-16', position: 'Tujuh Hari');

    $this->artisan('recruitment:start-reminders')->assertSuccessful();

    expect(startTitles($this->hr))->toBe(['Mulai kerja 7 hari lagi: Tujuh Hari']);
});

it('leaves out roles that are filled, cancelled or not yet sent', function () {
    startRequisition('2026-11-12', RequisitionStage::Filled);
    startRequisition('2026-11-12', RequisitionStage::Cancelled);
    startRequisition('2026-11-12', RequisitionStage::Draft);

    $this->artisan('recruitment:start-reminders')->assertSuccessful();

    expect($this->hr->notifications()->count())->toBe(0)->and($this->boss->notifications()->count())->toBe(0);
});

it('shows the open roles that start in the range to HR on the calendar, and not the rest', function () {
    $open = startRequisition('2026-11-12');
    startRequisition('2026-11-12', RequisitionStage::Filled);
    startRequisition('2026-12-30');

    $this->actingAs($this->hr)->get('/attendance/calendar?view=week&date=2026-11-09')
        ->assertInertia(fn (Assert $page) => $page
            ->has('startDates', 1)
            ->where('startDates.0.id', $open->id)
            ->where('startDates.0.title', 'Staf Gudang')
            ->where('startDates.0.date', '2026-11-12')
            ->where('startDates.0.reference', $open->reference()));
});

it('shows a start date to the direct superior it names and to nobody else, and never on the public entrance', function () {
    startRequisition('2026-11-12');
    $this->boss->givePermissionTo('view-attendance');
    $this->other->givePermissionTo('view-attendance');

    $this->actingAs($this->boss)->get('/attendance/calendar?view=week&date=2026-11-09')
        ->assertInertia(fn (Assert $page) => $page->has('startDates', 1));

    $this->actingAs($this->other)->get('/attendance/calendar?view=week&date=2026-11-09')
        ->assertInertia(fn (Assert $page) => $page->has('startDates', 0));

    $range = [CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-15')];

    expect(app(StartDatesOnCalendar::class)->for($this->hr, ...$range))->toHaveCount(1);

    app(Entrance::class)->markPublic(true);

    expect(app(StartDatesOnCalendar::class)->for($this->hr, ...$range))->toBe([]);
});
