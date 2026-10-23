<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceIssueResolution;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\InAppNotice;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * In-app notifications: leave approvals, attendance issues and expiring documents. Database channel
 * only; nothing is sent by email.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->hr = notifUser(Role::HrAdmin, $this->company);
    $this->boss = notifUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);
    $this->staffUser = notifUser(Role::Employee, $this->company);
    $this->staff = Employee::factory()->forCompany($this->company)->create([
        'user_id' => $this->staffUser->id, 'manager_id' => $this->bossEmployee->id, 'joined_on' => '2020-01-01',
    ]);
});

function notifUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function notifTitles(User $user): array
{
    return $user->notifications()->reorder('created_at')->get()->map(fn (DatabaseNotification $n): string => $n->data['title'])->all();
}

function notifFileLeave(User $as, Employee $employee): LeaveRequest
{
    test()->actingAs($as)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id, 'leave_type' => 'annual', 'start_on' => '2026-11-02', 'end_on' => '2026-11-03', 'reason' => 'Perlu',
    ])->assertRedirect();

    return LeaveRequest::query()->latest('id')->firstOrFail();
}

function notifDecide(User $by, LeaveRequest $leave, string $decision, ?string $note = null): void
{
    test()->actingAs($by)->put(route('leave-requests.decision', $leave), ['decision' => $decision, 'note' => $note])->assertRedirect();
}

function notifPunch(Employee $employee, string $local): void
{
    AttendanceEnrolMap::query()->firstOrCreate(
        ['employee_id' => $employee->id],
        ['company_id' => $employee->company_id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id],
    );
    AttendancePunch::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
}

// --- Leave ---

it('asks the Atasan to approve, lets HR know it exists, and tells the requester the outcome', function () {
    $leave = notifFileLeave($this->staffUser, $this->staff);

    expect(notifTitles($this->boss))->toBe(["{$leave->reference()} menunggu persetujuan Anda"])
        ->and(notifTitles($this->staffUser))->toBe([]);

    // HR does not approve, but is told of every submission.
    $info = $this->hr->notifications()->get();
    expect($info)->toHaveCount(1)
        ->and($info[0]->data['title'])->toBe("{$leave->reference()} diajukan oleh {$this->staff->full_name}")
        ->and($info[0]->data['body'])->toContain('menunggu Atasan langsung')
        ->and($info[0]->data['url'])->toBe("/leave-requests?open={$leave->id}");

    notifDecide($this->boss, $leave, 'approve');

    // Nothing more goes to HR: there is no second step.
    expect(notifTitles($this->hr))->toHaveCount(1);

    $notice = $this->staffUser->notifications()->first();
    expect($notice->data['title'])->toBe("{$leave->reference()} disetujui")
        ->and($notice->data['url'])->toBe("/employees/{$this->staff->id}?tab=requests")
        ->and($notice->data['kind'])->toBe('leave');
});

it('tells the requester a rejection with the reason, and where to correct it', function () {
    $leave = notifFileLeave($this->staffUser, $this->staff);

    notifDecide($this->boss, $leave, 'reject', 'Tim kekurangan orang');

    $notice = $this->staffUser->notifications()->first();

    expect($notice->data['title'])->toBe("{$leave->reference()} ditolak")
        ->and($notice->data['body'])->toContain('Tim kekurangan orang')
        ->and($notice->data['url'])->toBe("/leave-requests/{$leave->id}/edit");
    // HR heard of it when it was filed, and hears nothing more when it is turned down.
    expect(notifTitles($this->hr))->toHaveCount(1);
});

it('tells the approver again when a rejected request is sent round', function () {
    $employee = Employee::factory()->forCompany($this->company)->create(['user_id' => ($user = notifUser(Role::Employee, $this->company))->id]);
    $leave = notifFileLeave($user, $employee);
    expect(notifTitles($this->hr))->toHaveCount(1);

    notifDecide($this->hr, $leave, 'reject', 'Bentrok');
    $this->actingAs($user)->put(route('leave-requests.update', $leave), [
        'leave_type' => 'annual', 'start_on' => '2026-11-02', 'end_on' => '2026-11-02', 'note' => 'Sudah dipersingkat',
    ])->assertSessionHasNoErrors();

    expect(notifTitles($this->hr))->toBe(["{$leave->reference()} menunggu persetujuan Anda", "{$leave->reference()} diajukan ulang"]);
});

it('does not tell people about what they just did, or who cannot be told', function () {
    // HR files for someone else: HR is the approver but made the request.
    $other = Employee::factory()->forCompany($this->company)->create();
    $leave = notifFileLeave($this->hr, $other);
    expect(notifTitles($this->hr))->toBe([]);

    // A deactivated approver is not notified.
    $this->hr->forceFill(['is_active' => false])->save();
    notifFileLeave($this->staffUser, $this->staff);
    expect(notifTitles($this->hr))->toBe([]);
    unset($leave);
});

it('does not tell HR of another company about a request', function () {
    $stranger = notifUser(Role::HrAdmin, Company::factory()->create());

    notifFileLeave($this->staffUser, $this->staff);

    expect(notifTitles($stranger))->toBe([]);
});

// --- Attendance issues ---

it('tells an employee and their supervisor about a day\'s issues, once', function () {
    notifPunch($this->staff, '2026-10-06 09:00:00');
    notifPunch($this->staff, '2026-10-06 17:05:00');

    $this->artisan('notifications:attendance-issues', ['--date' => '2026-10-06'])->assertSuccessful();
    $this->artisan('notifications:attendance-issues', ['--date' => '2026-10-06'])->assertSuccessful();

    $own = $this->staffUser->notifications()->get();
    $lead = $this->boss->notifications()->get();

    expect($own)->toHaveCount(1)
        ->and($own[0]->data['title'])->toBe('Masalah absensi 2026-10-06')
        ->and($own[0]->data['body'])->toBe('Terlambat')
        ->and($own[0]->data['url'])->toBe("/employees/{$this->staff->id}?tab=attendance&month=2026-10")
        ->and($lead)->toHaveCount(1)
        ->and($lead[0]->data['title'])->toContain($this->staff->full_name);
});

it('sends one notice for a day however many issues', function () {
    // One scan only: no check-out. Two scans 30 seconds apart would be a double scan, so use one.
    notifPunch($this->staff, '2026-10-06 09:00:00');

    $this->artisan('notifications:attendance-issues', ['--date' => '2026-10-06'])->assertSuccessful();

    $own = $this->staffUser->notifications()->get();

    expect($own)->toHaveCount(1)->and($own[0]->data['body'])->toContain('Terlambat')->toContain('Belum check-out');
});

it('skips excused issues and days the scan log has not reached', function () {
    notifPunch($this->staff, '2026-10-06 09:00:00');
    notifPunch($this->staff, '2026-10-06 17:05:00');
    AttendanceIssueResolution::query()->create([
        'company_id' => $this->company->id, 'employee_id' => $this->staff->id, 'date' => '2026-10-06', 'type' => 'late', 'resolution' => 'excused',
        'reason' => 'Macet banjir', 'resolved_by_user_id' => $this->hr->id,
    ]);

    $this->artisan('notifications:attendance-issues', ['--date' => '2026-10-06'])->assertSuccessful();
    expect($this->staffUser->notifications()->count())->toBe(0);

    // Nothing was scanned after the 6th, so the 8th is "no data", not an absence.
    $this->artisan('notifications:attendance-issues', ['--date' => '2026-10-08'])->assertSuccessful();
    expect($this->staffUser->notifications()->count())->toBe(0);
});

// --- Documents ---

it('tells document managers about an expiring document once, and again when it expires', function () {
    $doc = EmployeeDocument::create([
        'company_id' => $this->company->id, 'employee_id' => $this->staff->id, 'category' => 'contract',
        'title' => 'Kontrak kerja', 'is_restricted' => false, 'expires_on' => '2026-10-20',
    ]);

    $this->artisan('notifications:document-expiry')->assertSuccessful();
    $this->artisan('notifications:document-expiry')->assertSuccessful();

    expect(notifTitles($this->hr))->toBe(['Dokumen segera berakhir: Kontrak kerja'])
        ->and(notifTitles($this->staffUser))->toBe([])
        ->and($this->hr->notifications()->first()->data['url'])->toBe("/employees/{$this->staff->id}?tab=documents");

    $this->travelTo(CarbonImmutable::parse('2026-10-22 08:00', 'Asia/Jakarta'));
    $this->artisan('notifications:document-expiry')->assertSuccessful();

    expect(notifTitles($this->hr))->toBe(['Dokumen segera berakhir: Kontrak kerja', 'Dokumen kedaluwarsa: Kontrak kerja']);
    unset($doc);
});

it('leaves a document with no date, or far off, alone', function () {
    EmployeeDocument::create(['company_id' => $this->company->id, 'employee_id' => $this->staff->id, 'category' => 'contract', 'title' => 'A', 'is_restricted' => false, 'expires_on' => null]);
    EmployeeDocument::create(['company_id' => $this->company->id, 'employee_id' => $this->staff->id, 'category' => 'contract', 'title' => 'B', 'is_restricted' => false, 'expires_on' => '2027-06-01']);

    $this->artisan('notifications:document-expiry')->assertSuccessful();

    expect(notifTitles($this->hr))->toBe([]);
});

it('tells only active document managers who may see that company, however they were given the permission', function () {
    $parentWide = User::factory()->create(['company_id' => null]);
    $parentWide->assignRole(Role::HrAdmin->value);

    $otherCompanyHr = notifUser(Role::HrAdmin, Company::factory()->create());

    $inactiveHr = notifUser(Role::HrAdmin, $this->company);
    $inactiveHr->forceFill(['is_active' => false])->save();

    $granted = notifUser(Role::Employee, $this->company);
    $granted->givePermissionTo(Permission::ManageDocuments->value);

    EmployeeDocument::create([
        'company_id' => $this->company->id, 'employee_id' => $this->staff->id, 'category' => 'contract',
        'title' => 'Kontrak kerja', 'is_restricted' => false, 'expires_on' => '2026-10-20',
    ]);

    $this->artisan('notifications:document-expiry')->assertSuccessful();

    expect($this->hr->notifications()->count())->toBe(1)
        ->and($parentWide->notifications()->count())->toBe(1)
        ->and($granted->notifications()->count())->toBe(1)
        ->and($otherCompanyHr->notifications()->count())->toBe(0)
        ->and($inactiveHr->notifications()->count())->toBe(0)
        ->and($this->boss->notifications()->count())->toBe(0)
        ->and($this->staffUser->notifications()->count())->toBe(0);
});

// --- The list ---

it('lists only your own notifications and counts the unread in every page', function () {
    notifFileLeave($this->staffUser, $this->staff);

    $this->actingAs($this->boss)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('list.total', 1)
            ->where('list.data.0.read', false)
            ->where('auth.user.id', $this->boss->id));

    $this->actingAs($this->boss)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1)->has('notifications.recent', 1));

    // HR is told of the submission as information, so has their own notice, and only theirs.
    $this->actingAs($this->hr)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page->where('list.total', 1)->where('list.data.0.title', fn ($title) => str_contains($title, 'diajukan oleh')));
});

it('opens a notification: marks it read and goes where it points', function () {
    $leave = notifFileLeave($this->staffUser, $this->staff);
    $notice = $this->boss->notifications()->first();

    $this->actingAs($this->boss)->get(route('notifications.open', $notice->id))->assertRedirect("/leave-requests?open={$leave->id}");

    expect($notice->refresh()->read_at)->not->toBeNull();
    $this->actingAs($this->boss)->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page->where('list.total', 0)->where('filter', 'unread'));
    unset($leave);
});

it('will not open, or reveal, someone else\'s notification', function () {
    notifFileLeave($this->staffUser, $this->staff);
    $notice = $this->boss->notifications()->first();

    $this->actingAs($this->hr)->get(route('notifications.open', $notice->id))->assertNotFound();
    expect($notice->refresh()->read_at)->toBeNull();
});

it('never follows a stored link that leaves the application', function () {
    $this->boss->notify(new InAppNotice('leave', 'Uji', 'x', 'https://evil.example/phish'));
    $this->boss->notify(new InAppNotice('leave', 'Uji 2', 'x', '//evil.example/phish'));

    foreach ($this->boss->notifications as $notice) {
        $this->actingAs($this->boss)->get(route('notifications.open', $notice->id))->assertRedirect(route('notifications.index'));
    }
});

it('marks everything read in one go', function () {
    notifFileLeave($this->staffUser, $this->staff);
    notifFileLeave($this->staffUser, $this->staff);

    expect($this->boss->unreadNotifications()->count())->toBe(2);

    $this->actingAs($this->boss)->post(route('notifications.read-all'))->assertRedirect();

    expect($this->boss->unreadNotifications()->count())->toBe(0);
});

it('tells an HR Admin who is also the approver once, not twice', function () {
    $loner = Employee::factory()->forCompany($this->company)->create(['user_id' => ($user = notifUser(Role::Employee, $this->company))->id]);

    $leave = notifFileLeave($user, $loner);

    expect(notifTitles($this->hr))->toBe(["{$leave->reference()} menunggu persetujuan Anda"]);
});

it('lets HR know again when a corrected request is sent round to the Atasan', function () {
    $leave = notifFileLeave($this->staffUser, $this->staff);
    notifDecide($this->boss, $leave, 'reject', 'Bentrok');

    $this->actingAs($this->staffUser)->put(route('leave-requests.update', $leave), [
        'leave_type' => 'annual', 'start_on' => '2026-11-02', 'end_on' => '2026-11-02',
    ])->assertSessionHasNoErrors();

    expect(notifTitles($this->hr))->toBe([
        "{$leave->reference()} diajukan oleh {$this->staff->full_name}",
        "{$leave->reference()} diajukan ulang oleh {$this->staff->full_name}",
    ]);
});
