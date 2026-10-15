<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Offboarding: HR makes the case, Management approves it (OFF-DSH, OFF-WIZ, OFF-PIP, OFF-APR).
 *
 * The tree: Mira (Management) manages Rudi (Supervisor); Rudi manages Sinta, who is leaving. Lukas
 * reports to no one on that line.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->hr = offUser(Role::HrAdmin, $this->company);

    $this->mira = offMember('Mira', null, Role::Management, $this->company);
    $this->rudi = offMember('Rudi', $this->mira, Role::Supervisor, $this->company);
    $this->sinta = offMember('Sinta', $this->rudi, Role::Employee, $this->company);
    $this->lukas = offMember('Lukas', null, Role::Supervisor, $this->company);
});

function offUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function offMember(string $name, ?Employee $manager, Role $role, Company $company): Employee
{
    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name,
        'manager_id' => $manager?->id,
        'user_id' => offUser($role, $company)->id,
    ]);
}

function letter(): UploadedFile
{
    return UploadedFile::fake()->create('resign.pdf', 40, 'application/pdf');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function draftCase(User $as, Employee $employee, array $overrides = []): TestResponse
{
    return test()->actingAs($as)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'exit_type' => 'resign',
        'notice_on' => '2026-11-02',
        'last_working_on' => '2026-12-02',
        'reason' => 'Pindah kota',
        'compensation_months' => 0,
        ...$overrides,
    ]);
}

function latestCase(): OffboardingCase
{
    return OffboardingCase::query()->latest('id')->firstOrFail();
}

/**
 * A complete case, sent for approval.
 */
function submittedCase(Employee $employee, ?User $by = null): OffboardingCase
{
    $by ??= test()->hr;

    draftCase($by, $employee, ['file_resignation_letter' => [letter()]])->assertRedirect();
    $case = latestCase();
    test()->actingAs($by)->post(route('offboarding.submit', $case))->assertRedirect(route('offboarding.show', $case));

    return $case->refresh();
}

function stageOf(OffboardingCase $case): string
{
    return (string) $case->refresh()->workflow->current_stage;
}

// --- Starting a case ---

it('lets HR save a draft with just an employee, and finish it later', function () {
    draftCase($this->hr, $this->sinta, ['exit_type' => null, 'notice_on' => null, 'last_working_on' => null, 'reason' => null])
        ->assertRedirect(route('offboarding.edit', latestCase()));

    $case = latestCase();

    expect($case->reference())->toBe('OFB-'.str_pad((string) $case->id, 4, '0', STR_PAD_LEFT))
        ->and(stageOf($case))->toBe('draft')
        ->and($case->created_by_user_id)->toBe($this->hr->id);

    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['exit_type' => 'resign', 'reason' => 'Pindah kota', 'compensation_months' => 2, 'compensation_reason' => 'Masa kerja panjang'])
        ->assertRedirect();

    expect($case->refresh()->reason)->toBe('Pindah kota')->and($case->compensation_months)->toBe(2);
});

it('lets only HR start a case', function () {
    foreach ([$this->mira->user, $this->rudi->user, $this->sinta->user] as $user) {
        draftCase($user, $this->lukas)->assertForbidden();
    }

    expect(OffboardingCase::count())->toBe(0);
});

it('refuses an employee who cannot be offboarded', function () {
    draftCase($this->hr, $this->sinta, ['employee_id' => ''])->assertSessionHasErrors('employee_id');

    $gone = Employee::factory()->forCompany($this->company)->create(['employment_status' => EmploymentStatus::Inactive]);
    draftCase($this->hr, $gone)->assertSessionHasErrors(['employee_id' => 'Karyawan ini sudah tidak aktif.']);

    $hrRecord = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->hr->id]);
    draftCase($this->hr, $hrRecord)->assertSessionHasErrors('employee_id');

    draftCase($this->hr, $this->sinta)->assertRedirect();
    draftCase($this->hr, $this->sinta)->assertSessionHasErrors(['employee_id' => 'Karyawan ini sudah punya kasus offboarding yang belum ditutup.']);

    $elsewhere = Employee::factory()->forCompany(Company::factory()->create())->create();
    draftCase($this->hr, $elsewhere)->assertSessionHasErrors('employee_id');

    expect(OffboardingCase::count())->toBe(1);
});

it('keeps the employee fixed once the case exists, and the last day from being before the notice', function () {
    draftCase($this->hr, $this->sinta)->assertRedirect();

    $this->actingAs($this->hr)->put(route('offboarding.update', latestCase()), ['employee_id' => $this->lukas->id])->assertSessionHasErrors('employee_id');
    $this->actingAs($this->hr)->put(route('offboarding.update', latestCase()), ['notice_on' => '2026-11-10', 'last_working_on' => '2026-11-01'])->assertSessionHasErrors('last_working_on');
});

it('files the resignation letter privately and replaces it with a new one', function () {
    draftCase($this->hr, $this->sinta, ['file_resignation_letter' => [letter()]])->assertRedirect();
    $case = latestCase();
    $first = $case->files()->firstOrFail();

    Storage::disk('local')->assertExists($first->path);

    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['file_resignation_letter' => [letter()], 'file_handover' => [letter()]])->assertRedirect();

    expect($case->files()->where('kind', 'resignation_letter')->count())->toBe(1)
        ->and($case->files()->count())->toBe(2);
    Storage::disk('local')->assertMissing($first->path);

    $handover = $case->files()->where('kind', 'handover')->firstOrFail();
    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['remove_files' => [$handover->id]])->assertRedirect();

    expect($case->files()->count())->toBe(1);
});

it('warns when the notice given is shorter than the contract asks for', function () {
    $this->sinta->update(['notice_days' => 30]);
    draftCase($this->hr, $this->sinta, ['notice_on' => '2026-11-02', 'last_working_on' => '2026-11-16'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('offboarding.edit', latestCase()))
        ->assertInertia(fn (Assert $page) => $page->where('case.noticeCheck', ['given' => 14, 'required' => 30, 'short' => true]));

    $this->actingAs($this->hr)->put(route('offboarding.update', latestCase()), ['notice_on' => '2026-11-02', 'last_working_on' => '2026-12-12'])->assertRedirect();

    expect(latestCase()->noticeCheck()['short'])->toBeFalse();
});

// --- Sending for approval ---

it('will not send an incomplete case', function () {
    draftCase($this->hr, $this->sinta, ['exit_type' => null, 'notice_on' => null, 'last_working_on' => null, 'reason' => ''])->assertRedirect();

    $this->actingAs($this->hr)->post(route('offboarding.submit', latestCase()))
        ->assertSessionHasErrors(['exit_type', 'notice_on', 'last_working_on', 'reason']);

    expect(stageOf(latestCase()))->toBe('draft');
});

it('needs the resignation letter for a resignation, and a reason for compensation', function () {
    draftCase($this->hr, $this->sinta, ['compensation_months' => 3])->assertRedirect();

    $this->actingAs($this->hr)->post(route('offboarding.submit', latestCase()))
        ->assertSessionHasErrors(['file_resignation_letter' => 'Surat pengunduran diri wajib diunggah.', 'compensation_reason']);

    // A contract that simply ends needs no resignation letter.
    $this->actingAs($this->hr)->put(route('offboarding.update', latestCase()), ['exit_type' => 'contract_end', 'compensation_months' => 0])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.submit', latestCase()))->assertSessionHasNoErrors();

    expect(stageOf(latestCase()))->toBe('submitted');
});

it('sends the case to the nearest Management above the leaver, and tells them', function () {
    $case = submittedCase($this->sinta);
    $step = $case->workflow->steps()->firstOrFail();

    expect(stageOf($case))->toBe('submitted')
        ->and($step->approver_user_id)->toBe($this->mira->user->id)
        ->and($case->workflow->steps()->count())->toBe(1)
        ->and($this->mira->user->notifications()->count())->toBe(1)
        ->and($this->hr->notifications()->count())->toBe(0);
});

it('lets any Management account decide when none is above the leaver', function () {
    $other = offUser(Role::Management, $this->company);
    $case = submittedCase($this->lukas);
    $step = $case->workflow->steps()->firstOrFail();

    expect($step->approver_user_id)->toBeNull()->and($step->approver_role)->toBe('management');

    $this->actingAs($other)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();

    expect(stageOf($case))->toBe('checklist');
});

// --- Management's decision ---

it('opens the checklist stage when Management approves, and tells HR and the Atasan', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve', 'note' => 'Setuju'])->assertRedirect();

    expect(stageOf($case))->toBe('checklist')
        ->and($case->workflow->steps()->firstOrFail()->decided_by_user_id)->toBe($this->mira->user->id)
        ->and($this->hr->notifications()->count())->toBe(1)
        ->and($this->rudi->user->notifications()->count())->toBe(1);
});

it('lets nobody but the bound Management account decide, and not the leaver', function () {
    $case = submittedCase($this->sinta);
    $otherManagement = offUser(Role::Management, $this->company);

    foreach ([$this->hr, $this->rudi->user, $this->sinta->user, $otherManagement] as $user) {
        $this->actingAs($user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertForbidden();
    }

    expect(stageOf($case))->toBe('submitted');

    // A Management account that is itself leaving cannot approve its own case.
    $leavingManager = offMember('Direktur', null, Role::Management, $this->company);
    $own = submittedCase($leavingManager);
    $this->actingAs($leavingManager->user)->put(route('offboarding.decide', $own), ['decision' => 'approve'])->assertForbidden();
});

it('needs a note to reject, returns the case to draft, and keeps the reason through a second round', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'reject'])
        ->assertSessionHasErrors(['note' => 'Tulis catatan untuk pemohon.']);
    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'reject', 'note' => 'Kompensasi terlalu besar'])->assertRedirect();

    expect(stageOf($case))->toBe('draft')
        ->and($this->hr->notifications()->firstOrFail()->data['body'])->toContain('Kompensasi terlalu besar');

    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['compensation_months' => 1, 'compensation_reason' => 'Disesuaikan'])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.submit', $case))->assertRedirect();

    expect(stageOf($case))->toBe('submitted')
        ->and($case->workflow->comments()->pluck('body')->all())->toContain('Kompensasi terlalu besar');

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();

    expect(stageOf($case))->toBe('checklist');
});

it('cannot edit a case once it has been sent', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['reason' => 'Diubah'])->assertForbidden();
    $this->actingAs($this->hr)->get(route('offboarding.edit', $case))->assertRedirect(route('offboarding.show', $case));
    $this->actingAs($this->hr)->delete(route('offboarding.destroy', $case))->assertForbidden();
});

it('deletes a draft with its files', function () {
    draftCase($this->hr, $this->sinta, ['file_resignation_letter' => [letter()]])->assertRedirect();
    $case = latestCase();
    $path = $case->files()->firstOrFail()->path;

    $this->actingAs($this->hr)->delete(route('offboarding.destroy', $case))->assertRedirect(route('offboarding.index'));

    expect(OffboardingCase::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

// --- Who sees what ---

it('keeps a draft to HR, and shows a sent case to Management and to the supervisors above the leaver', function () {
    draftCase($this->hr, $this->sinta)->assertRedirect();
    $case = latestCase();

    $this->actingAs($this->hr)->get(route('offboarding.index', ['status' => 'all']))->assertInertia(fn (Assert $page) => $page->has('cases', 1));
    foreach ([$this->mira->user, $this->rudi->user, $this->sinta->user] as $user) {
        $this->actingAs($user)->get(route('offboarding.show', $case))->assertForbidden();
    }

    $this->actingAs($this->hr)->post(route('offboarding.submit', $case));
    // (an incomplete draft stays a draft: complete it first)
    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['file_resignation_letter' => [letter()]])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.submit', $case))->assertRedirect();

    $this->actingAs($this->mira->user)->get(route('offboarding.show', $case))->assertOk();
    $this->actingAs($this->rudi->user)->get(route('offboarding.show', $case))->assertOk();
    // Not Lukas, who is not above her; and not Sinta herself until the checklist stage.
    $this->actingAs($this->lukas->user)->get(route('offboarding.show', $case))->assertForbidden();
    $this->actingAs($this->sinta->user)->get(route('offboarding.show', $case))->assertForbidden();

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();

    $this->actingAs($this->sinta->user)->get(route('offboarding.show', $case))->assertOk();
});

it('lists for each person only the cases they may see', function () {
    submittedCase($this->sinta);
    submittedCase($this->lukas);

    $names = fn (User $user): array => collect(
        $this->actingAs($user)->get(route('offboarding.index', ['status' => 'all']))->viewData('page')['props']['cases'],
    )->pluck('employee.name')->sort()->values()->all();

    expect($names($this->hr))->toBe(['Lukas', 'Sinta'])
        ->and($names($this->mira->user))->toBe(['Lukas', 'Sinta'])
        ->and($names($this->rudi->user))->toBe(['Sinta']);
});

it('opens the offboarding list only to HR, Management and people with a team', function () {
    $this->actingAs($this->hr)->get(route('offboarding.index'))->assertOk();
    $this->actingAs($this->mira->user)->get(route('offboarding.index'))->assertOk();
    $this->actingAs($this->rudi->user)->get(route('offboarding.index'))->assertOk();
    $this->actingAs($this->sinta->user)->get(route('offboarding.index'))->assertForbidden();
    $this->actingAs($this->lukas->user)->get(route('offboarding.index'))->assertForbidden();

    submittedCase($this->sinta);

    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canViewOffboarding', true)->where('openOffboarding', 1));
});

it('summarises the open cases by stage, and filters them', function () {
    submittedCase($this->sinta);
    $approved = submittedCase($this->lukas);
    $this->actingAs(offUser(Role::Management, $this->company))->put(route('offboarding.decide', $approved), ['decision' => 'approve']);

    $this->actingAs($this->hr)->get(route('offboarding.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.open', 2)
            ->where('summary.perStage.submitted', 1)
            ->where('summary.perStage.checklist', 1)
            ->has('cases', 2));

    $this->actingAs($this->hr)->get(route('offboarding.index', ['status' => 'checklist']))
        ->assertInertia(fn (Assert $page) => $page->has('cases', 1)->where('cases.0.employee.name', 'Lukas'));
});

// --- Comments and files ---

it('lets HR and Management talk about a sent case, but not before it is sent', function () {
    draftCase($this->hr, $this->sinta)->assertRedirect();
    $case = latestCase();

    // A draft that was never sent has no thread.
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Halo'])->assertForbidden();

    $case = submittedCase($this->lukas);
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Mohon cek kompensasi'])->assertRedirect();

    $management = offUser(Role::Management, $this->company);
    $this->actingAs($management)->post(route('offboarding.comments.store', $case), ['body' => 'Baik'])->assertRedirect();
    // Not a supervisor who cannot see the case, and not the person leaving.
    $this->actingAs($this->sinta->user)->post(route('offboarding.comments.store', $case), ['body' => 'Saya ikut'])->assertForbidden();
    $this->actingAs($this->rudi->user)->post(route('offboarding.comments.store', $case), ['body' => 'Saya ikut'])->assertForbidden();

    expect($case->workflow->comments()->count())->toBe(2)
        ->and($this->mira->user->notifications()->count())->toBeGreaterThanOrEqual(1);
});

it('serves case files only to people who may see the case', function () {
    $case = submittedCase($this->sinta);
    $file = $case->files()->firstOrFail();

    $this->actingAs($this->hr)->get(route('offboarding.files.download', $file))->assertOk();
    $this->actingAs($this->mira->user)->get(route('offboarding.files.download', $file))->assertOk();
    $this->actingAs($this->lukas->user)->get(route('offboarding.files.download', $file))->assertForbidden();
});

it('lets HR add files to a running case, until it is closed', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->hr)->post(route('offboarding.files.store', $case), ['file_notice' => [letter()]])->assertRedirect();

    expect($case->files()->where('kind', 'notice')->count())->toBe(1);

    $this->actingAs($this->rudi->user)->post(route('offboarding.files.store', $case), ['file_notice' => [letter()]])->assertForbidden();
});

it('records the notice period on the employee form', function () {
    $this->actingAs($this->hr)->put(route('employees.update', $this->lukas), [
        'employee_number' => $this->lukas->employee_number, 'national_id' => $this->lukas->national_id,
        'full_name' => 'Lukas', 'employment_status' => 'active', 'notice_days' => 30,
    ])->assertRedirect();

    expect($this->lukas->refresh()->notice_days)->toBe(30);
});

it('saves and sends a new case from the wizard in one step', function () {
    draftCase($this->hr, $this->sinta, ['file_resignation_letter' => [letter()], 'send' => '1'])
        ->assertRedirect(route('offboarding.show', latestCase()));

    expect(stageOf(latestCase()))->toBe('submitted');
});

it('keeps what was typed when sending from the wizard falls short', function () {
    draftCase($this->hr, $this->sinta, ['reason' => 'Pindah kota', 'send' => '1'])
        ->assertRedirect(route('offboarding.edit', latestCase()))
        ->assertSessionHasErrors('file_resignation_letter');

    expect(OffboardingCase::count())->toBe(1)
        ->and(stageOf(latestCase()))->toBe('draft')
        ->and(latestCase()->reason)->toBe('Pindah kota');
});

// --- The discussion that runs through every stage ---

/**
 * @return array{items: list<array<string, mixed>>, unread: int, unreadByStage: array<string, int>}
 */
function discussionFor(User $user, OffboardingCase $case): array
{
    return test()->actingAs($user)->followingRedirects()->get(route('offboarding.show', $case))->viewData('page')['props']['discussion'];
}

it('lets the Atasan of the leaver join the discussion, with a badge for who they are to the case', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Mohon dicek'])->assertRedirect();
    $this->actingAs($this->rudi->user)->post(route('offboarding.comments.store', $case), ['body' => 'Siap, saya ambil alih'])->assertRedirect();
    $this->actingAs($this->mira->user)->post(route('offboarding.comments.store', $case), ['body' => 'Baik'])->assertRedirect();

    $items = collect(discussionFor($this->hr, $case)['items']);

    expect($items->pluck('badge')->all())->toBe(['PEMOHON', 'ATASAN', 'MANAJEMEN'])
        ->and($items->pluck('role')->all())->toBe(['Admin HR', 'Supervisor', 'Manajemen']);
});

it('tags each comment with the stage it was written in, and keeps it as the case moves on', function () {
    $case = submittedCase($this->sinta);
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Saat diajukan'])->assertRedirect();
    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Saat checklist'])->assertRedirect();

    $items = collect(discussionFor($this->hr, $case)['items'])->keyBy('body');

    expect($items['Saat diajukan']['stage'])->toBe('submitted')
        ->and($items['Saat diajukan']['stageLabel'])->toBe('Diajukan')
        ->and($items['Saat checklist']['stage'])->toBe('checklist')
        ->and($items['Saat checklist']['stageLabel'])->toBe('Checklist');
});

it('counts what is new to each person, by stage, and clears it when they open the discussion', function () {
    $case = submittedCase($this->sinta);
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Satu'])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Dua'])->assertRedirect();

    // The author has read their own; Management has not.
    expect(discussionFor($this->hr, $case)['unread'])->toBe(0);

    $mira = discussionFor($this->mira->user, $case);
    expect($mira['unread'])->toBe(2)
        ->and($mira['unreadByStage'])->toBe(['submitted' => 2])
        ->and(collect($mira['items'])->pluck('isNew')->all())->toBe([true, true]);

    $this->actingAs($this->mira->user)->post(route('offboarding.comments.read', $case))->assertRedirect();

    expect(discussionFor($this->mira->user, $case)['unread'])->toBe(0);

    // A comment written afterwards is new again, and only that one.
    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Tiga'])->assertRedirect();

    expect(discussionFor($this->mira->user, $case)['unread'])->toBe(1);
});

it('shows the note of a rejection in the thread at once, once, and keeps it after resending', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'reject', 'note' => 'Kompensasi terlalu besar'])->assertRedirect();

    $items = collect(discussionFor($this->hr, $case)['items']);
    expect($items->pluck('body')->all())->toBe(['Kompensasi terlalu besar'])
        ->and($items->first()['stage'])->toBe('submitted')
        ->and($items->first()['badge'])->toBe('MANAJEMEN')
        ->and(discussionFor($this->hr, $case)['unread'])->toBe(1);

    $this->actingAs($this->hr)->put(route('offboarding.update', $case), ['compensation_months' => 0])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.submit', $case))->assertRedirect();

    expect(collect(discussionFor($this->hr, $case)['items'])->pluck('body')->all())->toBe(['Kompensasi terlalu besar']);
});

it('puts an approval note in the thread too', function () {
    $case = submittedCase($this->sinta);

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve', 'note' => 'Silakan lanjut'])->assertRedirect();

    expect(collect(discussionFor($this->hr, $case)['items'])->pluck('body')->all())->toBe(['Silakan lanjut']);
});

it('tells everyone in the conversation about a new comment, but not the leaver or the author', function () {
    $case = submittedCase($this->sinta);
    $this->actingAs($this->rudi->user)->post(route('offboarding.comments.store', $case), ['body' => 'Dari Atasan'])->assertRedirect();

    $this->hr->notifications()->delete();
    $this->mira->user->notifications()->delete();
    $this->rudi->user->notifications()->delete();

    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();
    $this->hr->notifications()->delete();
    $this->rudi->user->notifications()->delete();

    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Checklist dimulai'])->assertRedirect();

    expect($this->rudi->user->notifications()->count())->toBe(1)
        ->and($this->hr->notifications()->count())->toBe(0)
        ->and($this->sinta->user->notifications()->count())->toBe(0);
});

it('lets HR read and answer a rejection while correcting the case, from the wizard page', function () {
    $case = submittedCase($this->sinta);
    $this->actingAs($this->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'reject', 'note' => 'Lengkapi alasan'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('offboarding.edit', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('discussion.unread', 1)
            ->where('discussion.items.0.body', 'Lengkapi alasan'));

    $this->actingAs($this->hr)->post(route('offboarding.comments.store', $case), ['body' => 'Sudah dilengkapi'])->assertRedirect();

    expect($case->workflow->comments()->count())->toBe(2);
});
