<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Mail\CandidateInterviewCancelled;
use App\Mail\CandidateRejected;
use App\Mail\CandidateReminder;
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
 * Turning a candidate down (REC-CAN-003): HR gives a reason, may email the candidate, and the candidate is out of the
 * process (no reminders, no form, no mail-merge row) while the record stays and HR can take it back.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = rejectionUser(Role::HrAdmin, $this->company);
    $this->requester = rejectionUser(Role::Supervisor, $this->company);
});

function rejectionUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function rejectionRequisition(RequisitionStage $stage = RequisitionStage::Interview): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

/** Invites the candidate for tomorrow, as HR, and returns the personal link. */
function rejectionInvite(Requisition $requisition, Candidate $candidate): string
{
    return app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], test()->hr)['link'];
}

function rejectCandidate(Requisition $requisition, Candidate $candidate, array $fields = ['note' => 'Pengalaman kurang.'], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.candidates.reject', [$requisition, $candidate]), $fields);
}

it('turns a candidate down with a reason, and keeps the record', function () {
    $requisition = rejectionRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi']);

    rejectCandidate($requisition, $candidate)->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi ditolak.']);

    $candidate->refresh();
    $entry = Activity::query()->where('description', 'Kandidat ditolak')->sole();

    expect($candidate->isRejected())->toBeTrue()
        ->and($candidate->rejected_reason)->toBe('Pengalaman kurang.')
        ->and($candidate->rejected_by_user_id)->toBe($this->hr->id)
        ->and(Candidate::query()->inProcess()->whereKey($candidate->id)->exists())->toBeFalse()
        ->and($entry->causer_id)->toBe($this->hr->id)
        ->and($entry->properties['reason'])->toBe('Pengalaman kurang.');
});

it('needs a reason, and lets only HR do it, from Screening to Offer, once', function () {
    $requisition = rejectionRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    rejectCandidate($requisition, $candidate, ['note' => '  '])->assertSessionHasErrors('note');
    rejectCandidate($requisition, $candidate, as: $this->requester)->assertForbidden();

    foreach ([RequisitionStage::Posting, RequisitionStage::Filled, RequisitionStage::Cancelled] as $stage) {
        $other = rejectionRequisition($stage);
        rejectCandidate($other, Candidate::factory()->for($other)->create())->assertForbidden();
    }

    rejectCandidate(rejectionRequisition(), Candidate::factory()->for(rejectionRequisition())->create())->assertNotFound();

    expect($candidate->refresh()->isRejected())->toBeFalse();

    rejectCandidate($requisition, $candidate)->assertRedirect();
    rejectCandidate($requisition, $candidate)->assertForbidden();
});

it('emails the candidate only when HR asks, only if they were invited, and never with the reason', function () {
    $requisition = rejectionRequisition();
    $invited = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    $never = Candidate::factory()->for($requisition)->create(['email' => 'belum@example.com']);
    $quiet = Candidate::factory()->for($requisition)->create(['email' => 'diam@example.com']);
    rejectionInvite($requisition, $invited);
    rejectionInvite($requisition, $quiet);
    Mail::fake();

    rejectCandidate($requisition, $quiet)->assertRedirect();
    Mail::assertNotQueued(CandidateRejected::class);

    rejectCandidate($requisition, $never, ['note' => 'Rahasia', 'notify_candidate' => '1'])->assertRedirect();
    Mail::assertNotQueued(CandidateRejected::class);

    rejectCandidate($requisition, $invited, ['note' => 'Rahasia internal', 'notify_candidate' => '1'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi ditolak dan dikabari lewat email.']);

    Mail::assertQueued(CandidateRejected::class, 1);
    Mail::assertQueued(CandidateRejected::class, fn (CandidateRejected $mail): bool => $mail->hasTo('budi@example.com')
        && str_contains($mail->subjectLine, 'Staf Gudang')
        && str_contains($mail->body, 'Halo Budi')
        && ! str_contains($mail->body, 'Rahasia internal'));
});

it('leaves a rejected candidate out of reminders, the mail-merge file, the form and the cancellation email', function () {
    $requisition = rejectionRequisition();
    $out = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    $in = Candidate::factory()->for($requisition)->create(['name' => 'Ani', 'email' => 'ani@example.com']);
    $link = rejectionInvite($requisition, $out);
    rejectionInvite($requisition, $in);
    rejectCandidate($requisition, $out)->assertRedirect();
    Mail::fake();

    // The day before: only Ani is reminded.
    $this->artisan('recruitment:interview-reminders')->assertSuccessful();
    Mail::assertQueued(CandidateReminder::class, 1);
    Mail::assertQueued(CandidateReminder::class, fn (CandidateReminder $mail): bool => $mail->hasTo('ani@example.com'));

    // The mail-merge file has one row.
    $rows = $this->actingAs($this->hr)->get(route('recruitment.candidates.export', $requisition))->streamedContent();
    expect($rows)->toContain('Ani')->not->toContain('Budi');

    // The link says the form is closed, and takes nothing.
    auth()->logout();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00', 'Asia/Jakarta'));
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'closed'));
    $this->post($link, ['answers' => []])->assertRedirect();
    expect($out->refresh()->form_submitted_at)->toBeNull();

    // Cancelling emails Ani and not Budi.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 13:00', 'Asia/Jakarta'));
    $this->actingAs($this->hr)->post(route('recruitment.cancel', $requisition), ['note' => 'Tidak jadi', 'notify_candidates' => '1'])->assertRedirect();
    Mail::assertQueued(CandidateInterviewCancelled::class, 1);
    Mail::assertQueued(CandidateInterviewCancelled::class, fn (CandidateInterviewCancelled $mail): bool => $mail->hasTo('ani@example.com'));
});

it('does not invite, or make a new form link for, a rejected candidate', function () {
    $requisition = rejectionRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    rejectionInvite($requisition, $candidate);
    rejectCandidate($requisition, $candidate)->assertRedirect();

    $this->actingAs($this->hr)->post(route('recruitment.candidates.invite', [$requisition, $candidate]), [
        'interview_at' => '2026-11-06T10:00', 'interview_mode' => 'online', 'interview_place' => 'https://meet.google.com/x', 'participants' => [$this->hr->id],
    ])->assertForbidden();
    $this->actingAs($this->hr)->post(route('recruitment.candidates.form-link', [$requisition, $candidate]))->assertForbidden();
});

it('takes a rejection back, so the candidate is in the process again', function () {
    $requisition = rejectionRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi']);

    // Nothing to take back yet.
    $this->actingAs($this->hr)->delete(route('recruitment.candidates.restore', [$requisition, $candidate]))->assertForbidden();

    rejectCandidate($requisition, $candidate)->assertRedirect();
    $this->actingAs($this->requester)->delete(route('recruitment.candidates.restore', [$requisition, $candidate]))->assertForbidden();

    $this->actingAs($this->hr)->delete(route('recruitment.candidates.restore', [$requisition, $candidate]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi kembali diproses.']);

    $candidate->refresh();
    expect($candidate->isRejected())->toBeFalse()
        ->and($candidate->rejected_reason)->toBeNull()
        ->and($candidate->rejected_by_user_id)->toBeNull()
        ->and(Activity::query()->where('description', 'Penolakan kandidat dibatalkan')->count())->toBe(1);
});

it('tells the page who is rejected, and keeps the reason to HR', function () {
    $boss = rejectionUser(Role::Supervisor, $this->company);
    $bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $boss->id]);
    $requisition = rejectionRequisition();
    $requisition->update(['manager_employee_id' => $bossEmployee->id]);
    $rejected = Candidate::factory()->for($requisition)->create(['name' => 'Budi']);
    $active = Candidate::factory()->for($requisition)->create(['name' => 'Ani']);
    rejectionInvite($requisition, $rejected);
    rejectionInvite($requisition, $active);
    rejectCandidate($requisition, $rejected, ['note' => 'Pengalaman kurang.'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.shortlist.0.isRejected', true)
            ->where('requisition.shortlist.0.rejectedReason', 'Pengalaman kurang.')
            ->where('requisition.shortlist.0.rejectedBy', $this->hr->name)
            ->where('requisition.shortlist.0.canReject', false)
            ->where('requisition.shortlist.0.canRestore', true)
            ->where('requisition.shortlist.1.isRejected', false)
            ->where('requisition.shortlist.1.canReject', true)
            ->where('requisition.shortlist.1.canRestore', false));

    // The direct superior sees that Budi is out, not why.
    $this->actingAs($boss)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.shortlist.0.isRejected', true)
            ->where('requisition.shortlist.0.rejectedReason', null)
            ->where('requisition.shortlist.0.rejectedBy', null)
            ->where('requisition.shortlist.0.canReject', false));
});
