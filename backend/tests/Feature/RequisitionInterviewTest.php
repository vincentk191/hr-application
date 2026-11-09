<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Mail\CandidateInvitation;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Recruitment stage 04, Interview (REC-INT): HR invites a shortlisted candidate with the time and place, by email and
 * as text for WhatsApp, and the invitation carries a personal single-use link to the candidate form, where the
 * candidate answers the requisition's yes/no checklist.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = interviewUser(Role::HrAdmin, $this->company);
    $this->management = interviewUser(Role::Management, $this->company);
    $this->requester = interviewUser(Role::Supervisor, $this->company);
});

function interviewUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function interviewRequisition(RequisitionStage $stage = RequisitionStage::Interview): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
        'checklist' => [
            'education' => ['Minimal SMA/SMK'],
            'experience' => [],
            'hard' => ['Menguasai Microsoft Excel'],
            'soft' => ['Teliti'],
            'bonus' => ['Memiliki SIM B'],
        ],
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function invitationFields(array $overrides = []): array
{
    return [
        'interview_at' => '2026-11-10T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => 'Mohon hadir 10 menit lebih awal.',
        ...$overrides,
    ];
}

function invite(Requisition $requisition, Candidate $candidate, array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.candidates.invite', [$requisition, $candidate]), invitationFields($fields));
}

// --- Inviting ---

it('invites a candidate by email and hands HR the text and link for WhatsApp', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);

    invite($requisition, $candidate)->assertRedirect()->assertSessionDoesntHaveErrors();

    $candidate->refresh();
    expect($candidate->inviteState())->toBe('invited')
        ->and($candidate->interview_at->toDateTimeString())->toBe('2026-11-10 03:00:00')
        ->and($candidate->interview_mode)->toBe('online')
        ->and($candidate->invite_expires_at->toDateString())->toBe('2026-11-10')
        ->and($candidate->invited_by_user_id)->toBe($this->hr->id)
        ->and($candidate->invite_token_hash)->toHaveLength(64);

    Mail::assertQueued(CandidateInvitation::class, function (CandidateInvitation $mail): bool {
        return $mail->hasTo('budi@example.com')
            && str_contains($mail->body, 'Budi Santoso')
            && str_contains($mail->body, 'Selasa, 10 November 2026')
            && str_contains($mail->body, '10:00 WIB')
            && str_contains($mail->body, 'https://meet.google.com/abc-defg-hij')
            && str_contains($mail->body, '/lamaran/')
            && str_contains($mail->body, 'Mohon hadir 10 menit lebih awal.');
    });
});

it('does not keep the link itself, only its hash', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = null;

    Mail::fake();
    invite($requisition, $candidate);
    Mail::assertQueued(CandidateInvitation::class, function (CandidateInvitation $mail) use (&$link): bool {
        preg_match('#https?://\S+/lamaran/(\S{48})#', $mail->body, $found);
        $link = $found[1] ?? null;

        return true;
    });

    expect($link)->toHaveLength(48)
        ->and($candidate->refresh()->invite_token_hash)->toBe(Candidate::hashToken($link))
        ->and($candidate->invite_token_hash)->not->toContain($link);
});

it('sends no email to a candidate with none, but still produces the text', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['email' => null]);

    invite($requisition, $candidate)->assertSessionDoesntHaveErrors();

    Mail::assertNothingQueued();
    expect($candidate->refresh()->inviteState())->toBe('invited');
});

it('checks the time, the format and the place', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    invite($requisition, $candidate, ['interview_at' => ''])->assertSessionHasErrors('interview_at');
    invite($requisition, $candidate, ['interview_at' => 'besok'])->assertSessionHasErrors('interview_at');
    invite($requisition, $candidate, ['interview_at' => '2026-11-04T09:00'])->assertSessionHasErrors('interview_at');
    invite($requisition, $candidate, ['interview_at' => '2028-01-01T09:00'])->assertSessionHasErrors('interview_at');
    invite($requisition, $candidate, ['interview_mode' => 'telepati'])->assertSessionHasErrors('interview_mode');
    invite($requisition, $candidate, ['interview_place' => ''])->assertSessionHasErrors('interview_place');

    expect($candidate->refresh()->inviteState())->toBe('none');
});

it('lets only HR invite, and only at Interview or Offer', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    invite($requisition, $candidate, as: $this->requester)->assertForbidden();
    invite($requisition, $candidate, as: $this->management)->assertForbidden();

    foreach ([RequisitionStage::Screening, RequisitionStage::Posting, RequisitionStage::Filled] as $stage) {
        $other = interviewRequisition($stage);
        invite($other, Candidate::factory()->for($other)->create())->assertForbidden();
    }

    $offer = interviewRequisition(RequisitionStage::Offer);
    invite($offer, Candidate::factory()->for($offer)->create())->assertSessionDoesntHaveErrors();
});

it('refuses a candidate that belongs to another requisition', function () {
    $requisition = interviewRequisition();
    $other = interviewRequisition();
    $stranger = Candidate::factory()->for($other)->create();

    invite($requisition, $stranger)->assertNotFound();
});

it('shows HR who is invited and what each answered', function () {
    $requisition = interviewRequisition();
    $invited = Candidate::factory()->for($requisition)->create(['name' => 'Budi']);
    Candidate::factory()->for($requisition)->create(['name' => 'Ani']);
    invite($requisition, $invited);

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.canManageInterview', true)
            ->where('requisition.shortlist.0.inviteState', 'invited')
            ->where('requisition.shortlist.0.interviewAtLabel', 'Selasa, 10 November 2026 · 10:00 WIB')
            ->where('requisition.shortlist.0.interviewAtInput', '2026-11-10T10:00')
            ->where('requisition.shortlist.1.inviteState', 'none')
            ->where('requisition.shortlist.1.form', null));
});

// --- The candidate form ---

/** Invites, and returns the personal link from the email. The candidate is now the one asking: nobody is signed in. */
function sentLink(Requisition $requisition, Candidate $candidate): string
{
    $link = '';

    invite($requisition, $candidate)->assertSessionDoesntHaveErrors();
    Mail::assertQueued(CandidateInvitation::class, function (CandidateInvitation $mail) use (&$link): bool {
        preg_match('#https?://\S+/lamaran/\S{48}#', $mail->body, $found);
        $link = $found[0] ?? $link;

        return true;
    });

    auth()->logout();

    return $link;
}

/** The same, and then it is the morning of the interview day (10 November, 08:00), when the form can be opened. */
function invitedLink(Requisition $requisition, Candidate $candidate): string
{
    $link = sentLink($requisition, $candidate);

    test()->travelTo(CarbonImmutable::parse('2026-11-10 08:00', 'Asia/Jakarta'));

    return $link;
}

it('opens the form only on the day of the interview, and closes it at the end of that day', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = sentLink($requisition, $candidate);
    $answers = ['education_0' => 'yes', 'hard_0' => 'yes', 'soft_0' => 'yes', 'bonus_0' => 'yes'];

    // The invitation goes out on 4 November: the day before, the link says when it opens and takes nothing.
    $this->get($link)->assertInertia(fn (Assert $page) => $page
        ->where('state', 'early')
        ->where('opensOn', 'Selasa, 10 November 2026')
        ->missing('questions'));
    $this->post($link, ['answers' => $answers]);
    expect($candidate->refresh()->inviteState())->toBe('invited');

    $this->travelTo(CarbonImmutable::parse('2026-11-09 23:59', 'Asia/Jakarta'));
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'early'));

    $this->travelTo(CarbonImmutable::parse('2026-11-10 00:01', 'Asia/Jakarta'));
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'open'));

    $this->travelTo(CarbonImmutable::parse('2026-11-10 23:59', 'Asia/Jakarta'));
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'open'));

    $this->travelTo(CarbonImmutable::parse('2026-11-11 00:01', 'Asia/Jakarta'));
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'expired'));
    $this->post($link, ['answers' => $answers]);
    expect($candidate->refresh()->inviteState())->toBe('expired');
});

it('opens the form from the personal link without signing in, showing only what the candidate needs', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi Santoso', 'phone' => '081234567890']);
    $link = invitedLink($requisition, $candidate);

    $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('candidate-form/show')
        ->where('state', 'open')
        ->where('positionName', 'Staf Gudang')
        ->where('companyName', 'PT Maju Jaya')
        ->where('candidateName', 'Budi Santoso')
        ->where('interview.mode', 'Daring')
        ->where('questions.0', ['key' => 'education_0', 'group' => 'Pendidikan', 'text' => 'Minimal SMA/SMK'])
        ->has('questions', 4)
        ->missing('phone')
        ->missing('email'));
});

it('records the answers once, tells HR, and then closes the link', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = invitedLink($requisition, $candidate);

    $this->post($link, [
        'answers' => ['education_0' => 'yes', 'hard_0' => 'yes', 'soft_0' => 'no', 'bonus_0' => 'no'],
        'expected_salary' => 4500000,
        'available_from' => '2026-12-01',
        'message' => 'Siap bergabung.',
    ])->assertRedirect($link)->assertSessionDoesntHaveErrors();

    $candidate->refresh();
    expect($candidate->inviteState())->toBe('submitted')
        ->and($candidate->form_data['expected_salary'])->toBe(4500000)
        ->and($candidate->form_data['answers'])->toHaveCount(4)
        ->and($candidate->form_data['answers'][0])->toBe(['group' => 'Pendidikan', 'text' => 'Minimal SMA/SMK', 'answer' => true])
        ->and($candidate->form_data['answers'][2]['answer'])->toBeFalse();

    expect($this->hr->notifications()->latest()->firstOrFail()->data['title'])->toContain('mengisi formulir');

    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'submitted'));
    $this->post($link, ['answers' => ['education_0' => 'no', 'hard_0' => 'no', 'soft_0' => 'no', 'bonus_0' => 'no']]);
    expect($candidate->refresh()->form_data['answers'][0]['answer'])->toBeTrue();

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.shortlist.0.inviteState', 'submitted')
            ->where('requisition.shortlist.0.form.yes', 2)
            ->where('requisition.shortlist.0.form.expectedSalary', 4500000));
});

it('needs every question answered and checks the details', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = invitedLink($requisition, $candidate);
    $all = ['education_0' => 'yes', 'hard_0' => 'yes', 'soft_0' => 'yes', 'bonus_0' => 'yes'];

    $this->post($link, ['answers' => ['education_0' => 'yes']])->assertSessionHasErrors('answers');
    $this->post($link, ['answers' => [...$all, 'soft_0' => 'mungkin']])->assertSessionHasErrors('answers.soft_0');
    $this->post($link, ['answers' => $all, 'expected_salary' => 'banyak'])->assertSessionHasErrors('expected_salary');
    $this->post($link, ['answers' => $all, 'available_from' => '2026-01-01'])->assertSessionHasErrors('available_from');

    expect($candidate->refresh()->inviteState())->toBe('invited');
});

it('refuses a link that is wrong, expired or replaced by a newer one', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $old = invitedLink($requisition, $candidate);

    $this->get(route('candidate-form.show', str_repeat('a', 48)))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    $this->get(route('candidate-form.show', 'pendek'))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));

    $new = invitedLink($requisition, $candidate);
    expect($new)->not->toBe($old);
    $this->get($old)->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    $this->get($new)->assertInertia(fn (Assert $page) => $page->where('state', 'open'));

    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00', 'Asia/Jakarta'));
    $this->get($new)->assertInertia(fn (Assert $page) => $page->where('state', 'expired')->missing('questions'));
    $this->post($new, ['answers' => ['education_0' => 'yes', 'hard_0' => 'yes', 'soft_0' => 'yes', 'bonus_0' => 'yes']]);
    expect($candidate->refresh()->inviteState())->toBe('expired');
});

it('closes the form once the requisition is cancelled or filled', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = invitedLink($requisition, $candidate);

    $requisition->workflow->update(['current_stage' => RequisitionStage::Cancelled->value]);

    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'closed'));
});

it('lets HR change the time of an invitation whose form is already filled, without asking again', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = invitedLink($requisition, $candidate);
    $this->post($link, ['answers' => ['education_0' => 'yes', 'hard_0' => 'yes', 'soft_0' => 'yes', 'bonus_0' => 'yes']]);

    invite($requisition, $candidate, ['interview_at' => '2026-11-11T14:30'])->assertSessionDoesntHaveErrors();

    $candidate->refresh();
    expect($candidate->inviteState())->toBe('submitted')
        ->and($candidate->interview_at->toDateTimeString())->toBe('2026-11-11 07:30:00');

    Mail::assertQueued(CandidateInvitation::class, fn (CandidateInvitation $mail): bool => str_contains($mail->body, '14:30 WIB') && ! str_contains($mail->body, '/lamaran/'));
});

it('serves the form on the public entrance, with a limit per address', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();
    $link = invitedLink($requisition, $candidate);

    $this->withServerVariables(['ENTRANCE' => 'public'])->get($link)->assertOk();

    foreach (range(1, 25) as $attempt) {
        $response = $this->withServerVariables(['ENTRANCE' => 'public'])->get(route('candidate-form.show', str_repeat('b', 48)));
    }

    $response->assertStatus(429);
});

it('builds the candidate link on the public address even when HR is on the VPN one', function () {
    config(['app.public_url' => 'https://hr.example.com/']);
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    $this->withServerVariables(['HTTP_HOST' => 'hr-vpn.example.com']);
    invite($requisition, $candidate)->assertSessionDoesntHaveErrors();

    Mail::assertQueued(CandidateInvitation::class, fn (CandidateInvitation $mail): bool => str_contains($mail->body, 'https://hr.example.com/lamaran/')
        && ! str_contains($mail->body, 'hr-vpn'));
});

it('falls back to the address of the request when no public address is set', function () {
    config(['app.public_url' => null]);
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    invite($requisition, $candidate)->assertSessionDoesntHaveErrors();

    Mail::assertQueued(CandidateInvitation::class, fn (CandidateInvitation $mail): bool => (bool) preg_match('#https?://[^/\s]+/lamaran/\S{48}#', $mail->body));
});

// --- Cancelling a candidate ---

it('lets HR cancel an invited candidate at the interview stage, and their form link stops working', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['email' => 'budi@example.com']);
    $link = null;

    invite($requisition, $candidate);
    Mail::assertQueued(CandidateInvitation::class, function (CandidateInvitation $mail) use (&$link): bool {
        preg_match('#/lamaran/(\S{48})#', $mail->body, $found);
        $link = $found[1] ?? null;

        return true;
    });

    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertRedirect();

    expect(Candidate::query()->whereKey($candidate->id)->exists())->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00', 'Asia/Jakarta'));
    $this->get(route('candidate-form.show', $link))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
});

it('does not let anyone but HR cancel a candidate at the interview stage', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    $this->actingAs($this->requester)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertForbidden();
    $this->actingAs($this->management)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertForbidden();

    expect(Candidate::query()->whereKey($candidate->id)->exists())->toBeTrue();
});

// --- The mail-merge file ---

/**
 * @return list<array<int, string|null>>
 */
function csvRows(string $content): array
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, ltrim($content, "\xEF\xBB\xBF"));
    rewind($stream);

    $rows = [];

    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }

    return $rows;
}

it('gives HR a file with one message per invited candidate, to send from their own mailbox', function () {
    $requisition = interviewRequisition();
    $budi = Candidate::factory()->for($requisition)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    $ani = Candidate::factory()->for($requisition)->create(['name' => 'Ani Lestari', 'email' => 'ani@example.com']);
    Candidate::factory()->for($requisition)->create(['name' => 'Belum Diundang', 'email' => 'belum@example.com']);
    $noEmail = Candidate::factory()->for($requisition)->create(['name' => 'Tanpa Email', 'email' => null]);

    invite($requisition, $budi);
    invite($requisition, $ani, ['interview_at' => '2026-11-11T14:30', 'interview_mode' => 'onsite', 'interview_place' => 'Kantor pusat']);
    invite($requisition, $noEmail);

    $response = $this->actingAs($this->hr)->get(route('recruitment.candidates.export', $requisition))->assertOk();
    $rows = csvRows($response->streamedContent());

    expect($response->headers->get('content-disposition'))->toContain($requisition->reference())
        ->and($rows[0])->toBe(['Nama', 'Email', 'Posisi', 'Hari dan tanggal', 'Jam (WIB)', 'Format', 'Tempat atau tautan', 'Subjek', 'Pesan', 'Sudah diemail aplikasi'])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][0])->toBe('Budi Santoso')
        ->and($rows[1][1])->toBe('budi@example.com')
        ->and($rows[1][3])->toBe('Selasa, 10 November 2026')
        ->and($rows[1][4])->toBe('10:00')
        ->and($rows[1][5])->toBe('Daring')
        ->and($rows[1][7])->toContain('Staf Gudang')
        ->and($rows[1][8])->toContain('Budi Santoso')->toContain('https://meet.google.com/abc-defg-hij')
        ->and($rows[1][9])->toBe('Ya')
        ->and($rows[2][0])->toBe('Ani Lestari')
        ->and($rows[2][5])->toBe('Tatap muka');
});

it('leaves the personal form link out of the file, since it cannot be shown again', function () {
    $requisition = interviewRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['email' => 'budi@example.com']);
    invite($requisition, $candidate);

    $content = $this->actingAs($this->hr)->get(route('recruitment.candidates.export', $requisition))->streamedContent();

    expect($content)->not->toContain('/lamaran/');
});

it('gives the file to HR only', function () {
    $requisition = interviewRequisition();

    $this->actingAs($this->requester)->get(route('recruitment.candidates.export', $requisition))->assertForbidden();
    $this->actingAs($this->management)->get(route('recruitment.candidates.export', $requisition))->assertForbidden();
});
