<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Recruitment, first slice: a supervisor or HR raises a requisition, Management then HR approve it, and HR moves
 * it on to posting (REC-DSH, REC-WIZ, REC-PIP, REC-APR).
 *
 * The tree: Mira (Management) manages Rudi (Supervisor, head of Gudang); Rudi manages Sinta. Lukas is an
 * employee with nobody under him. HR and HR-2 are HR Admins with no employee record.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->gudang = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Gudang']);
    $this->finance = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Finance']);

    $this->hr = reqUser(Role::HrAdmin, $this->company);
    $this->hr2 = reqUser(Role::HrAdmin, $this->company);

    $this->mira = reqMember('Mira', null, Role::Management, $this->company, $this->finance);
    $this->rudi = reqMember('Rudi', $this->mira, Role::Supervisor, $this->company, $this->gudang);
    $this->sinta = reqMember('Sinta', $this->rudi, Role::Employee, $this->company, $this->gudang);
    $this->lukas = reqMember('Lukas', null, Role::Employee, $this->company, $this->finance);

    $this->gudang->update(['supervisor_employee_id' => $this->rudi->id]);
});

function reqUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function reqMember(string $name, ?Employee $manager, Role $role, Company $company, Department $department): Employee
{
    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name,
        'manager_id' => $manager?->id,
        'department_id' => $department->id,
        'user_id' => reqUser($role, $company)->id,
    ]);
}

/**
 * Every field the template asks for, filled: the contract, the superior (Rudi), a start date next month, the job posting
 * and the candidate checklist.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function requisitionFields(Department $department, array $overrides = []): array
{
    return [
        'department_id' => $department->id,
        'position_name' => 'Staf Gudang',
        'quota' => 2,
        'employment_type' => 'contract',
        'contract_months' => 12,
        'manager_employee_id' => test()->rudi->id,
        'reason' => 'Volume kiriman naik',
        'target_start_on' => reqToday()->addMonth()->toDateString(),
        'about' => 'Kami mencari staf gudang yang teliti.',
        'responsibilities' => [
            ['title' => 'Barang keluar', 'items' => ['Menyiapkan dan memeriksa barang keluar.']],
        ],
        'benefits' => ['BPJS Kesehatan & BPJS Ketenagakerjaan.'],
        'checklist' => [
            'education' => ['Minimal SMA/SMK'],
            'experience' => [],
            'hard' => ['Menguasai Microsoft Excel'],
            'soft' => ['Teliti'],
            'bonus' => [],
        ],
        ...$overrides,
    ];
}

/** Today in the company's timezone, which is what the start date is judged against. */
function reqToday(): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Jakarta')->startOfDay();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function draftRequisition(User $as, Department $department, array $overrides = []): TestResponse
{
    return test()->actingAs($as)->post(route('recruitment.store'), requisitionFields($department, $overrides));
}

function latestRequisition(): Requisition
{
    return Requisition::query()->latest('id')->firstOrFail();
}

/**
 * A complete requisition, raised by this person and sent for approval.
 */
function submittedRequisition(?User $by = null, ?Department $department = null): Requisition
{
    $by ??= test()->rudi->user;

    draftRequisition($by, $department ?? test()->gudang, ['send' => 1])->assertRedirect();

    return latestRequisition()->refresh();
}

function stageOfRequisition(Requisition $requisition): string
{
    return (string) $requisition->refresh()->workflow->current_stage;
}

function openStepOf(Requisition $requisition): ?WorkflowStep
{
    return $requisition->refresh()->workflow->steps()->where('status', 'pending')->first();
}

// --- Raising a requisition ---

it('lets a supervisor save a draft for their own department and come back to it at the same step', function () {
    draftRequisition($this->rudi->user, $this->gudang, ['quota' => null, 'reason' => null, 'about' => null, 'responsibilities' => [], 'draft_step' => 2])
        ->assertRedirect(route('recruitment.index'));

    $requisition = latestRequisition();

    expect($requisition->reference())->toBe('REQ-'.str_pad((string) $requisition->id, 4, '0', STR_PAD_LEFT))
        ->and(stageOfRequisition($requisition))->toBe('draft')
        ->and($requisition->company_id)->toBe($this->company->id)
        ->and($requisition->created_by_user_id)->toBe($this->rudi->user_id);

    // A draft opens in the wizard, on the step it was left on (REC-WIZ-004, REC-DSH-005).
    $this->actingAs($this->rudi->user)->get(route('recruitment.show', $requisition))->assertRedirect(route('recruitment.edit', $requisition));
    $this->actingAs($this->rudi->user)->get(route('recruitment.edit', $requisition))
        ->assertInertia(fn (Assert $page) => $page->component('recruitment/form')->where('requisition.draftStep', 2));
});

it('needs a position name and a whole quota of at least one even for a draft', function () {
    draftRequisition($this->rudi->user, $this->gudang, ['position_name' => ''])->assertSessionHasErrors('position_name');
    draftRequisition($this->rudi->user, $this->gudang, ['quota' => 0])->assertSessionHasErrors('quota');
    draftRequisition($this->rudi->user, $this->gudang, ['quota' => 1.5])->assertSessionHasErrors('quota');

    expect(Requisition::count())->toBe(0);
});

it('drops blank lines and empty subtitles', function () {
    draftRequisition($this->rudi->user, $this->gudang, [
        'benefits' => ['  ', 'THR sesuai ketentuan.', ''],
        'responsibilities' => [
            ['title' => 'Barang keluar', 'items' => ['', ' Periksa barang. ']],
            ['title' => '  ', 'items' => ['   ']],
        ],
        'checklist' => ['education' => [' ', 'Minimal SMA/SMK'], 'experience' => [], 'hard' => [], 'soft' => [], 'bonus' => ['']],
    ])->assertRedirect();

    $requisition = latestRequisition();

    expect($requisition->benefits)->toBe(['THR sesuai ketentuan.'])
        ->and($requisition->responsibilities)->toBe([['title' => 'Barang keluar', 'items' => ['Periksa barang.']]])
        ->and($requisition->checklist['education'])->toBe(['Minimal SMA/SMK'])
        ->and($requisition->checklist['bonus'])->toBe([]);
});

it('lets a supervisor ask only for their own departments, HR for any, and nobody else', function () {
    draftRequisition($this->rudi->user, $this->finance)->assertSessionHasErrors('department_id');
    draftRequisition($this->hr, $this->finance)->assertRedirect();

    foreach ([$this->lukas->user, $this->sinta->user] as $user) {
        $this->actingAs($user)->get(route('recruitment.create'))->assertForbidden();
        draftRequisition($user, $this->gudang)->assertForbidden();
    }

    $elsewhere = Department::factory()->create(['company_id' => Company::factory()->create()->id]);
    draftRequisition($this->hr, $elsewhere)->assertSessionHasErrors('department_id');

    expect(Requisition::count())->toBe(1);
});

it('keeps a draft to its requester until it is sent', function () {
    draftRequisition($this->rudi->user, $this->gudang)->assertRedirect();
    $requisition = latestRequisition();

    foreach ([$this->mira->user, $this->hr] as $user) {
        $this->actingAs($user)->get(route('recruitment.show', $requisition))->assertForbidden();
        $this->actingAs($user)->get(route('recruitment.index', ['status' => 'all']))->assertInertia(fn (Assert $page) => $page->has('requisitions', 0));
    }

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $requisition), requisitionFields($this->gudang, ['send' => 1]))
        ->assertRedirect(route('recruitment.show', $requisition));

    foreach ([$this->mira->user, $this->hr] as $user) {
        $this->actingAs($user)->get(route('recruitment.show', $requisition))->assertOk();
    }

    // Nobody outside the line and outside HR sees it.
    $this->actingAs($this->lukas->user)->get(route('recruitment.show', $requisition))->assertForbidden();
});

it('lets the requester throw away a draft that was never sent', function () {
    draftRequisition($this->rudi->user, $this->gudang)->assertRedirect();
    $requisition = latestRequisition();

    $this->actingAs($this->hr)->delete(route('recruitment.destroy', $requisition))->assertForbidden();
    $this->actingAs($this->rudi->user)->delete(route('recruitment.destroy', $requisition))->assertRedirect(route('recruitment.index'));

    expect(Requisition::count())->toBe(0);
});

// --- Sending it ---

it('will not send an incomplete requisition, and keeps it as a draft with what is missing', function () {
    draftRequisition($this->rudi->user, $this->gudang, ['quota' => null, 'reason' => '', 'about' => '', 'send' => 1])
        ->assertRedirect(route('recruitment.edit', latestRequisition()))
        ->assertSessionHasErrors(['quota', 'reason', 'about']);

    expect(stageOfRequisition(latestRequisition()))->toBe('draft');
});

it('sends a requisition to the Management above the requester, then to HR', function () {
    $requisition = submittedRequisition();

    $chain = $requisition->workflow->steps()->orderBy('position')->get();

    expect(stageOfRequisition($requisition))->toBe('submitted')
        ->and($requisition->submitted_at)->not->toBeNull()
        ->and($chain->pluck('label')->all())->toBe(['Manajemen', 'HR'])
        ->and($chain[0]->approver_user_id)->toBe($this->mira->user_id)
        ->and($chain[0]->status->value)->toBe('pending')
        ->and($chain[1]->approver_role)->toBe(Role::HrAdmin->value)
        ->and($chain[1]->status->value)->toBe('locked')
        ->and($this->mira->user->notifications()->count())->toBe(1)
        ->and($this->hr->notifications()->count())->toBe(0);
});

it('falls back to any Management account when nobody above the requester holds the role', function () {
    $requisition = submittedRequisition($this->hr, $this->finance);
    $step = $requisition->workflow->steps()->orderBy('position')->first();

    expect($step->approver_user_id)->toBeNull()
        ->and($step->approver_role)->toBe(Role::Management->value)
        ->and($this->mira->user->notifications()->count())->toBe(1);
});

// --- Stage 01: approval ---

it('needs Management to set the salary expectation before approving', function () {
    $requisition = submittedRequisition();

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), ['decision' => 'approve'])
        ->assertSessionHasErrors('salary_min');

    expect(openStepOf($requisition)->label)->toBe('Manajemen');

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), [
        'decision' => 'approve', 'salary_min' => 4500000, 'salary_max' => 4000000,
    ])->assertSessionHasErrors('salary_max');

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), [
        'decision' => 'approve', 'salary_min' => 4000000, 'salary_max' => 4500000,
    ])->assertRedirect();

    $requisition->refresh();

    expect($requisition->salary_min)->toBe(4000000)
        ->and($requisition->salary_max)->toBe(4500000)
        ->and($requisition->salary_set_by_user_id)->toBe($this->mira->user_id)
        ->and(openStepOf($requisition)->label)->toBe('HR')
        ->and($this->hr->notifications()->count())->toBe(1)
        ->and($this->hr2->notifications()->count())->toBe(1);
});

it('lets Management save the salary first and approve later', function () {
    $requisition = submittedRequisition();

    $this->actingAs($this->rudi->user)->put(route('recruitment.salary', $requisition), ['salary_min' => 1, 'salary_max' => 2])->assertForbidden();
    $this->actingAs($this->mira->user)->put(route('recruitment.salary', $requisition), ['salary_min' => 4000000, 'salary_max' => 5000000])->assertRedirect();

    expect(openStepOf($requisition)->label)->toBe('Manajemen');

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), ['decision' => 'approve'])->assertRedirect();

    expect(openStepOf($requisition)->label)->toBe('HR');
});

it('keeps HR waiting until Management has decided, and never lets the requester decide', function () {
    $requisition = submittedRequisition();

    $this->actingAs($this->hr)->put(route('recruitment.decide', $requisition), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($this->rudi->user)->put(route('recruitment.decide', $requisition), ['decision' => 'approve'])->assertForbidden();

    // HR raising one cannot approve it as HR either; another HR does.
    $own = submittedRequisition($this->hr, $this->finance);
    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $own), ['decision' => 'approve', 'salary_min' => 1, 'salary_max' => 2])->assertRedirect();

    $this->actingAs($this->hr)->put(route('recruitment.decide', $own), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($this->hr2)->put(route('recruitment.decide', $own), ['decision' => 'approve'])->assertRedirect();

    expect(openStepOf($own))->toBeNull();
});

it('returns a rejected requisition to its requester, who corrects and sends it again', function () {
    $requisition = submittedRequisition();

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), ['decision' => 'reject'])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), ['decision' => 'reject', 'note' => 'Kuota terlalu besar'])
        ->assertRedirect();

    expect(stageOfRequisition($requisition))->toBe('draft')
        ->and($this->rudi->user->notifications()->latest()->firstOrFail()->data['body'])->toContain('Kuota terlalu besar');

    // Back in the wizard, with the reason in the thread.
    $this->actingAs($this->rudi->user)->get(route('recruitment.edit', $requisition))
        ->assertInertia(fn (Assert $page) => $page->component('recruitment/form')->where('requisition.statusLabel', 'Dikembalikan ke pemohon'));

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $requisition), requisitionFields($this->gudang, ['quota' => 1, 'send' => 1]))
        ->assertRedirect(route('recruitment.show', $requisition));

    $steps = $requisition->refresh()->workflow->steps()->orderBy('position')->get();

    expect(stageOfRequisition($requisition))->toBe('submitted')
        ->and($steps->pluck('status')->map->value->all())->toBe(['pending', 'locked'])
        ->and($requisition->workflow->comments()->pluck('body')->all())->toContain('Kuota terlalu besar');
});

it('lets the requester correct a sent requisition until someone decides on it', function () {
    $requisition = submittedRequisition();
    $fields = requisitionFields($this->gudang, ['position_name' => 'Staf Gudang Senior']);

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $requisition), $fields)->assertRedirect(route('recruitment.show', $requisition));

    expect($requisition->refresh()->position_name)->toBe('Staf Gudang Senior')
        ->and(stageOfRequisition($requisition))->toBe('submitted');

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), ['decision' => 'approve', 'salary_min' => 1, 'salary_max' => 2])->assertRedirect();

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $requisition), [...$fields, 'position_name' => 'Lain'])->assertForbidden();
    $this->actingAs($this->rudi->user)->get(route('recruitment.edit', $requisition))->assertRedirect(route('recruitment.show', $requisition));
});

it('shows the salary to Management and HR, not to the requester', function () {
    $requisition = submittedRequisition();

    $this->actingAs($this->rudi->user)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page->component('recruitment/show')->where('requisition.salary', null));

    foreach ([$this->mira->user, $this->hr] as $user) {
        $this->actingAs($user)->get(route('recruitment.show', $requisition))
            ->assertInertia(fn (Assert $page) => $page->where('requisition.salary.set', false));
    }
});

// --- Moving on to posting ---

it('keeps "Lanjut ke Posting" shut until every approval is in, and lets only HR use it', function () {
    $requisition = submittedRequisition();

    $this->actingAs($this->hr)->post(route('recruitment.advance', $requisition))->assertSessionHasErrors('advance');

    $this->actingAs($this->mira->user)->put(route('recruitment.decide', $requisition), ['decision' => 'approve', 'salary_min' => 1, 'salary_max' => 2]);
    $this->actingAs($this->hr)->put(route('recruitment.decide', $requisition), ['decision' => 'approve'])->assertRedirect();

    expect($this->rudi->user->notifications()->latest()->firstOrFail()->data['title'])->toContain('disetujui');

    $this->actingAs($this->rudi->user)->post(route('recruitment.advance', $requisition))->assertForbidden();
    $this->actingAs($this->mira->user)->post(route('recruitment.advance', $requisition))->assertForbidden();
    $this->actingAs($this->hr)->post(route('recruitment.advance', $requisition))->assertRedirect();

    expect(stageOfRequisition($requisition))->toBe('posting');

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.tabs.0.state', 'done')
            ->where('requisition.tabs.1.state', 'current')
            ->where('requisition.tabs.2.state', 'locked'));
});

// --- The dashboard ---

it('lists what each person may see, with the summary and the menu entry', function () {
    $sent = submittedRequisition();
    draftRequisition($this->rudi->user, $this->gudang)->assertRedirect();
    submittedRequisition($this->hr, $this->finance);

    $this->actingAs($this->rudi->user)->get(route('recruitment.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recruitment/index')
            ->has('requisitions', 1)
            ->where('requisitions.0.id', $sent->id)
            ->where('summary.drafts', 1)
            ->where('canCreate', true)
            ->where('auth.canViewRecruitment', true));

    $this->actingAs($this->rudi->user)->get(route('recruitment.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('requisitions', 2));

    $this->actingAs($this->mira->user)->get(route('recruitment.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requisitions', 2)->where('summary.awaitingApproval', 2));

    $this->actingAs($this->lukas->user)->get(route('recruitment.index'))->assertForbidden();
    $this->actingAs($this->lukas->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canViewRecruitment', false));
});
