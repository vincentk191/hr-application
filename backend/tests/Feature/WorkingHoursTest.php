<?php

use App\Enums\Role;
use App\Filament\Pages\ManageWorkingHours;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Attendance\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Working hours set from the admin panel (Jam kerja), and what they change on the calendar.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    $this->hr = hoursUser(Role::HrAdmin);
});

function hoursUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

it('falls back to the config defaults until someone saves hours', function () {
    $schedule = WorkSchedule::current();

    expect($schedule->start)->toBe('08:00')
        ->and($schedule->end)->toBe('17:00')
        ->and($schedule->lateGraceMinutes)->toBe(10)
        ->and($schedule->latestOnTime())->toBe('08:10');
});

it('saves new hours from the admin page and uses them straight away', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManageWorkingHours::class)
        ->assertSchemaStateSet(['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10])
        ->fillForm(['start' => '09:00', 'end' => '18:30', 'late_grace_minutes' => 15])
        ->call('save')
        ->assertHasNoFormErrors();

    $schedule = WorkSchedule::current();

    expect($schedule->start)->toBe('09:00')
        ->and($schedule->end)->toBe('18:30')
        ->and($schedule->lateGraceMinutes)->toBe(15)
        ->and($schedule->latestOnTime())->toBe('09:15');

    $log = Activity::where('log_name', 'settings')->first();

    expect($log->properties['before']['start'])->toBe('08:00')
        ->and($log->properties['after']['start'])->toBe('09:00')
        ->and($log->causer_id)->toBe($this->hr->id);
});

it('rejects an end before the start and an out-of-range tolerance', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManageWorkingHours::class)
        ->fillForm(['start' => '17:00', 'end' => '08:00', 'late_grace_minutes' => 10])
        ->call('save')
        ->assertHasFormErrors(['end']);

    Livewire::test(ManageWorkingHours::class)
        ->fillForm(['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 500])
        ->call('save')
        ->assertHasFormErrors(['late_grace_minutes']);

    Livewire::test(ManageWorkingHours::class)
        ->fillForm(['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => -1])
        ->call('save')
        ->assertHasFormErrors(['late_grace_minutes']);

    expect(app(AppSettings::class)->get(WorkSchedule::KEY_START))->toBeNull();
});

it('lets Friday end later than the other weekdays', function () {
    config(['attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'end_friday' => '17:30', 'late_grace_minutes' => 10]]);

    $schedule = WorkSchedule::current();

    expect($schedule->fridayEnd)->toBe('17:30')
        ->and($schedule->endFor(CarbonImmutable::parse('2026-10-22')))->toBe('17:00')  // Thursday
        ->and($schedule->endFor(CarbonImmutable::parse('2026-10-23')))->toBe('17:30'); // Friday
});

it('ends every day alike when there is no Friday end', function () {
    $schedule = WorkSchedule::current();

    expect($schedule->fridayEnd)->toBeNull()
        ->and($schedule->endFor(CarbonImmutable::parse('2026-10-23')))->toBe('17:00');
});

it('saves a Friday end from the admin page, and clearing it sticks over the config default', function () {
    config(['attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'end_friday' => '17:30', 'late_grace_minutes' => 10]]);
    $this->actingAs($this->hr);

    Livewire::test(ManageWorkingHours::class)
        ->assertSchemaStateSet(['end_friday' => '17:30'])
        ->fillForm(['end_friday' => '18:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(WorkSchedule::current()->fridayEnd)->toBe('18:00');

    Livewire::test(ManageWorkingHours::class)
        ->fillForm(['end_friday' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    // Emptied on purpose: Friday now ends with the other days, not back at the config's 17:30.
    expect(WorkSchedule::current()->fridayEnd)->toBeNull();
});

it('rejects a Friday end before the start', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManageWorkingHours::class)
        ->fillForm(['start' => '08:00', 'end' => '17:00', 'end_friday' => '07:00', 'late_grace_minutes' => 10])
        ->call('save')
        ->assertHasFormErrors(['end_friday']);
});

it('counts leaving at 17:10 as early only on a Friday', function () {
    config(['attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'end_friday' => '17:30', 'late_grace_minutes' => 10]]);

    $company = Company::factory()->create();
    $budi = Employee::factory()->forCompany($company)->create(['joined_on' => '2020-01-01']);
    AttendanceEnrolMap::create(['company_id' => $company->id, 'employee_id' => $budi->id, 'device_sn' => 'SN1', 'pin' => 'P1']);

    // Thursday 22 and Friday 23 October 2026: in at 08:00, out at 17:10.
    foreach (['2026-10-22', '2026-10-23'] as $date) {
        foreach (['08:00:00', '17:10:00'] as $time) {
            AttendancePunch::create([
                'company_id' => $company->id, 'employee_id' => $budi->id, 'device_sn' => 'SN1', 'pin' => 'P1',
                'scan_at' => CarbonImmutable::parse("{$date} {$time}", 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
            ]);
        }
    }

    $statusOn = fn (string $date) => collect(
        $this->actingAs($this->hr)->get("/attendance/calendar?view=day&date={$date}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page)->viewData('page')['props']['rows'],
    )->first()['days'][$date]['status'];

    expect($statusOn('2026-10-22'))->toBe('present')
        ->and($statusOn('2026-10-23'))->toBe('early_leave');
});

it('has no field for working days: Saturday and Sunday are never workdays', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManageWorkingHours::class)
        ->assertFormFieldDoesNotExist('workdays');

    $schedule = new WorkSchedule('08:00', '17:00', 10);

    expect($schedule->isWorkday(CarbonImmutable::parse('2026-10-09')))->toBeTrue()   // Friday
        ->and($schedule->isWorkday(CarbonImmutable::parse('2026-10-10')))->toBeFalse() // Saturday
        ->and($schedule->isWorkday(CarbonImmutable::parse('2026-10-11')))->toBeFalse(); // Sunday
});

it('is open only to parent-wide accounts that manage attendance', function () {
    $this->actingAs($this->hr)->get('/admin/manage-working-hours')->assertOk();

    $this->actingAs(hoursUser(Role::HrAdmin, Company::factory()->create()))
        ->get('/admin/manage-working-hours')->assertForbidden();

    $this->actingAs(hoursUser(Role::SystemAdmin))->get('/admin/manage-working-hours')->assertForbidden();
});

it('changes what counts as late on the calendar', function () {
    $company = Company::factory()->create();
    $budi = Employee::factory()->forCompany($company)->create(['joined_on' => '2020-01-01']);
    $budi->company_id = $company->id;
    AttendancePunch::create([
        'company_id' => $company->id, 'employee_id' => $budi->id, 'device_sn' => 'SN1', 'pin' => 'P1',
        'scan_at' => CarbonImmutable::parse('2026-10-05 08:40:00', 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
    AttendanceEnrolMap::create(['company_id' => $company->id, 'employee_id' => $budi->id, 'device_sn' => 'SN1', 'pin' => 'P1']);

    $statusOnFifth = fn () => collect(
        $this->actingAs($this->hr)->get('/attendance/calendar?view=day&date=2026-10-05')
            ->assertOk()->assertInertia(fn (Assert $page) => $page)->viewData('page')['props']['rows'],
    )->first()['days']['2026-10-05']['status'];

    expect($statusOnFifth())->toBe('late');

    app(AppSettings::class)->set(WorkSchedule::KEY_START, '08:30');
    app(AppSettings::class)->set(WorkSchedule::KEY_GRACE, 15);

    expect($statusOnFifth())->toBe('present');
});
