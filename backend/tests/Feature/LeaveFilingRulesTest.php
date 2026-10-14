<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Who a request may be filed for, which start dates are allowed, and what happens when the Atasan
 * files it (REQ-WIZ-002, REQ-WIZ-013/014/015).
 *
 * Today is Tuesday 2026-10-20 in Jakarta. The tree: Mira (Management) manages Rudi; Rudi manages Sinta.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    config(['attendance.device_timezone' => 'Asia/Jakarta']);

    $this->company = Company::factory()->create();
    $this->hr = ruleUser(Role::HrAdmin, $this->company);

    $this->mira = ruleMember('Mira', null, Role::Management, $this->company);
    $this->rudi = ruleMember('Rudi', $this->mira, Role::Supervisor, $this->company);
    $this->sinta = ruleMember('Sinta', $this->rudi, Role::Employee, $this->company);
    $this->outsider = ruleMember('Luar', null, Role::Employee, $this->company);
});

function ruleUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function ruleMember(string $name, ?Employee $manager, Role $role, Company $company): Employee
{
    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name,
        'manager_id' => $manager?->id,
        'user_id' => ruleUser($role, $company)->id,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ruleFile(User $as, Employee $employee, array $overrides = []): TestResponse
{
    return test()->actingAs($as)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_on' => '2026-10-21',
        'end_on' => '2026-10-21',
        'reason' => 'Keperluan',
        ...$overrides,
    ]);
}

function ruleStage(): string
{
    return (string) LeaveRequest::query()->latest('id')->firstOrFail()->workflow->current_stage;
}

// --- Whom a request may be filed for ---

it('lets everyone file only for themselves, and Management for their downline', function () {
    ruleFile($this->sinta->user, $this->sinta)->assertRedirect();
    ruleFile($this->sinta->user, $this->rudi)->assertSessionHasErrors('employee_id');
    ruleFile($this->rudi->user, $this->sinta)->assertSessionHasErrors('employee_id');

    ruleFile($this->mira->user, $this->sinta)->assertRedirect();
    ruleFile($this->mira->user, $this->rudi)->assertRedirect();
    ruleFile($this->mira->user, $this->outsider)->assertSessionHasErrors('employee_id');

    ruleFile($this->hr, $this->outsider)->assertRedirect();
});

it('offers each person only the employees they may file for', function () {
    $options = fn (User $user): array => collect(
        $this->actingAs($user)->get(route('leave-requests.create'))->assertOk()->viewData('page')['props']['employees'],
    )->map(fn (array $row): string => explode(' (', $row['label'])[0])->sort()->values()->all();

    expect($options($this->sinta->user))->toBe(['Sinta'])
        ->and($options($this->rudi->user))->toBe(['Rudi'])
        ->and($options($this->mira->user))->toBe(['Mira', 'Rudi', 'Sinta'])
        ->and($options($this->hr))->toHaveCount(4);

    $this->actingAs($this->mira->user)->get(route('leave-requests.create'))
        ->assertInertia(fn (Assert $page) => $page->where('canFileForOthers', true)->where('canBackdate', true));
    $this->actingAs($this->rudi->user)->get(route('leave-requests.create'))
        ->assertInertia(fn (Assert $page) => $page->where('canFileForOthers', false)->where('canBackdate', false));
});

// --- Start dates ---

it('refuses a start date before today for an employee, and accepts today', function () {
    ruleFile($this->sinta->user, $this->sinta, ['start_on' => '2026-10-19', 'end_on' => '2026-10-19'])
        ->assertSessionHasErrors(['start_on' => 'Hari pertama tidak boleh sebelum hari ini.']);

    expect(LeaveRequest::count())->toBe(0);

    ruleFile($this->sinta->user, $this->sinta, ['start_on' => '2026-10-20', 'end_on' => '2026-10-20'])->assertRedirect();

    expect(LeaveRequest::count())->toBe(1);
});

it('reads today in Jakarta, not in UTC', function () {
    // 05:00 Jakarta on the 21st is still the 20th in UTC; the 21st is today.
    $this->travelTo(CarbonImmutable::parse('2026-10-21 05:00', 'Asia/Jakarta'));

    ruleFile($this->sinta->user, $this->sinta, ['start_on' => '2026-10-21', 'end_on' => '2026-10-21'])->assertRedirect();
    ruleFile($this->sinta->user, $this->sinta, ['start_on' => '2026-10-20', 'end_on' => '2026-10-20'])->assertSessionHasErrors('start_on');
});

it('lets a kind allow a number of calendar days back, and no more', function () {
    RequestType::query()->where('code', 'sick')->update(['max_back_days' => 3]);

    // Monday 19th is one day back; Friday 16th is four days back.
    ruleFile($this->sinta->user, $this->sinta, ['leave_type' => 'sick', 'start_on' => '2026-10-19', 'end_on' => '2026-10-19'])->assertRedirect();
    ruleFile($this->sinta->user, $this->sinta, ['leave_type' => 'sick', 'start_on' => '2026-10-16', 'end_on' => '2026-10-16'])
        ->assertSessionHasErrors(['start_on' => 'Cuti sakit hanya boleh dimulai paling lama 3 hari ke belakang.']);
    // A kind with no setting still refuses.
    ruleFile($this->sinta->user, $this->sinta, ['leave_type' => 'annual', 'start_on' => '2026-10-19', 'end_on' => '2026-10-19'])
        ->assertSessionHasErrors('start_on');
});

it('lets HR Admin and Management start a request in the past, but not a Supervisor', function () {
    $past = ['start_on' => '2026-10-05', 'end_on' => '2026-10-05'];

    ruleFile($this->hr, $this->outsider, $past)->assertRedirect();
    ruleFile($this->mira->user, $this->sinta, $past)->assertRedirect();
    ruleFile($this->rudi->user, $this->rudi, $past)->assertSessionHasErrors('start_on');
});

it('lets a rejected request be sent again with its past dates unchanged, but not moved to other past dates', function () {
    ruleFile($this->sinta->user, $this->sinta, ['start_on' => '2026-10-21', 'end_on' => '2026-10-21'])->assertRedirect();
    $leave = LeaveRequest::query()->latest('id')->firstOrFail();

    // The dates pass while the request waits for its decision.
    $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Asia/Jakarta'));
    $this->actingAs($this->rudi->user)->put(route('leave-requests.decision', $leave), ['decision' => 'reject', 'note' => 'Lengkapi catatan'])->assertRedirect();

    $resend = fn (array $overrides = []): TestResponse => $this->actingAs($this->sinta->user)->put(route('leave-requests.update', $leave), [
        'leave_type' => 'annual', 'start_on' => '2026-10-21', 'end_on' => '2026-10-21', 'reason' => 'Dengan catatan', ...$overrides,
    ]);

    $resend(['start_on' => '2026-10-22', 'end_on' => '2026-10-22'])->assertSessionHasErrors('start_on');
    $resend()->assertSessionHasNoErrors();

    expect(ruleStage())->toBe('submitted');
});

// --- Filed by the Atasan ---

it('approves at once a request the Atasan files for their direct report', function () {
    ruleFile($this->mira->user, $this->rudi)->assertRedirect();

    $leave = LeaveRequest::query()->latest('id')->firstOrFail();
    $step = $leave->workflow->steps()->firstOrFail();

    expect($leave->workflow->current_stage)->toBe('approved')
        ->and($step->decided_by_user_id)->toBe($this->mira->user->id)
        ->and($step->note)->toBe('Diajukan dan disetujui oleh atasan');
});

it('leaves a request filed for someone further down with that person\'s own Atasan', function () {
    ruleFile($this->mira->user, $this->sinta)->assertRedirect();

    $leave = LeaveRequest::query()->latest('id')->firstOrFail();

    expect($leave->workflow->current_stage)->toBe('submitted')
        ->and($leave->workflow->steps()->firstOrFail()->approver_user_id)->toBe($this->rudi->user->id);
});

it('does not auto-approve a request filed by HR, or by the employee for themselves', function () {
    ruleFile($this->hr, $this->rudi)->assertRedirect();
    expect(ruleStage())->toBe('submitted');

    ruleFile($this->rudi->user, $this->rudi)->assertRedirect();
    expect(ruleStage())->toBe('submitted');
});

it('tells the employee and every HR Admin about a request approved on filing, but not the Atasan', function () {
    ruleFile($this->mira->user, $this->rudi)->assertRedirect();

    $titles = fn (User $user): array => $user->notifications->pluck('data.title')->all();

    expect($titles($this->rudi->user))->toHaveCount(1)->and($titles($this->rudi->user)[0])->toContain('disetujui')
        ->and($this->hr->notifications()->count())->toBe(1)
        ->and($this->mira->user->notifications()->count())->toBe(0)
        ->and(DatabaseNotification::count())->toBe(2);
});
