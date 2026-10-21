<?php

use App\Enums\Role;
use App\Models\AttendanceAgent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * FEATURE_ATTENDANCE: a release can ship without attendance. Off, its pages and ingest API answer 404, the
 * menus, profile tab and dashboard card go, and the daily notice stops; the calendar and its holidays stay
 * because leave day counts need them.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => null]);
    $this->hr->assignRole(Role::HrAdmin->value);
    $this->employee = Employee::factory()->forCompany($this->company)->create();
});

it('opens the attendance pages when the feature is on', function (string $uri) {
    $this->actingAs($this->hr)->get($uri)->assertOk();
})->with([
    'recap' => '/attendance/recap',
    'issues' => '/attendance/issues',
    'device' => '/attendance/device',
]);

describe('with attendance switched off', function () {
    beforeEach(function () {
        config(['features.attendance' => false]);
    });

    it('returns 404 on the pages only attendance uses', function (string $method, string $uri) {
        $this->actingAs($this->hr)->call($method, str_replace('{employee}', (string) $this->employee->id, $uri))
            ->assertNotFound();
    })->with([
        'recap' => ['GET', '/attendance/recap'],
        'calendar export' => ['GET', '/attendance/calendar/export'],
        'issues' => ['GET', '/attendance/issues'],
        'resolving an issue' => ['POST', '/attendance/issues/resolve'],
        'device' => ['GET', '/attendance/device'],
        'importing a scan log' => ['POST', '/attendance/imports'],
        'adding a punch' => ['POST', '/employees/{employee}/punches'],
    ]);

    it('keeps the calendar page, where holidays are entered and leave is seen', function () {
        $this->actingAs($this->hr)->get('/attendance/calendar')->assertOk();
    });

    it('returns 404 on the ingest API even with a valid agent token', function () {
        $token = AttendanceAgent::create(['name' => 'SERVER-CAMAT'])->issueToken();

        $this->withToken($token)->postJson('/api/ingest/attendance', ['punches' => []])->assertNotFound();
        $this->withToken($token)->getJson('/api/ingest/attendance/instructions')->assertNotFound();
    });

    it('tells the page the feature is off and withholds the device card', function () {
        $this->actingAs($this->hr)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('features.attendance', false)
            ->where('auth.canManageAttendance', false)
            ->where('attendanceStatus', null));
    });

    it('gives a profile no attendance data and no way to correct it', function () {
        $this->actingAs($this->hr)->get("/employees/{$this->employee->id}")->assertInertia(fn (Assert $page) => $page
            ->where('attendance', null)
            ->where('canCorrectAttendance', false));
    });

    it('lets an employee see no attendance on their own profile', function () {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $own = Employee::factory()->forCompany($this->company)->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/employees/{$own->id}")->assertInertia(fn (Assert $page) => $page->where('attendance', null));
    });

    it('stops the daily attendance notice from running', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'notifications:attendance-issues'));

        expect($event->filtersPass($this->app))->toBeFalse();

        config(['features.attendance' => true]);

        expect($event->filtersPass($this->app))->toBeTrue();
    });
});
