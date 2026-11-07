<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Interview feedback (REC-INT): HR and the new hire's direct superior each give a candidate one evaluation after the
 * interview: a 1 to 5 rating, a recommendation and a note. The superior sees only the candidates invited to interview, and
 * without their contact details.
 *
 * "Now" is 2026-11-10 12:00 in Jakarta. Budi's interview was at 10:00 today, Ani's is tomorrow, Citra was not invited.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = evaluationUser(Role::HrAdmin, $this->company);
    $this->management = evaluationUser(Role::Management, $this->company);
    $this->requester = evaluationUser(Role::Supervisor, $this->company);
    $this->stranger = evaluationUser(Role::Employee, $this->company);
    $this->boss = evaluationUser(Role::Supervisor, $this->company);

    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Pak Atasan', 'user_id' => $this->boss->id]);
    Employee::factory()->forCompany($this->company)->create(['full_name' => 'Orang Lain', 'user_id' => $this->stranger->id]);

    $this->requisition = Requisition::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'created_by_user_id' => $this->requester->id,
        'manager_employee_id' => $this->bossEmployee->id,
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($this->requisition, new RequisitionWorkflow, [], $this->requester)->update(['current_stage' => 'interview']);

    $this->budi = Candidate::factory()->for($this->requisition)->create(['name' => 'Budi', 'phone' => '081200000001', 'note' => 'Catatan HR', 'cv_path' => 'candidate-cvs/x.pdf', 'cv_name' => 'cv-budi.pdf', 'invited_at' => now(), 'invite_expires_at' => now(), 'interview_at' => '2026-11-10 03:00:00', 'interview_mode' => 'online', 'interview_place' => 'https://meet.google.com/x']);
    $this->ani = Candidate::factory()->for($this->requisition)->create(['name' => 'Ani', 'invited_at' => now(), 'invite_expires_at' => now()->addDay(), 'interview_at' => '2026-11-11 03:00:00', 'interview_mode' => 'online', 'interview_place' => 'x']);
    $this->citra = Candidate::factory()->for($this->requisition)->create(['name' => 'Citra']);
    Storage::disk('local')->put('candidate-cvs/x.pdf', 'pdf');
});

function evaluationUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $fields
 */
function evaluate(Candidate $candidate, User $as, array $fields = [])
{
    return test()->actingAs($as)->put(route('recruitment.candidates.evaluation', [test()->requisition, $candidate]), [
        'rating' => 4, 'recommendation' => 'continue', 'note' => 'Komunikasi baik.', ...$fields,
    ]);
}

// --- Giving feedback ---

it('lets HR and the direct superior each evaluate a candidate after the interview, once each', function () {
    evaluate($this->budi, $this->hr)->assertRedirect()->assertSessionDoesntHaveErrors();
    evaluate($this->budi, $this->boss, ['rating' => 2, 'recommendation' => 'reject', 'note' => 'Kurang cocok.'])->assertSessionDoesntHaveErrors();

    expect($this->budi->evaluations()->count())->toBe(2);

    // Saving again replaces one's own, and adds nothing.
    evaluate($this->budi, $this->hr, ['rating' => 5, 'recommendation' => 'consider', 'note' => null])->assertSessionDoesntHaveErrors();

    $mine = CandidateEvaluation::query()->where('user_id', $this->hr->id)->sole();
    expect($this->budi->evaluations()->count())->toBe(2)
        ->and($mine->rating)->toBe(5)
        ->and($mine->recommendation->value)->toBe('consider')
        ->and($mine->note)->toBeNull();
});

it('checks the rating and the recommendation', function () {
    evaluate($this->budi, $this->hr, ['rating' => 0])->assertSessionHasErrors('rating');
    evaluate($this->budi, $this->hr, ['rating' => 6])->assertSessionHasErrors('rating');
    evaluate($this->budi, $this->hr, ['rating' => ''])->assertSessionHasErrors('rating');
    evaluate($this->budi, $this->hr, ['recommendation' => 'mungkin'])->assertSessionHasErrors('recommendation');
    evaluate($this->budi, $this->hr, ['note' => str_repeat('a', 1001)])->assertSessionHasErrors('note');

    expect(CandidateEvaluation::query()->count())->toBe(0);
});

it('waits for the interview to have taken place, and for a candidate who was invited', function () {
    evaluate($this->ani, $this->hr)->assertForbidden();
    evaluate($this->citra, $this->hr)->assertForbidden();
    evaluate($this->ani, $this->boss)->assertForbidden();

    expect(CandidateEvaluation::query()->count())->toBe(0);
});

it('lets nobody else evaluate, and only at Interview or Offer', function () {
    foreach ([$this->requester, $this->management, $this->stranger] as $user) {
        evaluate($this->budi, $user)->assertForbidden();
    }

    foreach ([RequisitionStage::Screening, RequisitionStage::Filled, RequisitionStage::Cancelled] as $stage) {
        $this->requisition->workflow->update(['current_stage' => $stage->value]);
        evaluate($this->budi, $this->hr)->assertForbidden();
    }

    $this->requisition->workflow->update(['current_stage' => RequisitionStage::Offer->value]);
    evaluate($this->budi, $this->hr)->assertSessionDoesntHaveErrors();
});

it('refuses a candidate of another requisition', function () {
    $other = Requisition::factory()->create(['company_id' => $this->company->id, 'department_id' => $this->department->id, 'created_by_user_id' => $this->requester->id, 'submitted_at' => now()]);
    app(WorkflowEngine::class)->start($other, new RequisitionWorkflow, [], $this->requester)->update(['current_stage' => 'interview']);
    $stranded = Candidate::factory()->for($other)->create(['interview_at' => '2026-11-10 03:00:00']);

    evaluate($stranded, $this->hr)->assertNotFound();
});

// --- What each person sees ---

it('shows HR every candidate with the evaluations, and what they may add', function () {
    evaluate($this->budi, $this->boss, ['rating' => 3, 'recommendation' => 'consider']);

    $this->actingAs($this->hr)->get(route('recruitment.show', $this->requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisition.shortlist', 3)
            ->where('requisition.shortlist.0.phone', '081200000001')
            ->where('requisition.shortlist.0.canEvaluate', true)
            ->where('requisition.shortlist.0.evaluations.0.author', $this->boss->name)
            ->where('requisition.shortlist.0.evaluations.0.rating', 3)
            ->where('requisition.shortlist.0.evaluations.0.recommendationLabel', 'Pertimbangkan')
            ->where('requisition.shortlist.0.evaluations.0.isMine', false)
            ->where('requisition.shortlist.1.canEvaluate', false)
            ->where('requisition.shortlist.2.canEvaluate', false));
});

it('shows the direct superior only the invited candidates, without contact details, and lets them open the CV', function () {
    $this->actingAs($this->boss)->get(route('recruitment.show', $this->requisition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisition.shortlist', 2)
            ->where('requisition.shortlist.0.name', 'Budi')
            ->where('requisition.shortlist.0.phone', null)
            ->where('requisition.shortlist.0.email', null)
            ->where('requisition.shortlist.0.note', null)
            ->where('requisition.shortlist.0.canEvaluate', true)
            ->where('requisition.shortlist.1.name', 'Ani')
            ->where('requisition.shortlist.1.canEvaluate', false)
            ->where('requisition.canManageInterview', false)
            ->where('requisition.canManageScreening', false));

    $this->actingAs($this->boss)->get(route('recruitment.candidates.cv', [$this->requisition, $this->budi]))->assertOk();

    $this->citra->update(['cv_path' => 'candidate-cvs/x.pdf', 'cv_name' => 'cv-citra.pdf']);
    $this->actingAs($this->boss)->get(route('recruitment.candidates.cv', [$this->requisition, $this->citra]))->assertForbidden();
});

it('gives the direct superior the menu entry and nothing else of Rekrutmen', function () {
    $this->actingAs($this->boss)->get(route('recruitment.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('requisitions', 1));

    $this->actingAs($this->boss)->post(route('recruitment.candidates.store', $this->requisition), ['name' => 'X', 'phone' => '081211112222', 'source' => 'glints'])->assertForbidden();
    $this->actingAs($this->boss)->post(route('recruitment.candidates.invite', [$this->requisition, $this->citra]), ['interview_at' => '2026-12-01T10:00', 'interview_mode' => 'online', 'interview_place' => 'x'])->assertForbidden();
});

it('shows nothing of the candidates to a stranger, the requester or Management', function () {
    foreach ([$this->requester, $this->management] as $user) {
        $this->actingAs($user)->get(route('recruitment.show', $this->requisition))
            ->assertInertia(fn (Assert $page) => $page->where('requisition.shortlist', null));
    }

    $this->actingAs($this->stranger)->get(route('recruitment.show', $this->requisition))->assertForbidden();
    $this->actingAs($this->stranger)->get(route('recruitment.candidates.cv', [$this->requisition, $this->budi]))->assertForbidden();
});

it('keeps the direct superior out until the requisition is sent', function () {
    $this->requisition->workflow->update(['current_stage' => 'draft']);

    $this->actingAs($this->boss)->get(route('recruitment.show', $this->requisition))->assertForbidden();
});

// --- The badge on "Rekrutmen" ---

it('counts the interviews held that a person has not yet evaluated', function () {
    $todo = function (User $user): int {
        $count = null;
        test()->actingAs($user)->get(route('dashboard'))->assertInertia(function (Assert $page) use (&$count): void {
            $count = $page->toArray()['props']['recruitmentTodo'];
        });

        return $count;
    };

    // Budi's interview has been held; Ani's has not.
    expect($todo($this->boss))->toBe(1)->and($todo($this->hr))->toBe(1)->and($todo($this->stranger))->toBe(0);

    evaluate($this->budi, $this->boss);
    expect($todo($this->boss))->toBe(0)->and($todo($this->hr))->toBe(1);

    evaluate($this->budi, $this->hr);
    expect($todo($this->hr))->toBe(0);
});
