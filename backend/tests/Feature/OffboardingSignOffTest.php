<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OffboardingCase;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The last stage of an offboarding case, closing and cancelling it, and the exit itself (OFF-SIG, OFF-CHK-010).
 * There are no signatures: the HR who asked for the case fills in the exit details and closes it.
 *
 * Mira (Management) manages Rudi, who manages Sinta, the leaver; Dewi reports to Sinta. Lukas has no Atasan.
 * The last working day is 2 December 2026.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
    config(['attendance.device_timezone' => 'Asia/Jakarta']);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->hr = soUser(Role::HrAdmin, $this->company);
    $this->mira = soMember('Mira', null, Role::Management, $this->company);
    $this->rudi = soMember('Rudi', $this->mira, Role::Supervisor, $this->company);
    $this->sinta = soMember('Sinta', $this->rudi, Role::Employee, $this->company);
    $this->dewi = soMember('Dewi', $this->sinta, Role::Employee, $this->company);
    $this->lukas = soMember('Lukas', null, Role::Employee, $this->company);
});

function soUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function soMember(string $name, ?Employee $manager, Role $role, Company $company): Employee
{
    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name,
        'manager_id' => $manager?->id,
        'user_id' => soUser($role, $company)->id,
    ]);
}

/**
 * A case with its checklist complete (every department set aside), so it can go to sign-off.
 */
function soCaseAtChecklist(?Employee $leaver = null): OffboardingCase
{
    $leaver ??= test()->sinta;
    $hr = test()->hr;

    test()->actingAs($hr)->post(route('offboarding.store'), [
        'employee_id' => $leaver->id, 'exit_type' => 'resign', 'notice_on' => '2026-11-02', 'last_working_on' => '2026-12-02',
        'reason' => 'Pindah kota', 'compensation_months' => 0, 'send' => '1',
        'file_resignation_letter' => [UploadedFile::fake()->create('resign.pdf', 40, 'application/pdf')],
    ])->assertRedirect();

    $case = OffboardingCase::query()->latest('id')->firstOrFail();
    test()->actingAs(test()->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();

    foreach ($case->checklistDepartments()->get() as $group) {
        test()->actingAs($hr)->put(route('offboarding.checklist.involvement', [$case, $group]), ['not_involved' => true])->assertRedirect();
    }

    return $case->refresh();
}

function soCaseAtSignOff(?Employee $leaver = null): OffboardingCase
{
    $case = soCaseAtChecklist($leaver);
    soProceed(test()->hr, $case);

    return $case->refresh();
}

/**
 * HR approves the finished checklist, then moves the case on.
 */
function soProceed(User $by, OffboardingCase $case): void
{
    test()->actingAs($by)->post(route('offboarding.checklist.approve', $case))->assertRedirect();
    test()->actingAs($by)->post(route('offboarding.proceed', $case))->assertRedirect();
}

function soStage(OffboardingCase $case): string
{
    return (string) $case->refresh()->workflow->current_stage;
}

function soInterview(OffboardingCase $case)
{
    return soInterviewAs(test()->hr, $case);
}

function soInterviewAs(User $by, OffboardingCase $case)
{
    return test()->actingAs($by)->put(route('offboarding.interview', $case), [
        'reason' => 'Peluang karier', 'rehire' => '1', 'notes' => 'Baik-baik saja',
    ]);
}

// --- Sign-off ---

it('moves on to the last stage with no signatures, and tells the HR who asked for it when someone else moved it', function () {
    $other = soUser(Role::HrAdmin, $this->company);
    $case = soCaseAtChecklist();
    $this->hr->notifications()->delete();

    soProceed($other, $case);

    expect(soStage($case))->toBe('sign_off')
        ->and($this->hr->notifications()->get()->pluck('data.title')->implode(' '))->toContain('siap diselesaikan')
        // Neither the Atasan nor anybody else is asked to sign.
        ->and($this->rudi->user->notifications()->get()->pluck('data.title')->implode(' '))->not->toContain('giliran Anda')
        ->and(Route::has('offboarding.signoff.sign'))->toBeFalse();
});

it('has only the HR who asked for the case fill in the details and close it, and another HR when they cannot', function () {
    $case = soCaseAtSignOff();
    $other = soUser(Role::HrAdmin, $this->company);

    $this->actingAs($other)->put(route('offboarding.interview', $case), ['reason' => 'x', 'rehire' => '1'])->assertForbidden();
    $this->actingAs($other)->post(route('offboarding.close', $case))->assertForbidden();
    $this->actingAs($this->mira->user)->post(route('offboarding.close', $case))->assertForbidden();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.canClose', true)->where('case.signoff.exitInterview.canRecord', true));
    $this->actingAs($other)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.canClose', false)->where('case.signoff.exitInterview.canRecord', false));

    // The requester is deactivated: the other HR takes it over, so the case is never stuck.
    $this->hr->forceFill(['is_active' => false])->save();
    soInterviewAs($other, $case)->assertRedirect();
    expect($other->can('close', $case))->toBeTrue();
});

it('chains the checklist phase as owners signing off, then HR approving, with no requester or case approval', function () {
    $case = soCaseAtChecklist();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.checklist.chain.0.label', 'Pemilik item sign-off')
            ->where('case.checklist.chain.0.status', 'approved')
            ->where('case.checklist.chain.1.label', 'Persetujuan HR')
            ->where('case.checklist.chain.1.status', 'pending')
            ->where('case.checklist.canApprove', true)
            ->has('case.checklist.chain', 2));

    // An owner who has not confirmed holds up both checks.
    $group = $case->checklistDepartments()->first();
    $group->update(['not_involved' => false, 'confirmed_at' => null]);

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.chain.0.status', 'pending')->where('case.checklist.chain.1.status', 'locked')->where('case.checklist.canApprove', false));
    $this->actingAs($this->hr)->post(route('offboarding.checklist.approve', $case))->assertSessionHasErrors('approve');
});

it('has any HR approve the checklist, and takes the approval back when the checklist changes', function () {
    $case = soCaseAtChecklist();
    $other = soUser(Role::HrAdmin, $this->company);

    $this->actingAs($other)->post(route('offboarding.checklist.approve', $case))->assertRedirect();
    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.chain.1.status', 'approved')->where('case.checklist.chain.1.decidedBy', $other->name)->where('case.checklist.approved', true));

    // A new item turns up: what HR approved is no longer what is there.
    $group = $case->checklistDepartments()->first();
    $this->actingAs($this->hr)->post(route('offboarding.checklist.adhoc', [$case, $group]), ['name' => 'Serah terima klien', 'kind' => 'process'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.approved', false));
    expect($case->refresh()->checklist_approved_at)->toBeNull();
});

it('has no chain of steps at the last stage, only the exit form, and a close that waits for it', function () {
    $case = soCaseAtSignOff();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('case.signoff.steps')
            ->where('case.signoff.exitInterview.recorded', false)
            ->where('case.signoff.closeBlockers.0', 'Catat wawancara keluar dulu, atau lewati dengan alasan.'));

    soInterview($case)->assertRedirect();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.signoff.exitInterview.recorded', true)->where('case.signoff.closeBlockers.0', fn ($reason) => str_contains($reason, 'hari kerja terakhir')));
});

it('records the exit interview, or skips it with a reason, and only for HR', function () {
    $case = soCaseAtSignOff();

    $this->actingAs($this->hr)->put(route('offboarding.interview', $case), [])->assertSessionHasErrors(['reason', 'rehire']);
    $this->actingAs($this->hr)->put(route('offboarding.interview', $case), ['skip' => '1'])->assertSessionHasErrors('skipped_reason');
    $this->actingAs($this->rudi->user)->put(route('offboarding.interview', $case), ['reason' => 'x', 'rehire' => '1'])->assertForbidden();

    soInterview($case)->assertRedirect();
    expect($case->refresh()->exit_interview_reason)->toBe('Peluang karier')->and($case->exit_interview_rehire)->toBeTrue();

    $this->actingAs($this->hr)->put(route('offboarding.interview', $case), ['skip' => '1', 'skipped_reason' => 'Diberhentikan, tidak relevan'])->assertRedirect();
    $case->refresh();

    expect($case->exit_interview_skipped_reason)->toBe('Diberhentikan, tidak relevan')->and($case->exit_interview_reason)->toBeNull();
});

it('lets HR send the case back to the checklist with a note, and then on again', function () {
    $case = soCaseAtSignOff();

    $this->actingAs($this->hr)->post(route('offboarding.send-back', $case), [])->assertSessionHasErrors('note');
    $this->actingAs($this->rudi->user)->post(route('offboarding.send-back', $case), ['note' => 'x'])->assertForbidden();
    $this->actingAs($this->lukas->user)->post(route('offboarding.send-back', $case), ['note' => 'x'])->assertForbidden();
    $this->actingAs($this->hr)->post(route('offboarding.send-back', $case), ['note' => 'Laptop belum diperiksa IT'])->assertRedirect();

    expect(soStage($case))->toBe('checklist')
        ->and($case->workflow->comments()->pluck('body')->all())->toContain('Laptop belum diperiksa IT')
        ->and($case->workflow->comments()->first()->stage)->toBe('sign_off');

    soProceed($this->hr, $case);
    expect(soStage($case))->toBe('sign_off');
});

it('says what stands in the way of closing', function () {
    $case = soCaseAtSignOff();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.signoff.closeBlockers', ['Catat wawancara keluar dulu, atau lewati dengan alasan.', 'Kasus baru bisa ditutup setelah hari kerja terakhir (2026-12-02).', 'Masih ada 1 bawahan langsung yang belum dipindahkan ke atasan baru.'])
            ->where('case.canClose', true));

    $this->actingAs($this->hr)->post(route('offboarding.close', $case))->assertSessionHasErrors('close');
    $this->actingAs($this->mira->user)->post(route('offboarding.close', $case))->assertForbidden();
});

// --- The leaver's reports ---

it('moves the leaver\'s reports to a new Atasan, and refuses a bad move', function () {
    $case = soCaseAtSignOff();
    $move = fn (array $moves) => $this->actingAs($this->hr)->post(route('offboarding.reports', $case), ['moves' => $moves]);

    $move([$this->lukas->id => $this->rudi->id])->assertSessionHasErrors('moves');
    $move([$this->dewi->id => $this->sinta->id])->assertSessionHasErrors('moves');
    $move([$this->dewi->id => 999999])->assertSessionHasErrors('moves');
    $this->actingAs($this->rudi->user)->post(route('offboarding.reports', $case), ['moves' => [$this->dewi->id => $this->rudi->id]])->assertForbidden();

    $move([$this->dewi->id => $this->rudi->id])->assertRedirect();

    expect($this->dewi->refresh()->manager_id)->toBe($this->rudi->id);
});

it('will not put someone under a person who works for them', function () {
    $case = soCaseAtSignOff();
    $deeper = soMember('Bawahan Dewi', $this->dewi, Role::Employee, $this->company);

    $this->actingAs($this->hr)->post(route('offboarding.reports', $case), ['moves' => [$this->dewi->id => $deeper->id]])->assertSessionHasErrors('moves');
});

it('shows HR the reports and the departments the leaver heads, and warns when a case is started', function () {
    $department = Department::factory()->forCompany($this->company)->create(['name' => 'Operasional', 'supervisor_employee_id' => $this->sinta->id]);
    $case = soCaseAtSignOff();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.impact.reports.0.name', 'Dewi')
            ->where('case.impact.defaultManagerId', $this->rudi->id)
            ->where('case.impact.headOf', [$department->name]));

    $this->actingAs($this->hr)->get(route('offboarding.create'))
        ->assertInertia(fn (Assert $page) => $page->where('employees', fn ($employees) => collect($employees)->firstWhere('name', 'Sinta') === null
            || collect($employees)->firstWhere('name', 'Sinta')['reportsCount'] === 1));
});

// --- Closing ---

it('closes after the last day, with the details filled in and the reports moved, and makes the exit final', function () {
    AttendanceEnrolMap::create(['company_id' => $this->company->id, 'employee_id' => $this->sinta->id, 'device_sn' => 'SN1', 'pin' => '77']);
    $case = soCaseAtSignOff();
    $this->actingAs($this->hr)->post(route('offboarding.reports', $case), ['moves' => [$this->dewi->id => $this->rudi->id]])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.files.store', $case), ['file_reference_letter' => [UploadedFile::fake()->create('ref.pdf', 20, 'application/pdf')]])->assertRedirect();
    soInterview($case)->assertRedirect();

    // Filled in, but the last working day (2 December) has not passed.
    $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00', 'Asia/Jakarta'));
    $this->actingAs($this->hr)->post(route('offboarding.close', $case))->assertSessionHasErrors('close');
    expect(soStage($case))->toBe('sign_off');

    $this->travelTo(CarbonImmutable::parse('2026-12-03 09:00', 'Asia/Jakarta'));
    $this->actingAs($this->hr)->post(route('offboarding.close', $case))->assertRedirect();

    $case->refresh();
    $this->sinta->refresh();

    expect(soStage($case))->toBe('closed')
        ->and($case->closed_at)->not->toBeNull()
        ->and($this->sinta->employment_status)->toBe(EmploymentStatus::Inactive)
        ->and($this->sinta->exited_on->toDateString())->toBe('2026-12-02')
        ->and($this->sinta->user->refresh()->is_active)->toBeFalse()
        // Roles are kept.
        ->and($this->sinta->user->hasRole(Role::Employee->value))->toBeTrue()
        ->and(AttendanceEnrolMap::first()->valid_to->toDateString())->toBe('2026-12-02')
        ->and($case->exit_applied_at)->not->toBeNull();

    // The resignation letter and the reference letter are on the dossier, restricted.
    $documents = $this->sinta->documents()->get();
    expect($documents)->toHaveCount(2)
        ->and($documents->every->is_restricted)->toBeTrue()
        ->and($documents->pluck('title')->implode(' '))->toContain('Surat referensi')->toContain('Surat pengunduran diri');
    Storage::disk('local')->assertExists($documents->first()->versions()->first()->path);

    // A locked record cannot be edited, and a closed case cannot be reopened.
    expect($this->hr->can('update', $this->sinta))->toBeFalse();
    $this->actingAs($this->hr)->post(route('offboarding.send-back', $case))->assertForbidden();
});

it('refuses to close while a report has not been moved', function () {
    $case = soCaseAtSignOff();
    soInterview($case);
    $this->travelTo(CarbonImmutable::parse('2026-12-03 09:00', 'Asia/Jakarta'));

    $this->actingAs($this->hr)->post(route('offboarding.close', $case))
        ->assertSessionHasErrors(['close' => 'Masih ada 1 bawahan langsung yang belum dipindahkan ke atasan baru.']);
});

// --- The last working day, and cancelling ---

it('lets HR change the last working day until the exit is applied, and tells Management', function () {
    $case = soCaseAtSignOff();
    $this->mira->user->notifications()->delete();

    $this->actingAs($this->rudi->user)->put(route('offboarding.last-day', $case), ['last_working_on' => '2026-12-10'])->assertForbidden();
    $this->actingAs($this->hr)->put(route('offboarding.last-day', $case), ['last_working_on' => '2026-11-01'])->assertSessionHasErrors('last_working_on');
    $this->actingAs($this->hr)->put(route('offboarding.last-day', $case), ['last_working_on' => '2026-12-10', 'note' => 'Serah terima diperpanjang'])->assertRedirect();

    expect($case->refresh()->last_working_on->toDateString())->toBe('2026-12-10')
        ->and($case->workflow->comments()->latest('id')->first()->body)->toContain('2026-12-02')->toContain('2026-12-10')->toContain('Serah terima diperpanjang')
        ->and($this->mira->user->notifications()->count())->toBe(1);

    // No new approval is asked for.
    expect(soStage($case))->toBe('sign_off');

    $this->artisan('offboarding:apply-exits')->assertSuccessful();
    $this->travelTo(CarbonImmutable::parse('2026-12-11 09:00', 'Asia/Jakarta'));
    $this->artisan('offboarding:apply-exits')->assertSuccessful();

    $this->actingAs($this->hr)->put(route('offboarding.last-day', $case), ['last_working_on' => '2026-12-20'])->assertForbidden();
});

it('lets HR cancel a case with a reason until the exit is applied, and offboard the person again later', function () {
    $case = soCaseAtChecklist();

    $this->actingAs($this->hr)->post(route('offboarding.cancel', $case), [])->assertSessionHasErrors('note');
    $this->actingAs($this->mira->user)->post(route('offboarding.cancel', $case), ['note' => 'x'])->assertForbidden();
    $this->actingAs($this->hr)->post(route('offboarding.cancel', $case), ['note' => 'Pengunduran diri ditarik'])->assertRedirect();

    $case->refresh();
    expect(soStage($case))->toBe('cancelled')
        ->and($case->cancel_reason)->toBe('Pengunduran diri ditarik')
        ->and($case->cancelled_by_user_id)->toBe($this->hr->id)
        ->and($this->sinta->refresh()->employment_status)->toBe(EmploymentStatus::Active)
        ->and($this->sinta->user->refresh()->is_active)->toBeTrue();

    $this->actingAs($this->hr)->get(route('offboarding.index', ['status' => 'cancelled']))->assertInertia(fn (Assert $page) => $page->has('cases', 1)->where('cases.0.statusLabel', 'Dibatalkan'));
    $this->actingAs($this->hr)->get(route('offboarding.index'))->assertInertia(fn (Assert $page) => $page->has('cases', 0));

    // A cancelled case does not block a new one.
    $this->actingAs($this->hr)->post(route('offboarding.store'), ['employee_id' => $this->sinta->id])->assertRedirect();
});

it('will not cancel once the exit has been applied', function () {
    $case = soCaseAtSignOff();
    $this->travelTo(CarbonImmutable::parse('2026-12-03 09:00', 'Asia/Jakarta'));
    $this->artisan('offboarding:apply-exits')->assertSuccessful();

    $this->actingAs($this->hr)->post(route('offboarding.cancel', $case), ['note' => 'Terlambat'])->assertForbidden();
    expect(soStage($case))->toBe('sign_off');
});

// --- The exit ---

it('applies the exit the day after the last working day, not before, and only once', function () {
    $case = soCaseAtSignOff();

    $this->travelTo(CarbonImmutable::parse('2026-12-02 23:00', 'Asia/Jakarta'));
    $this->artisan('offboarding:apply-exits')->assertSuccessful();
    expect($this->sinta->user->refresh()->is_active)->toBeTrue()->and($case->refresh()->exit_applied_at)->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-12-03 00:45', 'Asia/Jakarta'));
    $this->artisan('offboarding:apply-exits')->expectsOutputToContain('1 exit(s) applied.')->assertSuccessful();

    expect($this->sinta->user->refresh()->is_active)->toBeFalse()
        ->and($this->sinta->refresh()->exited_on->toDateString())->toBe('2026-12-02')
        // Still "aktif" until the case is closed.
        ->and($this->sinta->employment_status)->toBe(EmploymentStatus::Active);

    $this->artisan('offboarding:apply-exits')->expectsOutputToContain('0 exit(s) applied.')->assertSuccessful();
});

it('clears what the leaver still has waiting when the exit is applied', function () {
    $case = soCaseAtSignOff();
    $this->actingAs($this->sinta->user)->post(route('leave-requests.store'), [
        'employee_id' => $this->sinta->id, 'leave_type' => 'annual', 'start_on' => '2026-12-21', 'end_on' => '2026-12-21', 'reason' => 'Rencana',
    ])->assertRedirect();
    $leave = LeaveRequest::query()->latest('id')->firstOrFail();
    $change = ProfileChangeRequest::factory()->create(['employee_id' => $this->sinta->id]);

    expect($leave->workflow->current_stage)->toBe('submitted')->and($change->status->value)->toBe('pending');

    $this->travelTo(CarbonImmutable::parse('2026-12-03 00:45', 'Asia/Jakarta'));
    $this->artisan('offboarding:apply-exits')->assertSuccessful();

    $leave->refresh();
    expect($leave->workflow->current_stage)->toBe('rejected')
        ->and($leave->workflow->steps()->first()->note)->toContain('Dibatalkan otomatis')
        ->and($leave->workflow->steps()->where('status', 'pending')->count())->toBe(0)
        ->and($change->refresh()->status->value)->toBe('withdrawn');

    // Nobody is left with a request waiting on someone who has gone.
    $this->actingAs($this->rudi->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('pendingForMe', 0));
});

it('does not apply the exit of a case that is still waiting for Management, or a draft', function () {
    $this->actingAs($this->hr)->post(route('offboarding.store'), [
        'employee_id' => $this->sinta->id, 'exit_type' => 'contract_end', 'notice_on' => '2026-11-02', 'last_working_on' => '2026-11-30', 'reason' => 'Selesai', 'send' => '1',
    ])->assertRedirect();

    $this->travelTo(CarbonImmutable::parse('2026-12-10 09:00', 'Asia/Jakarta'));
    $this->artisan('offboarding:apply-exits')->expectsOutputToContain('0 exit(s) applied.')->assertSuccessful();

    expect($this->sinta->user->refresh()->is_active)->toBeTrue();
});

// --- The handover report ---

it('serves the handover report from sign-off on, to people who may see the case', function () {
    $case = soCaseAtChecklist();

    $this->actingAs($this->hr)->get(route('offboarding.berita-acara', $case))->assertForbidden();

    soProceed($this->hr, $case);

    $this->actingAs($this->hr)->get(route('offboarding.berita-acara', $case))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('offboarding/berita-acara')->where('case.reference', $case->reference()));
    $this->actingAs($this->mira->user)->get(route('offboarding.berita-acara', $case))->assertOk();
    $this->actingAs($this->lukas->user)->get(route('offboarding.berita-acara', $case))->assertForbidden();
});
