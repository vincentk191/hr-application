<?php

use App\Enums\OffboardingCaseStage as Stage;
use App\Enums\Role;
use App\Enums\WorkflowStepStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Support\CompanyContext;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\Definitions\OffboardingCaseWorkflow;
use App\Workflows\Exceptions\InvalidTransition;
use App\Workflows\Exceptions\StepNotActionable;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\Guards\ChecklistApproved;
use App\Workflows\Guards\ChecklistComplete;
use App\Workflows\Guards\ExitInterviewRecorded;
use App\Workflows\Guards\LastDayPassed;
use App\Workflows\Guards\ReportsReassigned;
use App\Workflows\StepBlueprint;
use App\Workflows\WorkflowEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\WorkflowSubject;

/**
 * The shared approval engine (CORE-WFL).
 *
 * Tested against a fixture subject rather than a real module, so these cover the engine's own
 * rules — what may move, who may decide, what is recorded — independently of whichever module
 * uses it first.
 */
beforeEach(function () {
    Schema::create('workflow_test_subjects', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained();
        $table->string('title')->nullable();
        $table->timestamps();
    });

    $this->seed(RoleSeeder::class);

    $this->engine = app(WorkflowEngine::class);
    $this->definition = app(OffboardingCaseWorkflow::class);
    $this->company = Company::factory()->create();
    $this->subject = WorkflowSubject::create(['company_id' => $this->company->id]);
});

function supervisor(Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole(Role::Supervisor->value);

    return $user;
}

function hrAdmin(Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole(Role::HrAdmin->value);

    return $user;
}

it('starts an instance in the definition initial stage', function () {
    $instance = $this->engine->start($this->subject, $this->definition);

    expect($instance->current_stage)->toBe(Stage::Draft->value)
        ->and($instance->stage())->toBe(Stage::Draft)
        ->and($instance->definition()->key())->toBe('offboarding_case')
        ->and($instance->isComplete())->toBeFalse();
});

it('tags the instance with the subject company', function () {
    $instance = $this->engine->start($this->subject, $this->definition);

    // An instance must never be scoped differently from the thing it tracks.
    expect($instance->company_id)->toBe($this->company->id);
});

it('links the instance back to its subject', function () {
    $instance = $this->engine->start($this->subject, $this->definition);

    expect($instance->subject->is($this->subject))->toBeTrue()
        ->and($this->subject->fresh()->workflow->is($instance))->toBeTrue();
});

it('records the opening transition', function () {
    $instance = $this->engine->start($this->subject, $this->definition);

    $transitions = $instance->transitions;

    expect($transitions)->toHaveCount(1)
        ->and($transitions->first()->from_stage)->toBeNull()
        ->and($transitions->first()->to_stage)->toBe(Stage::Draft->value);
});

it('opens only the first step of a chain', function () {
    $sv = supervisor($this->company);
    $hr = hrAdmin($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    $steps = $instance->steps;

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->status)->toBe(WorkflowStepStatus::Pending)
        // Sequential by construction, not by convention (OFF-SIG-005).
        ->and($steps[1]->status)->toBe(WorkflowStepStatus::Locked)
        ->and($this->engine->waitingOn($instance)->label)->toBe('Atasan langsung');
});

it('refuses a decision on a step that is still locked', function () {
    $sv = supervisor($this->company);
    $hr = hrAdmin($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    $this->engine->approve($instance->steps[1], $hr);
})->throws(StepNotActionable::class);

it('opens the next step once the current one is approved', function () {
    $sv = supervisor($this->company);
    $hr = hrAdmin($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    $this->engine->approve($instance->steps[0], $sv, 'Sudah benar');
    $instance->refresh();

    expect($instance->steps[0]->status)->toBe(WorkflowStepStatus::Approved)
        ->and($instance->steps[0]->note)->toBe('Sudah benar')
        ->and($instance->steps[0]->decided_by_user_id)->toBe($sv->id)
        ->and($instance->steps[1]->status)->toBe(WorkflowStepStatus::Pending);
});

it('binds a resolved step to that one person', function () {
    $sv = supervisor($this->company);
    $anotherSupervisor = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);

    // Holding the same role is not enough once a step names a person (CORE-RBA-005).
    expect($instance->steps[0]->canBeDecidedBy($anotherSupervisor))->toBeFalse()
        ->and($instance->steps[0]->canBeDecidedBy($sv))->toBeTrue();
});

it('lets anyone holding the role decide an unresolved step', function () {
    $firstHr = hrAdmin($this->company);
    $secondHr = hrAdmin($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    expect($instance->steps[0]->canBeDecidedBy($firstHr))->toBeTrue()
        ->and($instance->steps[0]->canBeDecidedBy($secondHr))->toBeTrue()
        ->and($instance->steps[0]->canBeDecidedBy(supervisor($this->company)))->toBeFalse();
});

it('refuses a decision from someone who is not the approver', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);

    $this->engine->approve($instance->steps[0], hrAdmin($this->company));
})->throws(StepNotActionable::class);

it('refuses to decide the same step twice', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);

    $this->engine->approve($instance->steps[0], $sv);
    $this->engine->approve($instance->steps[0]->refresh(), $sv);
})->throws(StepNotActionable::class);

it('stops the chain on a rejection rather than opening the next step', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    $this->engine->reject($instance->steps[0], $sv, 'Masa pemberitahuan kurang');
    $instance->refresh();

    expect($instance->steps[0]->status)->toBe(WorkflowStepStatus::Rejected)
        ->and($instance->steps[0]->note)->toBe('Masa pemberitahuan kurang')
        // The next approver must not be able to act on a decision already gone against it.
        ->and($instance->steps[1]->status)->toBe(WorkflowStepStatus::Locked)
        ->and($this->engine->waitingOn($instance))->toBeNull()
        ->and($this->engine->wasRejected($instance))->toBeTrue();
});

it('requires a reason on a rejection', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);

    $this->engine->reject($instance->steps[0], $sv, '   ');
})->throws(InvalidArgumentException::class);

it('refuses a transition the definition does not allow', function () {
    $instance = $this->engine->start($this->subject, $this->definition);

    // Draft may only go to Submitted.
    $this->engine->transitionTo($instance, Stage::SignOff);
})->throws(InvalidTransition::class);

it('blocks a guarded transition and says why', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);
    $instance = $this->engine->transitionTo($instance, Stage::Submitted);

    $reasons = $this->engine->blockingReasons($instance, Stage::Checklist);

    expect($reasons)->toHaveCount(1)
        ->and($reasons[0])->toContain('Atasan langsung');

    // The UI asks blockingReasons() to disable the action; attempting it anyway throws.
    expect(fn () => $this->engine->transitionTo($instance, Stage::Checklist))
        ->toThrow(TransitionBlocked::class);
});

it('allows a guarded transition once the chain is approved', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);
    $instance = $this->engine->transitionTo($instance, Stage::Submitted);
    $this->engine->approve($instance->steps()->first(), $sv);

    $instance = $this->engine->transitionTo($instance->refresh(), Stage::Checklist);

    // Sign-off is a stage further on, and it waits for the checklist as well as the approval.
    expect($instance->current_stage)->toBe(Stage::Checklist->value)
        ->and($this->engine->blockingReasons($instance, Stage::SignOff))->toHaveCount(2)
        ->and($this->engine->blockingReasons($instance, Stage::SignOff)[0])->toContain('sign-off')
        ->and($this->engine->blockingReasons($instance, Stage::SignOff)[1])->toContain('menyetujui checklist');
});

it('records each transition with its actor', function () {
    $hr = hrAdmin($this->company);
    $instance = $this->engine->start($this->subject, $this->definition, actor: $hr);

    $this->engine->transitionTo($instance, Stage::Submitted, $hr);

    $transitions = $instance->refresh()->transitions;

    expect($transitions)->toHaveCount(2)
        ->and($transitions[1]->from_stage)->toBe(Stage::Draft->value)
        ->and($transitions[1]->to_stage)->toBe(Stage::Submitted->value)
        ->and($transitions[1]->actor_user_id)->toBe($hr->id);
});

it('marks the instance complete on reaching a final stage', function () {
    $sv = supervisor($this->company);

    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);
    $instance = $this->engine->transitionTo($instance, Stage::Submitted);
    $this->engine->approve($instance->steps()->first(), $sv);

    $instance = $this->engine->transitionTo($instance->refresh(), Stage::Checklist);

    // The stand-in subject has no checklist, exit details or leaver; what is under test here is the engine, so
    // let the offboarding guards pass.
    foreach ([ChecklistComplete::class, ChecklistApproved::class, ExitInterviewRecorded::class, LastDayPassed::class, ReportsReassigned::class] as $guard) {
        app()->bind($guard, fn () => new class implements TransitionGuard
        {
            public function passes(WorkflowInstance $instance): bool
            {
                return true;
            }

            public function message(WorkflowInstance $instance): string
            {
                return '';
            }
        });
    }

    $instance = $this->engine->transitionTo($instance, Stage::SignOff);

    expect($instance->isComplete())->toBeFalse();

    $instance = $this->engine->transitionTo($instance, Stage::Closed);

    expect($instance->isComplete())->toBeTrue()
        ->and($instance->completed_at)->not->toBeNull()
        ->and($instance->stage()->isFinal())->toBeTrue();
});

it('treats an unconfigured chain as blocking, not as vacuously approved', function () {
    // A case whose approvers were never set up must not slip past a gated stage. "No
    // approvals required" and "approvals not configured yet" look the same to a naive guard,
    // and only one of them is safe to let through.
    $instance = $this->engine->start($this->subject, $this->definition);
    $instance = $this->engine->transitionTo($instance, Stage::Submitted);

    expect($this->engine->chainIsApproved($instance))->toBeFalse()
        ->and($this->engine->blockingReasons($instance, Stage::Checklist))
        ->toBe(['Alur persetujuan belum disiapkan.']);
});

it('reports how long each stage took', function () {
    $instance = $this->engine->start($this->subject, $this->definition);

    $this->travel(2)->hours();
    $instance = $this->engine->transitionTo($instance, Stage::Submitted);

    $this->travel(1)->hours();

    $durations = $this->engine->stageDurations($instance);

    expect($durations)->toHaveCount(2)
        ->and($durations[0]['stage'])->toBe(Stage::Draft->value)
        ->and($durations[0]['seconds'])->toBe(7200)
        ->and($durations[1]['stage'])->toBe(Stage::Submitted->value)
        ->and($durations[1]['seconds'])->toBe(3600);
});

it('tags a comment with the stage it was written in', function () {
    $hr = hrAdmin($this->company);
    $instance = $this->engine->start($this->subject, $this->definition);

    $this->engine->comment($instance, $hr, 'Tolong lengkapi berkasnya');
    $instance = $this->engine->transitionTo($instance, Stage::Submitted);
    $this->engine->comment($instance, $hr, 'Sudah saya ajukan');

    $comments = $instance->refresh()->comments;

    expect($comments)->toHaveCount(2)
        ->and($comments[0]->stage)->toBe(Stage::Draft->value)
        ->and($comments[1]->stage)->toBe(Stage::Submitted->value)
        ->and($comments[0]->author->is($hr))->toBeTrue();
});

it('rebuilds a chain when a later stage decides its own approvers', function () {
    $sv = supervisor($this->company);
    $instance = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
    ]);

    // Offboarding's checklist owners are only known once HR assembles the checklist.
    $owners = [hrAdmin($this->company), hrAdmin($this->company)];
    $instance = $this->engine->setChain($instance, [
        StepBlueprint::forUser('Pemilik item 1', $owners[0]),
        StepBlueprint::forUser('Pemilik item 2', $owners[1]),
    ]);

    expect($instance->steps)->toHaveCount(2)
        ->and($instance->steps[0]->label)->toBe('Pemilik item 1')
        ->and($instance->steps[0]->status)->toBe(WorkflowStepStatus::Pending)
        ->and($instance->steps[1]->status)->toBe(WorkflowStepStatus::Locked);
});

it('scopes an instance and its steps to the active company', function () {
    $other = Company::factory()->create();
    $otherSubject = WorkflowSubject::create(['company_id' => $other->id]);

    $mine = $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);
    $this->engine->start($otherSubject, $this->definition, [
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    app(CompanyContext::class)->setCompany($this->company);

    expect(WorkflowInstance::pluck('id')->all())->toBe([$mine->id])
        // The steps table is tagged too, because "waiting on me" is queried straight off it.
        ->and(WorkflowStep::count())->toBe(1);
});

it('finds the steps waiting on a given person', function () {
    $sv = supervisor($this->company);
    $hr = hrAdmin($this->company);

    $this->engine->start($this->subject, $this->definition, [
        StepBlueprint::forUser('Atasan langsung', $sv),
        StepBlueprint::forRole('HR Admin', Role::HrAdmin),
    ]);

    expect(WorkflowStep::waitingOn($sv)->count())->toBe(1)
        // HR's step is locked until the supervisor decides, so nothing waits on HR yet.
        ->and(WorkflowStep::waitingOn($hr)->count())->toBe(0);
});
