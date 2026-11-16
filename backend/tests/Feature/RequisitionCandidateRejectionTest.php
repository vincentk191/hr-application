<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Jobs\DeliverEmail;
use App\Mail\CandidateInterviewCancelled;
use App\Mail\CandidateReminder;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Models\Department;
use App\Models\EmailMessage;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

/** The company's mailbox, switched on: without it a rejection email cannot be scheduled. */
function rejectionMailbox(): void
{
    CompanyMailSetting::factory()->forCompany(test()->company)->create(['from_address' => 'recruitment@majujaya.test']);
}

function rejectionEmails(Candidate $candidate)
{
    return EmailMessage::acrossCompanies()->where('related_id', $candidate->id)->where('kind', EmailKind::CandidateRejected->value);
}

it('schedules the reviewed email for the next morning only for an invited candidate, and never with the reason', function () {
    Queue::fake();
    rejectionMailbox();
    $requisition = rejectionRequisition();
    $invited = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    $never = Candidate::factory()->for($requisition)->create(['email' => 'belum@example.com']);
    $quiet = Candidate::factory()->for($requisition)->create(['email' => 'diam@example.com']);
    rejectionInvite($requisition, $invited);
    rejectionInvite($requisition, $quiet);
    $draft = ['email_subject' => 'Status lamaran â€” Staf Gudang', 'email_body' => "Halo Budi,\n\nTerima kasih."];

    rejectCandidate($requisition, $quiet)->assertRedirect();
    rejectCandidate($requisition, $never, ['note' => 'Rahasia', 'notify_candidate' => '1', ...$draft])->assertRedirect();

    expect(rejectionEmails($quiet)->exists())->toBeFalse()
        ->and(rejectionEmails($never)->exists())->toBeFalse();

    rejectCandidate($requisition, $invited, ['note' => 'Rahasia internal', 'notify_candidate' => '1', ...$draft])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi ditolak dan emailnya dijadwalkan besok pagi.']);

    $message = rejectionEmails($invited)->sole();

    expect($message->to_email)->toBe('budi@example.com')
        ->and($message->status)->toBe(EmailStatus::Pending)
        ->and($message->subject)->toBe('Status lamaran â€” Staf Gudang')
        ->and($message->body)->not->toContain('Rahasia internal')
        ->and($message->send_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe('2026-11-05 08:00');
    Queue::assertNotPushed(DeliverEmail::class);
});

it('asks for the draft when it is ticked, and refuses while the company mailbox is off', function () {
    Queue::fake();
    $requisition = rejectionRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['email' => 'budi@example.com']);
    rejectionInvite($requisition, $candidate);

    rejectCandidate($requisition, $candidate, ['note' => 'x', 'notify_candidate' => '1'])
        ->assertSessionHasErrors(['email_subject', 'email_body']);

    $draft = ['email_subject' => 'Halo', 'email_body' => 'Teks'];

    rejectCandidate($requisition, $candidate, ['note' => 'x', 'notify_candidate' => '1', ...$draft])
        ->assertSessionHasErrors('notify_candidate');

    expect($candidate->refresh()->isRejected())->toBeFalse()
        ->and(rejectionEmails($candidate)->exists())->toBeFalse();
});

it('stops a waiting rejection email when HR takes the rejection back, but not one that already went', function () {
    Queue::fake();
    rejectionMailbox();
    $requisition = rejectionRequisition();
    $waiting = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    $gone = Candidate::factory()->for($requisition)->create(['name' => 'Ani', 'email' => 'ani@example.com']);
    rejectionInvite($requisition, $waiting);
    rejectionInvite($requisition, $gone);
    $draft = ['email_subject' => 'Halo', 'email_body' => 'Teks'];

    rejectCandidate($requisition, $waiting, ['note' => 'x', 'notify_candidate' => '1', ...$draft]);
    rejectCandidate($requisition, $gone, ['note' => 'x', 'notify_candidate' => '1', ...$draft]);
    rejectionEmails($gone)->update(['status' => EmailStatus::Sent->value, 'sent_at' => now()]);

    $this->actingAs($this->hr)->delete(route('recruitment.candidates.restore', [$requisition, $waiting]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi kembali diproses. Email penolakan yang menunggu dibatalkan.']);
    $this->actingAs($this->hr)->delete(route('recruitment.candidates.restore', [$requisition, $gone]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Ani kembali diproses.']);

    expect(rejectionEmails($waiting)->sole()->status)->toBe(EmailStatus::Cancelled)
        ->and(rejectionEmails($gone)->sole()->status)->toBe(EmailStatus::Sent);
});

it('lets HR send a failed rejection email again, and only then', function () {
    Queue::fake();
    rejectionMailbox();
    $requisition = rejectionRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['email' => 'budi@example.com']);
    rejectionInvite($requisition, $candidate);
    rejectCandidate($requisition, $candidate, ['note' => 'x', 'notify_candidate' => '1', 'email_subject' => 'Halo', 'email_body' => 'Teks']);
    $route = route('recruitment.candidates.rejection-email.resend', [$requisition, $candidate]);

    $this->actingAs($this->hr)->post($route)->assertForbidden();

    rejectionEmails($candidate)->update(['status' => EmailStatus::Failed->value, 'error' => 'Ditolak server.']);

    $this->actingAs($this->requester)->post($route)->assertForbidden();
    $this->actingAs($this->hr)->post($route)->assertRedirect();

    expect(rejectionEmails($candidate)->sole()->status)->toBe(EmailStatus::Pending);
    Queue::assertPushed(DeliverEmail::class);
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
