<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\EmploymentStatus;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Naming a different direct superior (REC-PIP): HR does it once the requisition is under way, the new superior must have
 * an active account, both are told, and the new one takes over the choosing.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta; the interview is on the 5th at 10:00.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = superiorUser(Role::HrAdmin, $this->company);
    $this->requester = superiorUser(Role::Supervisor, $this->company);
    $this->oldBoss = superiorUser(Role::Supervisor, $this->company);
    $this->oldBossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->oldBoss->id]);
    $this->newBoss = superiorUser(Role::Supervisor, $this->company);
    $this->newBossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->newBoss->id]);
});

function superiorUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function superiorRequisition(RequisitionStage $stage = RequisitionStage::Interview): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'manager_employee_id' => test()->oldBossEmployee->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

function changeSuperior(Requisition $requisition, ?Employee $to, ?User $as = null, array $extra = [])
{
    return test()->actingAs($as ?? test()->hr)->put(route('recruitment.superior', $requisition), ['manager_employee_id' => $to?->id, ...$extra]);
}

it('lets HR name a new direct superior, tells both, and keeps it on the audit trail', function () {
    $requisition = superiorRequisition();

    changeSuperior($requisition, $this->newBossEmployee, extra: ['note' => 'Atasan lama pindah divisi.'])
        ->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Atasan langsung diganti ke {$this->newBossEmployee->full_name}."]);

    $entry = Activity::query()->where('description', 'Atasan langsung diganti')->sole();

    expect($requisition->refresh()->manager_employee_id)->toBe($this->newBossEmployee->id)
        ->and($entry->causer_id)->toBe($this->hr->id)
        ->and($entry->properties['from'])->toBe($this->oldBossEmployee->full_name)
        ->and($entry->properties['to'])->toBe($this->newBossEmployee->full_name)
        ->and($entry->properties['note'])->toBe('Atasan lama pindah divisi.')
        ->and($this->newBoss->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Anda atasan langsung')
        ->and($this->oldBoss->notifications()->get()->pluck('data.title')->implode(' '))->toContain('bukan lagi atasan langsung')
        ->and($this->hr->notifications()->count())->toBe(0);
});

it('hands the choosing to the new superior', function () {
    $requisition = superiorRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi']);
    app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], $this->hr);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 14:00', 'Asia/Jakarta'));
    $pick = route('recruitment.candidates.select', [$requisition, $candidate]);

    $this->actingAs($this->newBoss)->post($pick)->assertForbidden();

    changeSuperior($requisition, $this->newBossEmployee)->assertRedirect();

    $this->actingAs($this->oldBoss)->post($pick)->assertForbidden();
    $this->actingAs($this->newBoss)->post($pick)->assertRedirect();

    expect($candidate->refresh()->isSelected())->toBeTrue();
});

it('lets only HR do it, from Posting until the requisition is filled or called off', function () {
    foreach ([RequisitionStage::Posting, RequisitionStage::Screening, RequisitionStage::Interview, RequisitionStage::Offer] as $stage) {
        changeSuperior(superiorRequisition($stage), $this->newBossEmployee)->assertRedirect();
    }

    foreach ([RequisitionStage::Draft, RequisitionStage::Submitted, RequisitionStage::Filled, RequisitionStage::Cancelled] as $stage) {
        changeSuperior(superiorRequisition($stage), $this->newBossEmployee)->assertForbidden();
    }

    $requisition = superiorRequisition();
    changeSuperior($requisition, $this->newBossEmployee, $this->requester)->assertForbidden();
    changeSuperior($requisition, $this->newBossEmployee, $this->oldBoss)->assertForbidden();

    expect($requisition->refresh()->manager_employee_id)->toBe($this->oldBossEmployee->id);
});

it('names only an active employee of the company with an active account, other than the current superior', function () {
    $requisition = superiorRequisition();

    $noAccount = Employee::factory()->forCompany($this->company)->create(['user_id' => null]);
    $dormant = Employee::factory()->forCompany($this->company)->create(['user_id' => User::factory()->create(['company_id' => $this->company->id, 'is_active' => false])->id]);
    $left = Employee::factory()->forCompany($this->company)->create([
        'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        'employment_status' => EmploymentStatus::Inactive,
    ]);
    $elsewhere = Employee::factory()->forCompany(Company::factory()->create())->create(['user_id' => User::factory()->create()->id]);

    foreach ([$noAccount, $dormant, $left, $elsewhere, $this->oldBossEmployee] as $refused) {
        changeSuperior($requisition, $refused)->assertSessionHasErrors('manager_employee_id');
    }

    changeSuperior($requisition, null)->assertSessionHasErrors('manager_employee_id');

    expect($requisition->refresh()->manager_employee_id)->toBe($this->oldBossEmployee->id)
        ->and(Activity::query()->where('description', 'Atasan langsung diganti')->count())->toBe(0);
});

it('offers HR the choices, and nobody else', function () {
    $requisition = superiorRequisition();
    $noAccount = Employee::factory()->forCompany($this->company)->create(['user_id' => null]);

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.canChangeSuperior', true)
            ->where('requisition.superiorChoices', function ($choices) use ($noAccount): bool {
                $ids = collect($choices)->pluck('id');

                return $ids->contains($this->newBossEmployee->id)
                    && ! $ids->contains($this->oldBossEmployee->id)
                    && ! $ids->contains($noAccount->id);
            }));

    $this->actingAs($this->requester)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.canChangeSuperior', false)
            ->where('requisition.superiorChoices', []));
});
