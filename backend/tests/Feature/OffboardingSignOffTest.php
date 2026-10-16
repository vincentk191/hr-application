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
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Sign-off, closing and cancelling an offboarding case, and the exit itself (OFF-SIG, OFF-CHK-010).
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
    test()->actingAs(test()->hr)->post(route('offboarding.proceed', $case))->assertRedirect();

    return $case->refresh();
}

function soStage(OffboardingCase $case): string
{
    return (string) $case->refresh()->workflow->current_stage;
}

function soSign(User $by, OffboardingCase $case, int $position)
{
    $signoff = $case->signoffs()->where('position', $position)->firstOrFail();

    return test()->actingAs($by)->post(route('offboarding.signoff.sign', [$case, $signoff]), ['note' => 'Sudah diperiksa']);
}

function soInterview(OffboardingCase $case)
{
    return test()->actingAs(test()->hr)->put(route('offboarding.interview', $case), [
        'reason' => 'Peluang karier', 'rehire' => '1', 'notes' => 'Baik-baik saja',
    ]);
}

// --- Sign-off ---

it('sets up the Atasan then HR when the checklist is complete, and tells the Atasan', function () {
    $case = soCaseAtSignOff();

    expect(soStage($case))->toBe('sign_off')
        ->and($case->signoffs()->pluck('label')->all())->toBe(['Atasan langsung', 'HR Admin'])
        ->and($case->signoffs()->first()->user_id)->toBe($this->rudi->user_id)
        ->and($case->signoffs()->where('position', 2)->value('user_id'))->toBeNull()
        ->and($this->rudi->user->notifications()->get()->pluck('data.title')->implode(' '))->toContain('giliran Anda');
});

it('has HR sign alone when the leaver has no usable Atasan', function () {
    $case = soCaseAtSignOff($this->lukas);

    expect($case->signoffs()->pluck('label')->all())->toBe(['HR Admin'])
        ->and($case->signoffs()->first()->position)->toBe(1);

    // An Atasan who is deactivated counts as none.
    $this->rudi->user->forceFill(['is_active' => false])->save();
    $other = soCaseAtSignOff($this->sinta);

    expect($other->signoffs()->pluck('label')->all())->toBe(['HR Admin']);
});

it('keeps each signature locked until the one before it is done, and HR\'s until the exit interview is in', function () {
    $case = soCaseAtSignOff();

    soSign($this->hr, $case, 2)->assertForbidden();
    soSign($this->mira->user, $case, 1)->assertForbidden();
    soSign($this->sinta->user, $case, 1)->assertForbidden();
    soSign($this->rudi->user, $case, 1)->assertRedirect();

    // Now HR's turn, but the exit interview has to be on the case first.
    soSign($this->hr, $case, 2)->assertSessionHasErrors(['signoff' => 'Catat wawancara keluar dulu, atau lewati dengan alasan.']);
    soInterview($case)->assertRedirect();
    soSign($this->hr, $case, 2)->assertRedirect();

    expect($case->signoffs()->whereNull('signed_at')->count())->toBe(0);
    soSign($this->hr, $case, 2)->assertForbidden();
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

it('lets a signer or HR send the case back to the checklist with a note, clearing the signatures', function () {
    $case = soCaseAtSignOff();

    // The Atasan, while their own signature is the open one.
    $this->actingAs($this->rudi->user)->post(route('offboarding.send-back', $case), [])->assertSessionHasErrors('note');
    $this->actingAs($this->lukas->user)->post(route('offboarding.send-back', $case), ['note' => 'x'])->assertForbidden();
    $this->actingAs($this->rudi->user)->post(route('offboarding.send-back', $case), ['note' => 'Laptop belum diperiksa IT'])->assertRedirect();

    expect(soStage($case))->toBe('checklist')
        ->and($case->signoffs()->count())->toBe(0)
        ->and($case->workflow->comments()->pluck('body')->all())->toContain('Laptop belum diperiksa IT')
        ->and($case->workflow->comments()->first()->stage)->toBe('sign_off');

    // Back to sign-off; once the Atasan has signed it is no longer theirs to send back, but HR still may.
    $this->actingAs($this->hr)->post(route('offboarding.proceed', $case))->assertRedirect();
    soSign($this->rudi->user, $case, 1)->assertRedirect();

    $this->actingAs($this->rudi->user)->post(route('offboarding.send-back', $case), ['note' => 'Lagi'])->assertForbidden();
    $this->actingAs($this->hr)->post(route('offboarding.send-back', $case), ['note' => 'Ada yang terlewat'])->assertRedirect();

    expect(soStage($case))->toBe('checklist')->and($case->signoffs()->count())->toBe(0);
});

it('says what stands in the way of closing', function () {
    $case = soCaseAtSignOff();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.signoff.closeBlockers', ['Masih menunggu 2 tanda tangan.', 'Kasus baru bisa ditutup setelah hari kerja terakhir (2026-12-02).', 'Masih ada 1 bawahan langsung yang belum dipindahkan ke atasan baru.'])
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

it('closes after the last day, with everything signed and the reports moved, and makes the exit final', function () {
    AttendanceEnrolMap::create(['company_id' => $this->company->id, 'employee_id' => $this->sinta->id, 'device_sn' => 'SN1', 'pin' => '77']);
    $case = soCaseAtSignOff();
    $this->actingAs($this->hr)->post(route('offboarding.reports', $case), ['moves' => [$this->dewi->id => $this->rudi->id]])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.files.store', $case), ['file_reference_letter' => [UploadedFile::fake()->create('ref.pdf', 20, 'application/pdf')]])->assertRedirect();
    soSign($this->rudi->user, $case, 1)->assertRedirect();
    soInterview($case)->assertRedirect();
    soSign($this->hr, $case, 2)->assertRedirect();

    // Signed, but the last working day (2 December) has not passed.
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
    soSign($this->rudi->user, $case, 1);
    soInterview($case);
    soSign($this->hr, $case, 2);
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

    $this->actingAs($this->hr)->post(route('offboarding.proceed', $case))->assertRedirect();

    $this->actingAs($this->hr)->get(route('offboarding.berita-acara', $case))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('offboarding/berita-acara')->where('case.reference', $case->reference()));
    $this->actingAs($this->mira->user)->get(route('offboarding.berita-acara', $case))->assertOk();
    $this->actingAs($this->lukas->user)->get(route('offboarding.berita-acara', $case))->assertForbidden();
});

it('shows who signed and when on the case page, in order', function () {
    $case = soCaseAtSignOff();
    soSign($this->rudi->user, $case, 1)->assertRedirect();

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.signoff.steps.0.status', 'approved')
            ->where('case.signoff.steps.0.decidedBy', $this->rudi->user->name)
            ->where('case.signoff.steps.1.status', 'pending')
            ->where('case.signoff.steps.1.canSign', true)
            ->where('case.signoff.exitInterview.canRecord', true));

    $this->actingAs($this->rudi->user)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.signoff.steps.1.canSign', false)->where('case.canClose', false));
});
