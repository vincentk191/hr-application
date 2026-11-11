<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\OfferStage;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Mail\CandidatePositionFilled;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\CandidateOffer;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

/**
 * The candidate's answer to a sent offer, and filling the position (REC-OFS-004/008/009, REC-FIL-001/003/005): HR marks an offer
 * accepted (counting toward the quota, refused once it is full) or declined (with a reason, leaving the place open), and once
 * the quota is filled "Tandai Selesai" moves the requisition to Terisi, withdrawing what is still out, ending the form links
 * and, if ticked, telling the other interviewed candidates.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta; interviews are on the 5th.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = fillUser(Role::HrAdmin, $this->company);
    $this->requester = fillUser(Role::Supervisor, $this->company);
    $this->management = fillUser(Role::Management, $this->company);
    $this->boss = fillUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);
});

function fillUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function fillRequisition(int $quota = 2): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'manager_employee_id' => test()->bossEmployee->id,
        'position_name' => 'Staf Gudang',
        'quota' => $quota,
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => RequisitionStage::Offer->value]);

    return $requisition->refresh();
}

/**
 * A candidate interviewed and chosen, put to Management, approved at Rp 4.500.000, given terms and, unless told otherwise,
 * sent.
 */
function fillOffer(Requisition $requisition, string $name, ?string $email = null, bool $send = true): CandidateOffer
{
    $candidate = Candidate::factory()->for($requisition)->create(['name' => $name, 'email' => $email]);

    app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], test()->hr);

    CandidateEvaluation::factory()->create(['candidate_id' => $candidate->id, 'user_id' => test()->hr->id]);
    $candidate->forceFill(['selected_at' => now(), 'selected_by_user_id' => test()->boss->id])->save();

    test()->actingAs(test()->hr)->post(route('recruitment.offers.store', $requisition), ['candidate_ids' => [$candidate->id]]);
    $offer = $candidate->offers()->latest('id')->firstOrFail();
    test()->actingAs(test()->management)->put(route('recruitment.offers.decide', [$requisition, $offer]), ['decision' => 'approve', 'salary' => 4500000]);

    if ($send) {
        test()->actingAs(test()->hr)->put(route('recruitment.offers.terms', [$requisition, $offer]), ['start_on' => '2026-12-01', 'probation_months' => 3]);
        test()->actingAs(test()->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));
    }

    return $offer->refresh();
}

function fillAccept(Requisition $requisition, CandidateOffer $offer, ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.offers.accept', [$requisition, $offer]));
}

function fillDecline(Requisition $requisition, CandidateOffer $offer, array $fields, ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->put(route('recruitment.offers.decline', [$requisition, $offer]), $fields);
}

function fillClose(Requisition $requisition, array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.filled', $requisition), $fields);
}

it('counts an accepted offer as filled, tells the superior and the requester, and keeps the hire from being turned down, removed or unchosen', function () {
    $requisition = fillRequisition();
    $offer = fillOffer($requisition, 'Budi');

    fillAccept($requisition, $offer)->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi diterima (1 dari 2).']);

    $offer->refresh();
    $candidate = $offer->candidate;

    expect($offer->stage())->toBe(OfferStage::Accepted)
        ->and($offer->responded_at)->not->toBeNull()
        ->and($offer->isLive())->toBeFalse()
        ->and($requisition->filledCount())->toBe(1)
        ->and($requisition->stage())->toBe(RequisitionStage::Offer)
        ->and(Activity::query()->where('description', 'Penawaran diterima kandidat')->count())->toBe(1)
        ->and($this->boss->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Budi menerima penawaran')
        ->and($this->requester->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Budi menerima penawaran')
        ->and($this->hr->notifications()->get()->pluck('data.title')->implode(' '))->not->toContain('menerima penawaran');

    // The list shows how many places are taken.
    $row = collect($this->actingAs($this->hr)->get(route('recruitment.index'))->viewData('page')['props']['requisitions'])->firstWhere('id', $requisition->id);
    expect($row['filled'])->toBe(1);

    // A hire is not turned down, removed or unchosen, and cannot be proposed again.
    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$requisition, $candidate]), ['note' => 'Tidak jadi'])->assertForbidden();
    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertForbidden();
    $this->actingAs($this->boss)->delete(route('recruitment.candidates.unselect', [$requisition, $candidate]))->assertForbidden();
    $this->actingAs($this->hr)->post(route('recruitment.offers.store', $requisition), ['candidate_ids' => [$candidate->id]])->assertSessionHasErrors('candidate_ids');
});

it('refuses an acceptance once the quota is full, and only for HR and a sent offer', function () {
    $requisition = fillRequisition(quota: 1);
    $first = fillOffer($requisition, 'Budi');
    $second = fillOffer($requisition, 'Ani');
    $unsent = fillOffer($requisition, 'Citra', send: false);

    fillAccept($requisition, $first, $this->management)->assertForbidden();
    fillAccept($requisition, $first, $this->boss)->assertForbidden();
    fillAccept($requisition, $unsent)->assertForbidden();

    fillAccept($requisition, $first)->assertSessionDoesntHaveErrors();
    fillAccept($requisition, $second)->assertSessionHasErrors('answer');

    expect($second->refresh()->stage())->toBe(OfferStage::Sent)->and($requisition->filledCount())->toBe(1);
});

it('records a decline with a reason, leaves the place open and keeps the candidate from being chosen again', function () {
    $requisition = fillRequisition();
    $offer = fillOffer($requisition, 'Budi');
    $candidate = $offer->candidate;

    fillDecline($requisition, $offer, [])->assertSessionHasErrors('note');
    fillDecline($requisition, $offer, ['note' => 'Gaji kurang'], $this->management)->assertForbidden();

    fillDecline($requisition, $offer, ['note' => 'Gaji kurang'])->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi menolak penawaran; tempat masih terbuka.']);

    $offer->refresh();

    expect($offer->stage())->toBe(OfferStage::Declined)
        ->and($offer->decline_reason)->toBe('Gaji kurang')
        ->and($offer->responded_at)->not->toBeNull()
        ->and($candidate->refresh()->isSelected())->toBeFalse()
        ->and($requisition->filledCount())->toBe(0)
        ->and(Activity::query()->where('description', 'Penawaran ditolak kandidat')->count())->toBe(1)
        ->and($this->boss->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Budi menolak penawaran');

    // The superior may choose someone else but not this candidate again.
    $this->actingAs($this->boss)->post(route('recruitment.candidates.select', [$requisition, $candidate]))->assertForbidden();

    // Only HR sees why.
    $seen = fn (User $user) => collect($this->actingAs($user)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['offers'])->sole()['declineReason'];
    expect($seen($this->hr))->toBe('Gaji kurang')->and($seen($this->management))->toBeNull()->and($seen($this->boss))->toBeNull();
});

it('closes the requisition once the quota is filled: withdraws what is out, ends the unused form links, and counts nobody extra', function () {
    $requisition = fillRequisition(quota: 1);
    $hired = fillOffer($requisition, 'Budi', 'budi@example.com');
    $waiting = fillOffer($requisition, 'Ani', 'ani@example.com');

    fillClose($requisition)->assertSessionHasErrors('filled');
    expect($requisition->refresh()->stage())->toBe(RequisitionStage::Offer);

    fillAccept($requisition, $hired);

    $anisLink = $waiting->candidate->refresh()->invite_expires_at;
    expect($anisLink->isFuture())->toBeTrue();

    fillClose($requisition)->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'REQ-'.str_pad((string) $requisition->id, 4, '0', STR_PAD_LEFT).' terisi.']);

    expect($requisition->refresh()->stage())->toBe(RequisitionStage::Filled)
        ->and($hired->refresh()->stage())->toBe(OfferStage::Accepted)
        ->and($waiting->refresh()->stage())->toBe(OfferStage::Withdrawn)
        ->and($waiting->candidate->refresh()->invite_expires_at->isFuture())->toBeFalse()
        ->and($waiting->candidate->inviteState())->toBe('expired')
        ->and(Activity::query()->where('description', 'Lowongan terisi')->count())->toBe(1)
        ->and($this->requester->notifications()->get()->pluck('data.title')->implode(' '))->toContain('terisi')
        ->and($this->management->notifications()->get()->pluck('data.title')->implode(' '))->toContain('terisi');

    Mail::assertNotQueued(CandidatePositionFilled::class);
});

it('emails the other interviewed candidates that the position is filled only when ticked, never the hire', function () {
    $requisition = fillRequisition(quota: 1);
    $hired = fillOffer($requisition, 'Budi', 'budi@example.com');
    fillOffer($requisition, 'Ani', 'ani@example.com');
    fillOffer($requisition, 'Citra', null);
    fillAccept($requisition, $hired);

    $props = fn () => $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition'];

    expect($props())->toMatchArray(['canMarkFilled' => true, 'emailableOthers' => 1, 'filledBlockers' => []]);

    fillClose($requisition, ['notify_candidates' => '1'])->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'REQ-'.str_pad((string) $requisition->id, 4, '0', STR_PAD_LEFT).' terisi. 1 kandidat dikabari lewat email.']);

    Mail::assertQueued(CandidatePositionFilled::class, 1);
    Mail::assertQueued(CandidatePositionFilled::class, fn (CandidatePositionFilled $mail): bool => $mail->hasTo('ani@example.com') && str_contains($mail->body, 'sudah terisi'));
});

it('leaves "Tandai Selesai" to HR at Penawaran', function () {
    $requisition = fillRequisition(quota: 1);
    fillAccept($requisition, fillOffer($requisition, 'Budi'));

    fillClose($requisition, [], $this->management)->assertForbidden();
    fillClose($requisition, [], $this->boss)->assertForbidden();
    fillClose($requisition, [], $this->requester)->assertForbidden();

    fillClose($requisition)->assertRedirect();
    fillClose($requisition)->assertForbidden();
});

it('lets HR mark the listings as taken down once the position is filled, and take the mark back', function () {
    $requisition = fillRequisition(quota: 1);
    $posting = RequisitionPosting::factory()->create(['requisition_id' => $requisition->id]);
    $takedown = route('recruitment.postings.takedown', [$requisition, $posting]);

    $this->actingAs($this->hr)->put($takedown)->assertForbidden();

    fillAccept($requisition, fillOffer($requisition, 'Budi'));
    fillClose($requisition);

    $this->actingAs($this->management)->put($takedown)->assertForbidden();
    $this->actingAs($this->hr)->put($takedown)->assertRedirect();
    expect($posting->refresh()->taken_down_at)->not->toBeNull();

    $this->actingAs($this->hr)->put($takedown);
    expect($posting->refresh()->taken_down_at)->toBeNull();
});
