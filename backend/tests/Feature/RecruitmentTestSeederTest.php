<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Enums\WorkflowStepStatus;
use App\Models\Company;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\WorkflowEngine;
use Database\Seeders\RecruitmentTestSeeder;
use Database\Seeders\RoleSeeder;

/**
 * The requisitions the dev database is given for trying recruitment by hand: one at each point of the approval flow.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Company::factory()->create(['code' => 'CMP-A']);
});

function seededRequisition(string $position): Requisition
{
    return Requisition::acrossCompanies()->where('position_name', $position)->with('workflow.steps')->firstOrFail();
}

/**
 * @return array<string, string>
 */
function stepStatuses(Requisition $requisition): array
{
    return $requisition->workflow->steps->mapWithKeys(fn ($step): array => [$step->label => $step->status->value])->all();
}

it('puts a requisition at each point of the approval flow', function () {
    $this->seed(RecruitmentTestSeeder::class);

    expect(Requisition::acrossCompanies()->count())->toBe(8)
        ->and(seededRequisition('Desainer Grafis')->stage())->toBe(RequisitionStage::Draft)
        ->and(seededRequisition('UI/UX Designer')->stage())->toBe(RequisitionStage::Submitted)
        ->and(stepStatuses(seededRequisition('UI/UX Designer')))->toBe(['Manajemen' => 'pending', 'HR' => 'locked'])
        ->and(stepStatuses(seededRequisition('Motion Designer')))->toBe(['Manajemen' => 'approved', 'HR' => 'pending'])
        ->and(seededRequisition('Staf Produksi Kreatif')->stage())->toBe(RequisitionStage::Submitted)
        ->and(stepStatuses(seededRequisition('Staf Produksi Kreatif')))->toBe(['Manajemen' => 'approved', 'HR' => 'approved'])
        ->and(seededRequisition('Art Director')->stage())->toBe(RequisitionStage::Posting);
});

it('sends a rejected requisition back to its requester as a draft, with the reason on record', function () {
    $this->seed(RecruitmentTestSeeder::class);

    $rejected = seededRequisition('Ilustrator Freelance');

    expect($rejected->stage())->toBe(RequisitionStage::Draft)
        ->and(app(WorkflowEngine::class)->wasRejected($rejected->workflow))->toBeTrue()
        ->and($rejected->workflow->comments()->pluck('body')->all())->toContain('Anggaran kuartal ini belum tersedia. Ajukan lagi bulan depan.');
});

it('has Management set the salary expectation on the ones it approved', function () {
    $this->seed(RecruitmentTestSeeder::class);

    $approved = seededRequisition('Motion Designer');

    expect([$approved->salary_min, $approved->salary_max])->toBe([7_000_000, 10_000_000])
        ->and($approved->salary_set_by_user_id)->toBe(User::where('email', 'manager@hr.test')->value('id'))
        ->and(seededRequisition('UI/UX Designer')->hasSalaryExpectation())->toBeFalse();
});

it('raises them as the supervisor for Design and as HR for any department', function () {
    $this->seed(RecruitmentTestSeeder::class);

    $supervisor = User::where('email', 'supervisor@hr.test')->value('id');
    $hr = User::where('email', 'hr@hr.test')->first();

    expect($hr->hasRole(Role::HrAdmin->value))->toBeTrue()
        ->and(seededRequisition('UI/UX Designer')->created_by_user_id)->toBe($supervisor)
        ->and(seededRequisition('Marketing Executive')->created_by_user_id)->toBe($hr->id)
        ->and(seededRequisition('Marketing Executive')->department->name)->toBe('Marketing')
        ->and(seededRequisition('Sales Executive')->created_by_user_id)->toBe($hr->id)
        ->and(stepStatuses(seededRequisition('Sales Executive')))->toBe(['Manajemen' => 'approved', 'HR' => 'pending']);
});

it('binds the Management step to the manager above the supervisor', function () {
    $this->seed(RecruitmentTestSeeder::class);

    $step = seededRequisition('UI/UX Designer')->workflow->steps->firstWhere('status', WorkflowStepStatus::Pending);

    expect($step->approver_user_id)->toBe(User::where('email', 'manager@hr.test')->value('id'));
});

it('can be run again without duplicating anything', function () {
    $this->seed(RecruitmentTestSeeder::class);
    $this->seed(RecruitmentTestSeeder::class);

    expect(Requisition::acrossCompanies()->count())->toBe(8)
        ->and(User::where('email', 'hr@hr.test')->count())->toBe(1);
});

it('runs on a production server, using the company that is already there', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => RecruitmentTestSeeder::class, '--force' => true])->assertSuccessful();

    expect(Requisition::acrossCompanies()->count())->toBe(8)
        ->and(Company::count())->toBe(1);
});

it('creates nothing on a production server that has no company yet', function () {
    Company::query()->delete();
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => RecruitmentTestSeeder::class, '--force' => true])->assertSuccessful();

    expect(Requisition::acrossCompanies()->count())->toBe(0);
});
