<?php

use App\Enums\Role;
use App\Filament\Resources\RequestTypes\Pages\CreateRequestType;
use App\Filament\Resources\RequestTypes\Pages\EditRequestType;
use App\Filament\Resources\RequestTypes\Pages\ListRequestTypes;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The kinds of request (jenis pengajuan) as data the administrators manage, and the optional
 * timeframe on a request.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->hr = typesUser(Role::HrAdmin, null);
    $this->staffUser = typesUser(Role::Employee, $this->company);
    $this->staff = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->staffUser->id, 'joined_on' => '2020-01-01']);
});

function typesUser(Role $role, ?Company $company): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fileTyped(User $as, Employee $employee, array $overrides = []): TestResponse
{
    return test()->actingAs($as)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id, 'leave_type' => 'annual', 'start_on' => '2026-11-03', 'end_on' => '2026-11-03', ...$overrides,
    ]);
}

// --- The six that existed ---

it('starts with the six kinds the app always had, in order', function () {
    expect(RequestType::ordered()->pluck('label', 'code')->all())->toBe([
        'sick' => 'Cuti sakit', 'annual' => 'Cuti tahunan', 'maternity' => 'Cuti melahirkan',
        'marriage' => 'Pernikahan / lamaran', 'bereavement' => 'Cuti duka', 'unpaid' => 'Cuti tanpa gaji',
    ]);
});

// --- Adding and removing ---

it('lets HR add a kind of request, with a code made from its name', function () {
    $this->actingAs($this->hr);

    Livewire::test(CreateRequestType::class)
        ->fillForm(['label' => 'Izin keluar kantor', 'max_days' => 2, 'sort_order' => 7, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $type = RequestType::where('label', 'Izin keluar kantor')->firstOrFail();

    expect($type->code)->toBe('izin-keluar-kantor')
        ->and($type->max_days)->toBe(2)
        ->and(Activity::where('log_name', 'request-types')->exists())->toBeTrue();

    $this->actingAs($this->staffUser)->get(route('leave-requests.create'))
        ->assertInertia(fn (Assert $page) => $page->where('leaveTypes', fn ($types) => collect($types)->contains('value', 'izin-keluar-kantor')));

    fileTyped($this->staffUser, $this->staff, ['leave_type' => 'izin-keluar-kantor'])->assertSessionHasNoErrors();
    expect(LeaveRequest::latest('id')->first()->typeLabel())->toBe('Izin keluar kantor');
});

it('does not let two kinds share a name or a code', function () {
    $this->actingAs($this->hr);

    Livewire::test(CreateRequestType::class)
        ->fillForm(['label' => 'Cuti tahunan'])
        ->call('create')
        ->assertHasFormErrors(['label']);

    expect(RequestType::uniqueCodeFor('Cuti Tahunan'))->toBe('cuti-tahunan')
        ->and(RequestType::uniqueCodeFor('Annual'))->toBe('annual-2');
});

it('removes a kind nobody used, and refuses to remove one that has been used', function () {
    $this->actingAs($this->hr);
    $unused = RequestType::create(['label' => 'Percobaan', 'is_active' => true]);

    Livewire::test(ListRequestTypes::class)->callTableAction('remove', $unused);
    expect(RequestType::where('code', 'percobaan')->exists())->toBeFalse();

    fileTyped($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $this->actingAs($this->hr);

    Livewire::test(ListRequestTypes::class)->callTableAction('remove', RequestType::where('code', 'annual')->first());
    expect(RequestType::where('code', 'annual')->exists())->toBeTrue();
});

it('switches a kind off for new requests while the old ones keep working', function () {
    fileTyped($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = LeaveRequest::latest('id')->first();

    $this->actingAs($this->hr);
    Livewire::test(EditRequestType::class, ['record' => RequestType::where('code', 'annual')->value('id')])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    // Not offered, and refused if sent anyway.
    $this->actingAs($this->staffUser)->get(route('leave-requests.create'))
        ->assertInertia(fn (Assert $page) => $page->where('leaveTypes', fn ($types) => ! collect($types)->contains('value', 'annual')));
    fileTyped($this->staffUser, $this->staff)->assertSessionHasErrors('leave_type');

    // The request already filed still shows its label, and can still be corrected as that type.
    expect($leave->fresh()->typeLabel())->toBe('Cuti tahunan');
    $this->actingAs($this->hr)->put(route('leave-requests.decision', $leave), ['decision' => 'approve']);
});

it('lets a rejected request be corrected as its own type even after that type is switched off', function () {
    fileTyped($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = LeaveRequest::latest('id')->first();
    $this->actingAs($this->hr)->put(route('leave-requests.decision', $leave), ['decision' => 'reject', 'note' => 'Ubah']);
    RequestType::where('code', 'annual')->update(['is_active' => false]);

    $this->actingAs($this->staffUser)->get(route('leave-requests.edit', $leave))
        ->assertInertia(fn (Assert $page) => $page->where('leaveTypes', fn ($types) => collect($types)->contains('value', 'annual')));

    $this->actingAs($this->staffUser)->put(route('leave-requests.update', $leave), [
        'leave_type' => 'annual', 'start_on' => '2026-11-03', 'end_on' => '2026-11-04',
    ])->assertSessionHasNoErrors();
});

it('keeps requests intact when a kind is renamed', function () {
    fileTyped($this->staffUser, $this->staff)->assertSessionHasNoErrors();

    $this->actingAs($this->hr);
    Livewire::test(EditRequestType::class, ['record' => RequestType::where('code', 'annual')->value('id')])
        ->fillForm(['label' => 'Cuti tahunan (baru)'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(RequestType::where('code', 'annual')->exists())->toBeTrue()
        ->and(LeaveRequest::latest('id')->first()->typeLabel())->toBe('Cuti tahunan (baru)');
});

it('sets the maximum days on the kind itself', function () {
    $this->actingAs($this->hr);
    Livewire::test(EditRequestType::class, ['record' => RequestType::where('code', 'sick')->value('id')])
        ->fillForm(['max_days' => 2])
        ->call('save')
        ->assertHasNoFormErrors();

    fileTyped($this->staffUser, $this->staff, ['leave_type' => 'sick', 'start_on' => '2026-11-03', 'end_on' => '2026-11-06'])
        ->assertSessionHasErrors('end_on');
});

it('is for HR only', function () {
    $this->actingAs($this->staffUser)->get('/admin/request-types')->assertForbidden();
    $this->actingAs($this->hr)->get('/admin/request-types')->assertOk();
});

// --- The timeframe ---

it('stores a timeframe only when the box is ticked', function () {
    fileTyped($this->staffUser, $this->staff, ['use_timeframe' => '1', 'start_time' => '09:00', 'end_time' => '11:30'])->assertSessionHasNoErrors();
    $with = LeaveRequest::latest('id')->first();

    fileTyped($this->staffUser, $this->staff, ['start_on' => '2026-11-04', 'end_on' => '2026-11-04', 'start_time' => '09:00', 'end_time' => '11:30'])->assertSessionHasNoErrors();
    $without = LeaveRequest::latest('id')->first();

    expect($with->timeframe())->toBe('09:00–11:30')
        ->and($with->days)->toBe(1)
        ->and($without->timeframe())->toBeNull()
        ->and($without->start_time)->toBeNull();
});

it('needs both times, in order, when the box is ticked', function () {
    fileTyped($this->staffUser, $this->staff, ['use_timeframe' => '1'])->assertSessionHasErrors(['start_time', 'end_time']);
    fileTyped($this->staffUser, $this->staff, ['use_timeframe' => '1', 'start_time' => '11:00', 'end_time' => '09:00'])->assertSessionHasErrors('end_time');
    fileTyped($this->staffUser, $this->staff, ['use_timeframe' => '1', 'start_time' => '9am', 'end_time' => '11:00'])->assertSessionHasErrors('start_time');

    expect(LeaveRequest::count())->toBe(0);
});

it('shows the timeframe in the queue, on the profile and in the notification', function () {
    $boss = typesUser(Role::Supervisor, $this->company);
    $bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $boss->id]);
    $this->staff->update(['manager_id' => $bossEmployee->id]);

    fileTyped($this->staffUser, $this->staff, ['use_timeframe' => '1', 'start_time' => '13:00', 'end_time' => '15:00'])->assertSessionHasNoErrors();

    $this->actingAs($this->staffUser)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('requests.0.timeframe', '13:00–15:00'));
    $this->actingAs($this->staffUser)->get(route('employees.show', $this->staff))
        ->assertInertia(fn (Assert $page) => $page->where('requests.0.timeframe', '13:00–15:00'));
    expect($boss->notifications()->first()->data['body'])->toContain('(13:00–15:00)');
});

it('lets a corrected request add, change or drop its timeframe, and says so in the history', function () {
    fileTyped($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = LeaveRequest::latest('id')->first();
    $this->actingAs($this->hr)->put(route('leave-requests.decision', $leave), ['decision' => 'reject', 'note' => 'Jam berapa?']);

    $this->actingAs($this->staffUser)->get(route('leave-requests.edit', $leave))
        ->assertInertia(fn (Assert $page) => $page->where('request.startTime', null));

    $this->actingAs($this->staffUser)->put(route('leave-requests.update', $leave), [
        'leave_type' => 'annual', 'start_on' => '2026-11-03', 'end_on' => '2026-11-03', 'use_timeframe' => '1', 'start_time' => '08:00', 'end_time' => '10:00',
    ])->assertSessionHasNoErrors();

    expect($leave->refresh()->timeframe())->toBe('08:00–10:00')
        ->and($leave->workflow->comments->last()->body)->toContain('jam 08:00–10:00');
});
