<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\OfferStage;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Mail\CandidateOfferSent;
use App\Mail\CandidateOfferWithdrawn;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\CandidateOffer;
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
 * The terms, the letter and sending of an approved offer (REC-OFR, REC-OFS-003): HR fills in the start date and probation
 * beside the salary Management set, prints the letter, and marks the offer as sent, emailing the candidate only when it
 * is ticked.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Gudang']);

    $this->hr = letterUser(Role::HrAdmin, $this->company);
    $this->requester = letterUser(Role::Supervisor, $this->company);
    $this->management = letterUser(Role::Management, $this->company);
    $this->boss = letterUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id, 'full_name' => 'Pak Bos']);
});

function letterUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * An offer Management has approved at a salary of Rp 4.500.000, for a chosen candidate with the given email.
 */
function approvedLetterOffer(?string $email = 'budi@example.com'): array
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
        ->update(['current_stage' => RequisitionStage::Offer->value]);

    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => $email]);

    app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], test()->hr);

    CandidateEvaluation::factory()->create(['candidate_id' => $candidate->id, 'user_id' => test()->hr->id]);
    $candidate->forceFill(['selected_at' => now(), 'selected_by_user_id' => test()->boss->id])->save();

    test()->actingAs(test()->hr)->post(route('recruitment.offers.store', $requisition), ['candidate_ids' => [$candidate->id]]);
    $offer = CandidateOffer::query()->sole();
    test()->actingAs(test()->management)->put(route('recruitment.offers.decide', [$requisition, $offer]), ['decision' => 'approve', 'salary' => 4500000]);

    return [$requisition->refresh(), $offer->refresh()];
}

function setLetterTerms(Requisition $requisition, CandidateOffer $offer, array $fields, ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->put(route('recruitment.offers.terms', [$requisition, $offer]), $fields);
}

it('lets HR set the start date and probation of an approved offer, and leaves Management\'s salary alone', function () {
    [$requisition, $offer] = approvedLetterOffer();

    expect($offer->hasTerms())->toBeFalse();

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3])
        ->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Ketentuan penawaran Budi disimpan.']);

    $offer->refresh();

    expect($offer->start_on->toDateString())->toBe('2026-12-01')
        ->and($offer->probation_months)->toBe(3)
        ->and($offer->salary)->toBe(4500000)
        ->and($offer->hasTerms())->toBeTrue()
        ->and(Activity::query()->where('description', 'Ketentuan penawaran diisi')->count())->toBe(1);

    // Until it is sent, the terms can be corrected.
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-15', 'probation_months' => 0])->assertSessionDoesntHaveErrors();

    expect($offer->refresh()->start_on->toDateString())->toBe('2026-12-15')->and($offer->probation_months)->toBe(0);
});

it('wants a start date after today and a probation of at most three months', function () {
    [$requisition, $offer] = approvedLetterOffer();

    setLetterTerms($requisition, $offer, [])->assertSessionHasErrors(['start_on', 'probation_months']);
    setLetterTerms($requisition, $offer, ['start_on' => '2026-11-04', 'probation_months' => 3])->assertSessionHasErrors('start_on');
    setLetterTerms($requisition, $offer, ['start_on' => '2026-10-01', 'probation_months' => 3])->assertSessionHasErrors('start_on');
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 4])->assertSessionHasErrors('probation_months');
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => -1])->assertSessionHasErrors('probation_months');
    setLetterTerms($requisition, $offer, ['start_on' => '2026-11-05', 'probation_months' => 0])->assertSessionDoesntHaveErrors();

    expect($offer->refresh()->start_on->toDateString())->toBe('2026-11-05');
});

it('leaves the terms to HR, for an approved offer only', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $terms = ['start_on' => '2026-12-01', 'probation_months' => 3];

    setLetterTerms($requisition, $offer, $terms, $this->management)->assertForbidden();
    setLetterTerms($requisition, $offer, $terms, $this->boss)->assertForbidden();
    setLetterTerms($requisition, $offer, $terms, $this->requester)->assertForbidden();

    $this->actingAs($this->hr)->delete(route('recruitment.offers.withdraw', [$requisition, $offer]))->assertRedirect();
    setLetterTerms($requisition, $offer, $terms)->assertForbidden();

    expect($offer->refresh()->start_on)->toBeNull();
});

it('builds the letter for HR once the terms are set, from the requisition and the offer', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $letter = route('recruitment.offers.letter', [$requisition, $offer]);

    $this->actingAs($this->hr)->get($letter)->assertForbidden();

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    $this->actingAs($this->hr)->get($letter)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('recruitment/offer-letter')
        ->where('letter.company', 'PT Maju Jaya')
        ->where('letter.candidate', 'Budi')
        ->where('letter.position', 'Staf Gudang')
        ->where('letter.department', 'Gudang')
        ->where('letter.manager', 'Pak Bos')
        ->where('letter.date', '4 November 2026')
        ->where('letter.startOn', '1 Desember 2026')
        ->where('letter.probation', '3 bulan')
        ->where('letter.salary', 'Rp 4.500.000'));

    foreach ([$this->management, $this->boss, $this->requester] as $other) {
        $this->actingAs($other)->get($letter)->assertForbidden();
    }
});

it('marks an offer as sent without emailing the candidate unless HR ticks it', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditandai terkirim.']);

    $offer->refresh();

    expect($offer->stage())->toBe(OfferStage::Sent)
        ->and($offer->isLive())->toBeTrue()
        ->and($offer->sent_at)->not->toBeNull()
        ->and($offer->sent_by_user_id)->toBe($this->hr->id)
        ->and($offer->emailed_at)->toBeNull()
        ->and(Activity::query()->where('description', 'Penawaran dikirim ke kandidat')->count())->toBe(1);

    Mail::assertNotQueued(CandidateOfferSent::class);
});

it('emails the candidate the terms when HR ticks it and the candidate has an address', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), ['notify_candidate' => '1'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditandai terkirim dan dikabarkan lewat email.']);

    Mail::assertQueued(CandidateOfferSent::class, fn (CandidateOfferSent $mail): bool => $mail->hasTo('budi@example.com')
        && str_contains($mail->body, 'Rp 4.500.000')
        && str_contains($mail->body, '1 Desember 2026')
        && str_contains($mail->body, '3 bulan'));

    expect($offer->refresh()->emailed_at)->not->toBeNull();
});

it('sends no email to a candidate without an address, even when ticked', function () {
    [$requisition, $offer] = approvedLetterOffer(email: null);
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 0]);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), ['notify_candidate' => '1'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditandai terkirim.']);

    Mail::assertNotQueued(CandidateOfferSent::class);
    expect($offer->refresh()->stage())->toBe(OfferStage::Sent)->and($offer->emailed_at)->toBeNull();
});

it('needs the terms before sending, sends once, and locks the terms and the withdrawal afterwards', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $send = route('recruitment.offers.send', [$requisition, $offer]);

    $this->actingAs($this->hr)->post($send)->assertForbidden();

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    $this->actingAs($this->management)->post($send)->assertForbidden();
    $this->actingAs($this->boss)->post($send)->assertForbidden();

    $this->actingAs($this->hr)->post($send)->assertRedirect();
    $this->actingAs($this->hr)->post($send)->assertForbidden();

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-20', 'probation_months' => 1])->assertForbidden();

    // The letter can still be printed again.
    $this->actingAs($this->hr)->get(route('recruitment.offers.letter', [$requisition, $offer]))->assertOk();

    expect($offer->refresh()->start_on->toDateString())->toBe('2026-12-01');
});

it('keeps HR from turning down or removing a candidate while an offer is out to them', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $candidate = $offer->candidate;
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    // Approved but not sent: the candidate has nothing yet, so these still work (and take the offer back with them).
    $flags = fn () => $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['shortlist'][0];
    expect($flags())->toMatchArray(['canReject' => true, 'canRemove' => true]);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));

    expect($flags())->toMatchArray(['canReject' => false, 'canRemove' => false]);

    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$requisition, $candidate]), ['note' => 'Tidak cocok'])->assertForbidden();
    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertForbidden();

    expect($offer->refresh()->stage())->toBe(OfferStage::Sent)->and(Candidate::query()->count())->toBe(1);
});

it('lets HR take a sent offer back with a reason, emailing the candidate only when ticked, and then turn the candidate down', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));
    $withdraw = route('recruitment.offers.withdraw', [$requisition, $offer]);

    $this->actingAs($this->management)->delete($withdraw, ['note' => 'Tidak jadi'])->assertForbidden();
    $this->actingAs($this->hr)->delete($withdraw)->assertSessionHasErrors('note');
    expect($offer->refresh()->stage())->toBe(OfferStage::Sent);

    $this->actingAs($this->hr)->delete($withdraw, ['note' => 'Anggaran dipotong', 'notify_candidate' => '1'])
        ->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditarik dan kandidat dikabari lewat email.']);

    expect($offer->refresh()->stage())->toBe(OfferStage::Withdrawn)
        ->and(Activity::query()->where('description', 'Penawaran ditarik')->latest('id')->first()->properties['reason'])->toBe('Anggaran dipotong');

    Mail::assertQueued(CandidateOfferWithdrawn::class, fn (CandidateOfferWithdrawn $mail): bool => $mail->hasTo('budi@example.com') && ! str_contains($mail->body, 'Anggaran dipotong'));

    // Nothing is out any more, so the candidate can be turned down again.
    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$requisition, $offer->candidate]), ['note' => 'Tidak cocok'])->assertRedirect()->assertSessionDoesntHaveErrors();
});

it('shows HR what they may do with the offer on the Penawaran tab', function () {
    [$requisition, $offer] = approvedLetterOffer();

    $props = fn () => collect($this->actingAs($this->hr)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['offers'])->sole();

    expect($props())->toMatchArray(['canSetTerms' => true, 'canSend' => false, 'canViewLetter' => false, 'startOn' => null]);

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    expect($props())->toMatchArray(['canSetTerms' => true, 'canSend' => true, 'canViewLetter' => true, 'startOn' => '2026-12-01', 'probationMonths' => 3]);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));

    expect($props())->toMatchArray(['canSetTerms' => false, 'canSend' => false, 'canViewLetter' => true, 'stage' => 'sent', 'sentOn' => '2026-11-04']);
});
