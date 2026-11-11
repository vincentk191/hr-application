<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\OfferStage;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\CandidateOffer;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Dashboard\AdminOverview;
use App\Support\Recruitment\RecruitmentTodo;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Offers to the candidates the direct superior chose (REC-OFS): HR puts them to Management, Management approves each with a
 * starting salary or rejects it with a reason, HR can pull one back, and Management sees the candidates who have an offer
 * and no others.
 *
 * "Now" is 2026-11-05 14:00 in Jakarta, after the interviews held that morning.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = offerUser(Role::HrAdmin, $this->company);
    $this->requester = offerUser(Role::Supervisor, $this->company);
    $this->management = offerUser(Role::Management, $this->company);
    $this->boss = offerUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);
});

function offerUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function offerRequisition(RequisitionStage $stage = RequisitionStage::Offer): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'manager_employee_id' => test()->bossEmployee->id,
        'position_name' => 'Staf Gudang',
        'quota' => 2,
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

/** A candidate interviewed on the 5th, with one evaluation, and chosen by the direct superior. */
function chosenCandidate(Requisition $requisition, string $name = 'Budi'): Candidate
{
    $candidate = Candidate::factory()->for($requisition)->create(['name' => $name]);

    app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], test()->hr);

    CandidateEvaluation::factory()->create(['candidate_id' => $candidate->id, 'user_id' => test()->hr->id]);
    $candidate->forceFill(['selected_at' => now(), 'selected_by_user_id' => test()->boss->id])->save();

    return $candidate->refresh();
}

function propose(Requisition $requisition, array $candidates, ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.offers.store', $requisition), ['candidate_ids' => collect($candidates)->pluck('id')->all()]);
}

function decideOffer(Requisition $requisition, CandidateOffer $offer, array $fields, ?User $as = null)
{
    return test()->actingAs($as ?? test()->management)->put(route('recruitment.offers.decide', [$requisition, $offer]), $fields);
}

it('lets HR put the chosen candidates to Management, one offer each, and tells Management', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition, 'Budi');
    $ani = chosenCandidate($requisition, 'Ani');

    propose($requisition, [$budi, $ani])->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 penawaran diajukan ke Manajemen.']);

    $offers = CandidateOffer::query()->get();

    expect($offers)->toHaveCount(2)
        ->and($offers->every(fn (CandidateOffer $offer): bool => $offer->stage() === OfferStage::Proposed))->toBeTrue()
        ->and($offers->every(fn (CandidateOffer $offer): bool => $offer->proposed_by_user_id === $this->hr->id))->toBeTrue()
        ->and(WorkflowStep::waitingOn($this->management, CandidateOffer::class)->count())->toBe(2)
        ->and(WorkflowStep::waitingOn($this->hr, CandidateOffer::class)->count())->toBe(0)
        ->and(app(RecruitmentTodo::class)->countFor($this->management))->toBe(2)
        ->and(Activity::query()->where('description', 'Penawaran diajukan ke Manajemen')->count())->toBe(2)
        ->and($this->management->notifications()->get()->pluck('data.title')->implode(' '))->toContain('2 penawaran menunggu persetujuan')
        ->and($this->hr->notifications()->count())->toBe(0);
});

it('needs a chosen candidate who has no live offer, and lets only HR do it, at Penawaran', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition, 'Budi');
    $unchosen = Candidate::factory()->for($requisition)->create(['name' => 'Citra']);

    $this->actingAs($this->hr)->post(route('recruitment.offers.store', $requisition), [])->assertSessionHasErrors('candidate_ids');
    propose($requisition, [$unchosen])->assertSessionHasErrors('candidate_ids');

    propose($requisition, [$budi], $this->requester)->assertForbidden();
    propose($requisition, [$budi], $this->boss)->assertForbidden();
    propose($requisition, [$budi], $this->management)->assertForbidden();

    $interview = offerRequisition(RequisitionStage::Interview);
    propose($interview, [chosenCandidate($interview, 'Dewi')])->assertForbidden();

    propose($requisition, [$budi])->assertRedirect()->assertSessionDoesntHaveErrors();
    propose($requisition, [$budi])->assertSessionHasErrors('candidate_ids');

    expect(CandidateOffer::query()->count())->toBe(1);
});

it('lets Management approve with a starting salary, tells HR, and keeps it on the audit trail', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition);
    propose($requisition, [$budi]);
    $offer = CandidateOffer::query()->sole();

    decideOffer($requisition, $offer, ['decision' => 'approve'])->assertSessionHasErrors('salary');
    decideOffer($requisition, $offer, ['decision' => 'approve', 'salary' => 0])->assertSessionHasErrors('salary');
    decideOffer($requisition, $offer, ['decision' => 'approve', 'salary' => 6000000], $this->hr)->assertForbidden();
    decideOffer($requisition, $offer, ['decision' => 'approve', 'salary' => 6000000], $this->requester)->assertForbidden();

    decideOffer($requisition, $offer, ['decision' => 'approve', 'salary' => 6500000, 'note' => 'Sesuai anggaran.'])
        ->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi disetujui.']);

    $offer->refresh();
    $entry = Activity::query()->where('description', 'Penawaran disetujui')->sole();

    expect($offer->stage())->toBe(OfferStage::Approved)
        ->and($offer->salary)->toBe(6500000)
        ->and($entry->causer_id)->toBe($this->management->id)
        ->and($entry->properties['salary'])->toBe(6500000)
        ->and($offer->workflow->comments()->pluck('body')->all())->toBe(['Sesuai anggaran.'])
        ->and(WorkflowStep::waitingOn($this->management, CandidateOffer::class)->count())->toBe(0)
        ->and($this->hr->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Penawaran Budi disetujui')
        ->and($budi->refresh()->isSelected())->toBeTrue();

    decideOffer($requisition, $offer, ['decision' => 'approve', 'salary' => 7000000])->assertForbidden();
});

it('lets Management reject with a reason, which sends the candidate back to the interview list', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition);
    propose($requisition, [$budi]);
    $offer = CandidateOffer::query()->sole();

    decideOffer($requisition, $offer, ['decision' => 'reject', 'note' => '  '])->assertSessionHasErrors('note');

    decideOffer($requisition, $offer, ['decision' => 'reject', 'note' => 'Gaji ekspektasi terlalu tinggi.'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditolak; kandidat kembali ke daftar interview.']);

    expect($offer->refresh()->stage())->toBe(OfferStage::Rejected)
        ->and($budi->refresh()->isSelected())->toBeFalse()
        ->and($budi->isRejected())->toBeFalse()
        ->and(WorkflowStep::waitingOn($this->management, CandidateOffer::class)->count())->toBe(0)
        ->and($this->hr->notifications()->get()->pluck('data.body')->implode(' '))->toContain('Gaji ekspektasi terlalu tinggi.')
        ->and($this->boss->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Penawaran Budi ditolak');

    // The superior can choose them again, and HR can put them to Management anew.
    $budi->forceFill(['selected_at' => now(), 'selected_by_user_id' => $this->boss->id])->save();
    propose($requisition, [$budi])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($budi->offers()->count())->toBe(2)->and($budi->offers()->live()->count())->toBe(1);
});

it('lets HR pull an offer back until it is sent, and the candidate stays chosen', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition);
    propose($requisition, [$budi]);
    $offer = CandidateOffer::query()->sole();
    $url = route('recruitment.offers.withdraw', [$requisition, $offer]);

    $this->actingAs($this->management)->delete($url)->assertForbidden();
    $this->actingAs($this->boss)->delete($url)->assertForbidden();

    $this->actingAs($this->hr)->delete($url)->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditarik.']);

    expect($offer->refresh()->stage())->toBe(OfferStage::Withdrawn)
        ->and($budi->refresh()->isSelected())->toBeTrue()
        ->and(WorkflowStep::waitingOn($this->management, CandidateOffer::class)->count())->toBe(0)
        ->and(Activity::query()->where('description', 'Penawaran ditarik')->count())->toBe(1);

    $this->actingAs($this->hr)->delete($url)->assertForbidden();
    decideOffer($requisition, $offer, ['decision' => 'approve', 'salary' => 6000000])->assertForbidden();
});

it('withdraws a live offer when the candidate is turned down or removed, or the requisition is called off', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition, 'Budi');
    $ani = chosenCandidate($requisition, 'Ani');
    $citra = chosenCandidate($requisition, 'Citra');
    propose($requisition, [$budi, $ani, $citra]);

    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$requisition, $budi]), ['note' => 'Referensi buruk.'])->assertRedirect();
    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$requisition, $ani]))->assertRedirect();

    expect($budi->offers()->first()->stage())->toBe(OfferStage::Withdrawn)
        ->and(WorkflowStep::waitingOn($this->management, CandidateOffer::class)->count())->toBe(1);

    $this->actingAs($this->hr)->post(route('recruitment.cancel', $requisition), ['decision' => 'cancel', 'note' => 'Sudah terisi.'])->assertRedirect();

    expect($citra->offers()->first()->stage())->toBe(OfferStage::Withdrawn)
        ->and(WorkflowStep::waitingOn($this->management, CandidateOffer::class)->count())->toBe(0)
        ->and(app(RecruitmentTodo::class)->countFor($this->management))->toBe(0);
});

it('stops the superior taking back a choice once an offer is live for it', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition);
    $url = route('recruitment.candidates.unselect', [$requisition, $budi]);

    propose($requisition, [$budi]);
    $this->actingAs($this->boss)->delete($url)->assertForbidden();

    $this->actingAs($this->hr)->delete(route('recruitment.offers.withdraw', [$requisition, CandidateOffer::query()->sole()]))->assertRedirect();
    $this->actingAs($this->boss)->delete($url)->assertRedirect();

    expect($budi->refresh()->isSelected())->toBeFalse();
});

it('shows Management only the candidates who have an offer, without their contact details', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition, 'Budi');
    $citra = chosenCandidate($requisition, 'Citra');
    $dewi = Candidate::factory()->for($requisition)->create(['name' => 'Dewi', 'note' => 'Catatan HR']);
    propose($requisition, [$budi]);

    $this->actingAs($this->management)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.shortlist', null)
            ->has('requisition.offers', 1)
            ->where('requisition.offers.0.name', 'Budi')
            ->where('requisition.offers.0.stage', 'proposed')
            ->where('requisition.offers.0.recommendedBy', 1)
            ->where('requisition.offers.0.evaluators', 1)
            ->where('requisition.offers.0.canDecide', true)
            ->where('requisition.offers.0.canWithdraw', false)
            ->where('requisition.offers.0.candidate.phone', null)
            ->where('requisition.offers.0.candidate.email', null)
            ->where('requisition.offers.0.candidate.note', null)
            ->where('requisition.canProposeOffers', false));

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisition.offers', 1)
            ->where('requisition.offers.0.canDecide', false)
            ->where('requisition.offers.0.canWithdraw', true)
            ->where('requisition.offers.0.candidate.phone', $budi->phone)
            ->where('requisition.canProposeOffers', true));

    $this->actingAs($this->boss)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisition.offers', 1)
            ->where('requisition.offers.0.candidate.phone', null)
            ->where('requisition.offers.0.canDecide', false));

    // The requester sees neither the offers nor who they are for.
    $this->actingAs($this->requester)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page->where('requisition.offers', null));

    expect($citra->isSelected())->toBeTrue()->and($dewi->offers()->exists())->toBeFalse();
});

it('lets Management open the CV of a candidate with an offer, and no other', function () {
    $requisition = offerRequisition();
    $budi = chosenCandidate($requisition, 'Budi');
    $citra = chosenCandidate($requisition, 'Citra');
    Storage::fake('local');
    foreach ([$budi, $citra] as $candidate) {
        Storage::disk('local')->put("candidate-cvs/{$candidate->id}.pdf", 'cv');
        $candidate->forceFill(['cv_path' => "candidate-cvs/{$candidate->id}.pdf", 'cv_name' => 'cv.pdf', 'cv_size' => 2])->save();
    }
    propose($requisition, [$budi]);

    $this->actingAs($this->management)->get(route('recruitment.candidates.cv', [$requisition, $budi]))->assertOk();
    $this->actingAs($this->management)->get(route('recruitment.candidates.cv', [$requisition, $citra]))->assertForbidden();
    $this->actingAs($this->requester)->get(route('recruitment.candidates.cv', [$requisition, $budi]))->assertForbidden();
});

it('puts offers waiting for a decision on Management dashboard card', function () {
    $requisition = offerRequisition();
    propose($requisition, [chosenCandidate($requisition, 'Budi')]);

    $waiting = app(AdminOverview::class)->waiting($this->management);
    $item = collect($waiting['items'])->firstWhere('key', 'offer');

    expect($item['count'])->toBe(1)
        ->and($item['entries'][0]['title'])->toBe('Budi')
        ->and($item['entries'][0]['chip'])->toBe($requisition->reference());
});
