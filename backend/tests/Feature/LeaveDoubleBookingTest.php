<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;

/**
 * A request may not land on top of another of the same person's that is waiting or approved, and may not reach outside
 * the time they work here. The form warns about the first as the person types; the server refuses it either way.
 *
 * Today is Tuesday 2026-10-20 in Jakarta; 24–25 Oct is a weekend.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    config(['attendance.device_timezone' => 'Asia/Jakarta']);

    $this->company = Company::factory()->create();
    $this->hr = bookingUser(Role::HrAdmin, $this->company);
    $this->staffUser = bookingUser(Role::Employee, $this->company);
    $this->staff = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->staffUser->id, 'joined_on' => '2020-01-01']);
});

function bookingUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function bookLeave(array $overrides = [], ?Employee $for = null, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->staffUser)->post(route('leave-requests.store'), [
        'employee_id' => ($for ?? test()->staff)->id,
        'leave_type' => 'annual',
        'start_on' => '2026-10-21',
        'end_on' => '2026-10-22',
        'reason' => 'Perlu',
        ...$overrides,
    ]);
}

function bookedHours(string $from, string $to): array
{
    return ['use_timeframe' => '1', 'start_time' => $from, 'end_time' => $to];
}

function decideBooking(LeaveRequest $leave, string $decision): void
{
    test()->actingAs(test()->hr)->put(route('leave-requests.decision', $leave), ['decision' => $decision, 'note' => $decision === 'reject' ? 'Tidak' : null])->assertRedirect();
}

it('refuses a request on days that are already taken by one waiting or approved', function (?string $decision) {
    bookLeave()->assertSessionHasNoErrors();

    if ($decision !== null) {
        decideBooking(LeaveRequest::latest('id')->firstOrFail(), $decision);
    }

    bookLeave(['start_on' => '2026-10-22', 'end_on' => '2026-10-23'])
        ->assertSessionHasErrors(['start_on' => 'Bentrok dengan REQ-0001 (Cuti tahunan, 21–22 Okt 2026, '.($decision === 'approve' ? 'disetujui' : 'menunggu persetujuan').'). Pilih tanggal atau jam lain.']);

    expect(LeaveRequest::count())->toBe(1);
})->with([null, 'approve']);

it('does not count a rejected request, nor a stretch that is only a weekend', function () {
    bookLeave(['start_on' => '2026-10-23', 'end_on' => '2026-10-23'])->assertSessionHasNoErrors();

    // Friday to Monday against Saturday and Sunday: the days they share are not working days.
    bookLeave(['start_on' => '2026-10-24', 'end_on' => '2026-10-26'])->assertSessionHasNoErrors();

    decideBooking(LeaveRequest::latest('id')->firstOrFail(), 'reject');
    bookLeave(['start_on' => '2026-10-26', 'end_on' => '2026-10-26'])->assertSessionHasNoErrors();

    expect(LeaveRequest::count())->toBe(3);
});

it('lets two requests share a day when their hours do not overlap, and refuses when they do or one is for the whole day', function () {
    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21', ...bookedHours('09:00', '11:00')])->assertSessionHasNoErrors();

    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21', ...bookedHours('13:00', '15:00')])->assertSessionHasNoErrors();
    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21', ...bookedHours('11:00', '12:00')])->assertSessionHasNoErrors();

    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21', ...bookedHours('10:00', '12:00')])->assertSessionHasErrors('start_on');
    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21'])->assertSessionHasErrors('start_on');

    expect(LeaveRequest::count())->toBe(3);
});

it('names the first clash and how many more there are', function () {
    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21'])->assertSessionHasNoErrors();
    bookLeave(['start_on' => '2026-10-22', 'end_on' => '2026-10-22'])->assertSessionHasNoErrors();

    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-22'])
        ->assertSessionHasErrors(['start_on' => 'Bentrok dengan REQ-0001 dan 1 lainnya (Cuti tahunan, 21 Okt 2026, menunggu persetujuan). Pilih tanggal atau jam lain.']);
});

it('only looks at the same person\'s requests', function () {
    $other = Employee::factory()->forCompany($this->company)->create(['joined_on' => '2020-01-01']);

    bookLeave()->assertSessionHasNoErrors();
    bookLeave(for: $other, as: $this->hr)->assertSessionHasNoErrors();

    expect(LeaveRequest::count())->toBe(2);
});

it('checks a corrected request against the others but not against itself', function () {
    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21'])->assertSessionHasNoErrors();
    $first = LeaveRequest::latest('id')->firstOrFail();
    decideBooking($first, 'reject');

    // Filed on the same day while the first is rejected, which is allowed.
    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-21'])->assertSessionHasNoErrors();

    // Sending the first one again now would book the day twice.
    $this->actingAs($this->staffUser)->put(route('leave-requests.update', $first), ['leave_type' => 'annual', 'start_on' => '2026-10-21', 'end_on' => '2026-10-21'])
        ->assertSessionHasErrors('start_on');

    // Moved to a free day, it goes, though the day it came from is taken.
    $this->actingAs($this->staffUser)->put(route('leave-requests.update', $first), ['leave_type' => 'annual', 'start_on' => '2026-10-22', 'end_on' => '2026-10-22'])
        ->assertSessionHasNoErrors();
});

it('refuses days before the person joined or after they left', function () {
    $this->staff->update(['joined_on' => '2026-10-22', 'exited_on' => '2026-10-30']);

    bookLeave(['start_on' => '2026-10-21', 'end_on' => '2026-10-23'])
        ->assertSessionHasErrors(['start_on' => 'Hari pertama sebelum karyawan bergabung (22 Okt 2026).']);
    bookLeave(['start_on' => '2026-10-29', 'end_on' => '2026-11-02'])
        ->assertSessionHasErrors(['end_on' => 'Hari terakhir setelah karyawan keluar (30 Okt 2026).']);
    bookLeave(['start_on' => '2026-10-22', 'end_on' => '2026-10-30'])->assertSessionHasNoErrors();
});

it('answers the form\'s question with the same clashes, and only for someone the person may file for', function () {
    bookLeave()->assertSessionHasNoErrors();
    $query = ['employee_id' => $this->staff->id, 'start_on' => '2026-10-22', 'end_on' => '2026-10-23'];

    $clashes = $this->actingAs($this->staffUser)->getJson(route('leave-requests.conflicts', $query))->assertOk()->json('conflicts');

    expect($clashes)->toHaveCount(1)
        ->and($clashes[0])->toMatchArray(['reference' => 'REQ-0001', 'type' => 'Cuti tahunan', 'dates' => '21–22 Okt 2026', 'statusLabel' => 'menunggu persetujuan'])
        // Leaving out the request being corrected, or choosing free days, leaves none.
        ->and($this->actingAs($this->staffUser)->getJson(route('leave-requests.conflicts', [...$query, 'except' => LeaveRequest::firstOrFail()->id]))->json('conflicts'))->toBe([])
        ->and($this->actingAs($this->staffUser)->getJson(route('leave-requests.conflicts', [...$query, 'start_on' => '2026-10-26', 'end_on' => '2026-10-26']))->json('conflicts'))->toBe([])
        // Incomplete dates are no clashes rather than an error.
        ->and($this->actingAs($this->staffUser)->getJson(route('leave-requests.conflicts', ['employee_id' => $this->staff->id]))->assertOk()->json('conflicts'))->toBe([]);

    // Someone who may not file for this person is told nothing about their requests.
    $stranger = bookingUser(Role::Employee, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $stranger->id, 'joined_on' => '2020-01-01']);

    expect($this->actingAs($stranger)->getJson(route('leave-requests.conflicts', $query))->assertOk()->json('conflicts'))->toBe([]);
});

it('serves the question on the public entrance too', function () {
    bookLeave()->assertSessionHasNoErrors();

    $this->withHeaders(['X-Entrance' => 'public'])->actingAs($this->staffUser)
        ->getJson(route('leave-requests.conflicts', ['employee_id' => $this->staff->id, 'start_on' => '2026-10-21', 'end_on' => '2026-10-21']))
        ->assertOk()->assertJsonCount(1, 'conflicts');
});
