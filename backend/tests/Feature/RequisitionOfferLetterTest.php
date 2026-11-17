<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use App\Enums\OfferStage;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Jobs\DeliverEmail;
use App\Mail\CompanyEmail;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\CandidateOffer;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Models\Department;
use App\Models\EmailMessage;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Email\CompanyMailer;
use App\Support\Recruitment\OfferLetterTemplate;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
    Storage::fake('local');
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

/**
 * A second candidate on the same requisition, chosen and put to Management; approved at Rp 4.000.000 when asked.
 */
function secondLetterOffer(Requisition $requisition, bool $approve = true): CandidateOffer
{
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Ani', 'email' => 'ani@example.com']);
    $candidate->forceFill(['selected_at' => now(), 'selected_by_user_id' => test()->boss->id])->save();

    test()->actingAs(test()->hr)->post(route('recruitment.offers.store', $requisition), ['candidate_ids' => [$candidate->id]]);
    $offer = CandidateOffer::query()->where('candidate_id', $candidate->id)->sole();

    if ($approve) {
        test()->actingAs(test()->management)->put(route('recruitment.offers.decide', [$requisition, $offer]), ['decision' => 'approve', 'salary' => 4000000]);
    }

    return $offer->refresh();
}

it('gives one set of terms to several approved offers, each keeping its own letter number', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $other = secondLetterOffer($requisition);
    $other->forceFill(['letter_number' => '002/HR/XII'])->save();

    setLetterTerms($requisition, $offer, [
        'start_on' => '2026-12-01',
        'probation_months' => 3,
        'letter_number' => '001/HR/XII',
        'letter_note' => 'Mulai di gudang pusat.',
        'also_for' => [$other->id],
    ])->assertSessionDoesntHaveErrors();

    $other->refresh();

    expect($other->start_on->toDateString())->toBe('2026-12-01')
        ->and($other->probation_months)->toBe(3)
        ->and($other->letter_note)->toBe('Mulai di gudang pusat.')
        ->and($other->letter_number)->toBe('002/HR/XII')
        ->and($other->salary)->toBe(4000000)
        ->and($offer->refresh()->letter_number)->toBe('001/HR/XII');
});

it('refuses terms for an offer that is not approved, and changes nothing', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $waiting = secondLetterOffer($requisition, approve: false);

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3, 'also_for' => [$waiting->id]])
        ->assertSessionHasErrors('also_for');

    expect($offer->refresh()->hasTerms())->toBeFalse()->and($waiting->refresh()->hasTerms())->toBeFalse();
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

/**
 * The letter the print page is given, for HR.
 *
 * @return array<string, mixed>
 */
function printedLetter(Requisition $requisition, CandidateOffer $offer): array
{
    return test()->actingAs(test()->hr)->get(route('recruitment.offers.letter', [$requisition, $offer]))->assertOk()->viewData('page')['props']['letter'];
}

/**
 * Saves wording for the letter and the email, leaving whatever is not named as it is.
 *
 * @param  array<string, mixed>  $wording
 */
function saveLetterWording(array $wording): void
{
    $template = app(OfferLetterTemplate::class);

    $template->save([...$template->current(), ...$wording]);
}

it('prints the built-in wording until HR changes the template', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    $letter = printedLetter($requisition, $offer);

    expect($letter)->toMatchArray([
        'title' => 'Surat penawaran kerja',
        'subject' => 'Penawaran kerja — Staf Gudang',
        'number' => null,
        'note' => null,
        'logoUrl' => null,
        'signer' => ['name' => null, 'title' => 'HR'],
        'sent' => false,
    ])
        ->and($letter['opening'])->toHaveCount(2)
        ->and($letter['opening'][0])->toBe('Dengan hormat,')
        ->and($letter['opening'][1])->toContain('PT Maju Jaya')->toContain('sebagai Staf Gudang')
        ->and($letter['closing'])->toHaveCount(2)
        ->and($letter['closing'][1])->toBe('Kami berharap Anda dapat bergabung dan berkembang bersama PT Maju Jaya.');
});

it('prints the wording, the signer, the number and the note HR set, and always the terms', function () {
    $this->company->forceFill(['logo_path' => 'logos/maju.png'])->save();
    saveLetterWording([
        'title' => 'Surat tawaran',
        'subject' => 'Tawaran {posisi} di {cabang}{departemen}',
        'opening' => "Yth. {nama},\n\nKami menawarkan {posisi} mulai {tanggal_mulai}, gaji {gaji}.",
        'closing' => 'Salam dari {perusahaan}.',
        'signers' => [$this->company->id => ['name' => 'Ratna Dewi', 'title' => 'Kepala HR']],
    ]);
    [$requisition, $offer] = approvedLetterOffer();

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3, 'letter_number' => '  012/HR/XI/2026 ', 'letter_note' => "Tunjangan makan Rp 500.000.\nDiberikan setelah masa percobaan."])
        ->assertSessionDoesntHaveErrors();

    $letter = printedLetter($requisition, $offer);

    expect($letter)->toMatchArray([
        'title' => 'Surat tawaran',
        'subject' => 'Tawaran Staf Gudang di Gudang',
        'opening' => ['Yth. Budi,', 'Kami menawarkan Staf Gudang mulai 1 Desember 2026, gaji Rp 4.500.000.'],
        'closing' => ['Salam dari PT Maju Jaya.'],
        'number' => '012/HR/XI/2026',
        'note' => "Tunjangan makan Rp 500.000.\nDiberikan setelah masa percobaan.",
        'signer' => ['name' => 'Ratna Dewi', 'title' => 'Kepala HR'],
        'startOn' => '1 Desember 2026',
        'probation' => '3 bulan',
        'salary' => 'Rp 4.500.000',
    ])->and($letter['logoUrl'])->toEndWith('logos/maju.png');

    // The Penawaran tab gives the dialog what HR typed, to change it.
    $row = collect($this->actingAs($this->hr)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['offers'])->sole();
    expect($row)->toMatchArray(['letterNumber' => '012/HR/XI/2026', 'letterNote' => "Tunjangan makan Rp 500.000.\nDiberikan setelah masa percobaan."]);

    // Emptied again, they are gone from the letter.
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3, 'letter_number' => '', 'letter_note' => null]);

    expect(printedLetter($requisition, $offer))->toMatchArray(['number' => null, 'note' => null]);
});

it('keeps the letter number and note short', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $terms = ['start_on' => '2026-12-01', 'probation_months' => 3];

    setLetterTerms($requisition, $offer, [...$terms, 'letter_number' => str_repeat('1', 61)])->assertSessionHasErrors('letter_number');
    setLetterTerms($requisition, $offer, [...$terms, 'letter_note' => str_repeat('a', 1001)])->assertSessionHasErrors('letter_note');
    setLetterTerms($requisition, $offer, [...$terms, 'letter_number' => str_repeat('1', 60), 'letter_note' => str_repeat('a', 1000)])->assertSessionDoesntHaveErrors();
});

it('follows the template until the offer is sent, and then keeps the letter as it was sent', function () {
    saveLetterWording(['opening' => 'Pembuka pertama untuk {nama}.']);
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3, 'letter_number' => '012/HR']);

    expect(printedLetter($requisition, $offer)['opening'])->toBe(['Pembuka pertama untuk Budi.']);

    saveLetterWording(['opening' => 'Pembuka kedua untuk {nama}.']);
    expect(printedLetter($requisition, $offer)['opening'])->toBe(['Pembuka kedua untuk Budi.']);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));

    saveLetterWording(['title' => 'Judul baru', 'opening' => 'Pembuka ketiga.', 'signers' => [$this->company->id => ['name' => 'Ratna Dewi', 'title' => 'Kepala HR']]]);
    $this->company->forceFill(['name' => 'PT Maju Sentosa'])->save();

    $letter = printedLetter($requisition, $offer->refresh());

    expect($letter)->toMatchArray([
        'company' => 'PT Maju Jaya',
        'title' => 'Surat penawaran kerja',
        'opening' => ['Pembuka kedua untuk Budi.'],
        'number' => '012/HR',
        'date' => '4 November 2026',
        'signer' => ['name' => null, 'title' => 'HR'],
        'sent' => true,
    ])->and($offer->letter_snapshot)->not->toHaveKey('logoUrl');
});

/** The company's mailbox, switched on: without it an offer's email cannot be sent. */
function mailboxReady(): CompanyMailSetting
{
    return CompanyMailSetting::factory()->forCompany(test()->company)->create(['from_address' => 'recruitment@majujaya.test']);
}

/** The offer as the Penawaran tab hands it to HR. */
function offerProps(Requisition $requisition): array
{
    return collect(test()->actingAs(test()->hr)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['offers'])->sole();
}

it('drafts the email from the wording HR set, and sends the text HR left in it with the letter as a PDF', function () {
    Queue::fake();
    saveLetterWording([
        'email_subject' => 'Tawaran {posisi} dari {perusahaan}',
        'email_body' => "Yth. {nama},\n\n{ketentuan}\n\nSalam, {perusahaan}",
    ]);
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 0]);
    mailboxReady();

    $draft = offerProps($requisition)['emailDraft'];

    expect($draft['to'])->toBe('budi@example.com')
        ->and($draft['subject'])->toBe('Tawaran Staf Gudang dari PT Maju Jaya')
        ->and($draft['body'])->toStartWith('Yth. Budi,')
        ->and($draft['body'])->toContain("Posisi            : Staf Gudang\n")->toContain('Gaji pokok/bulan  : Rp 4.500.000')->toEndWith('Salam, PT Maju Jaya');

    // HR edits the draft before pressing Kirim: what they left in it is what goes.
    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), [
        'notify_candidate' => '1', 'email_subject' => 'Tawaran kerja untuk Budi', 'email_body' => "Halo Budi,\n\nSurat terlampir.",
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $offer->refresh();
    $message = EmailMessage::acrossCompanies()->sole();

    expect($message->kind)->toBe(EmailKind::OfferSent)
        ->and($message->to_email)->toBe('budi@example.com')
        ->and($message->subject)->toBe('Tawaran kerja untuk Budi')
        ->and($message->body)->toBe("Halo Budi,\n\nSurat terlampir.")
        ->and($message->status)->toBe(EmailStatus::Pending)
        ->and($message->related_id)->toBe($offer->id)
        ->and($message->attachments)->toBe([['name' => 'Surat penawaran Budi.pdf', 'mime' => 'application/pdf', 'path' => $offer->letter_pdf_path]])
        ->and($offer->email_message_id)->toBe($message->id)
        ->and($offer->emailed_at)->not->toBeNull();

    // The attachment is a real PDF, kept on the private disk, made from the letter as it was sent.
    expect(Storage::disk('local')->get($offer->letter_pdf_path))->toStartWith('%PDF');
    Queue::assertPushed(DeliverEmail::class, fn (DeliverEmail $job): bool => $job->messageId === $message->id);
});

it('delivers the offer email from the company address with the PDF attached', function () {
    Queue::fake();
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    mailboxReady();

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), [
        'notify_candidate' => '1', 'email_subject' => 'Penawaran kerja', 'email_body' => 'Halo Budi',
    ])->assertSessionDoesntHaveErrors();

    app(CompanyMailer::class)->deliver(EmailMessage::acrossCompanies()->sole());

    Mail::assertSent(CompanyEmail::class, function (CompanyEmail $mail): bool {
        $attachments = $mail->attachments();

        return $mail->hasTo('budi@example.com')
            && $mail->envelope()->from->address === 'recruitment@majujaya.test'
            && count($attachments) === 1
            && $mail->body === 'Halo Budi';
    });
    expect(EmailMessage::acrossCompanies()->sole()->status)->toBe(EmailStatus::Sent);
});

it('does not put the salary in the email unless the wording HR set says so', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    mailboxReady();

    $draft = offerProps($requisition)['emailDraft'];

    expect($draft['body'])->toContain('Budi')->toContain('terlampir')->not->toContain('Rp 4.500.000')->not->toContain('Gaji');
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

    expect(EmailMessage::acrossCompanies()->count())->toBe(0);
});

it('refuses to send the email while the company mailbox is not switched on, and changes nothing', function () {
    Queue::fake();
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    expect(offerProps($requisition)['mailReady'])->toBeFalse();

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), [
        'notify_candidate' => '1', 'email_subject' => 'Penawaran', 'email_body' => 'Halo',
    ])->assertSessionHasErrors('notify_candidate');

    expect($offer->refresh()->stage())->toBe(OfferStage::Approved)
        ->and($offer->letter_pdf_path)->toBeNull()
        ->and(EmailMessage::acrossCompanies()->count())->toBe(0);
    Queue::assertNotPushed(DeliverEmail::class);

    // Marking it sent without the email is still open to HR, who then sends the letter themselves.
    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]))->assertSessionDoesntHaveErrors();
    expect($offer->refresh()->stage())->toBe(OfferStage::Sent)->and($offer->letter_pdf_path)->not->toBeNull();
});

it('needs a subject and a text for the email HR sends', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    mailboxReady();

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), ['notify_candidate' => '1', 'email_subject' => '', 'email_body' => ''])
        ->assertSessionHasErrors(['email_subject', 'email_body']);

    expect($offer->refresh()->stage())->toBe(OfferStage::Approved);
});

it('sends no email to a candidate without an address, even when ticked', function () {
    [$requisition, $offer] = approvedLetterOffer(email: null);
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 0]);

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), ['notify_candidate' => '1'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditandai terkirim.']);

    expect(EmailMessage::acrossCompanies()->count())->toBe(0);
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
    Queue::fake();
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));
    $withdraw = route('recruitment.offers.withdraw', [$requisition, $offer]);

    // Without the company's mailbox the tick cannot be honoured, and the offer stays out.
    $this->actingAs($this->hr)->delete($withdraw, ['note' => 'Anggaran dipotong', 'notify_candidate' => '1'])->assertSessionHasErrors('notify_candidate');
    expect($offer->refresh()->stage())->toBe(OfferStage::Sent);
    mailboxReady();

    $this->actingAs($this->management)->delete($withdraw, ['note' => 'Tidak jadi'])->assertForbidden();
    $this->actingAs($this->hr)->delete($withdraw)->assertSessionHasErrors('note');
    expect($offer->refresh()->stage())->toBe(OfferStage::Sent);

    $this->actingAs($this->hr)->delete($withdraw, ['note' => 'Anggaran dipotong', 'notify_candidate' => '1'])
        ->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Penawaran Budi ditarik dan kandidat dikabari lewat email.']);

    expect($offer->refresh()->stage())->toBe(OfferStage::Withdrawn)
        ->and(Activity::query()->where('description', 'Penawaran ditarik')->latest('id')->first()->properties['reason'])->toBe('Anggaran dipotong');

    $withdrawn = EmailMessage::acrossCompanies()->where('kind', EmailKind::OfferWithdrawn->value)->sole();
    expect($withdrawn->to_email)->toBe('budi@example.com')->and($withdrawn->body)->not->toContain('Anggaran dipotong');

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

// --- The letter as a PDF, and the signed letter that comes back ---

it('opens the letter as a PDF for HR: as it reads now before sending, and as it was sent afterwards, whatever the template says later', function () {
    [$requisition, $offer] = approvedLetterOffer();
    $pdf = route('recruitment.offers.letter-pdf', [$requisition, $offer]);

    // Not before the terms are set.
    $this->actingAs($this->hr)->get($pdf)->assertForbidden();

    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);

    $before = $this->actingAs($this->hr)->get($pdf)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($before->getContent())->toStartWith('%PDF');

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]))->assertSessionDoesntHaveErrors();
    $sent = $this->actingAs($this->hr)->get($pdf)->assertOk()->getContent();

    // The kept file is what the candidate holds: a new template does not rewrite it.
    saveLetterWording(['title' => 'Judul yang sama sekali baru']);

    expect($this->actingAs($this->hr)->get($pdf)->getContent())->toBe($sent)
        ->and(Storage::disk('local')->get($offer->refresh()->letter_pdf_path))->toBe($sent);

    // Only HR opens it.
    $this->actingAs($this->management)->get($pdf)->assertForbidden();
    $this->actingAs($this->boss)->get($pdf)->assertForbidden();
});

it('files the letter the candidate signed, for HR only, once the offer is with the candidate, and lets HR replace or remove it', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    $file = fn (string $name = 'surat-ttd.pdf') => UploadedFile::fake()->create($name, 200, 'application/pdf');
    $store = route('recruitment.offers.signed-letter.store', [$requisition, $offer]);

    // Not before it is sent.
    $this->actingAs($this->hr)->post($store, ['signed_letter' => $file()])->assertForbidden();

    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));

    $this->actingAs($this->management)->post($store, ['signed_letter' => $file()])->assertForbidden();
    $this->actingAs($this->hr)->post($store, ['signed_letter' => $file()])->assertRedirect()->assertSessionDoesntHaveErrors();

    $offer->refresh();
    $first = $offer->signed_letter_path;

    expect($offer->hasSignedLetter())->toBeTrue()
        ->and($offer->signed_letter_name)->toBe('surat-ttd.pdf')
        ->and($offer->signed_letter_by_user_id)->toBe($this->hr->id)
        ->and(Storage::disk('local')->exists($first))->toBeTrue()
        ->and(offerProps($requisition)['signedLetter'])->toMatchArray(['name' => 'surat-ttd.pdf', 'by' => $this->hr->name]);

    // HR opens it; nobody else does.
    $this->actingAs($this->hr)->get(route('recruitment.offers.signed-letter', [$requisition, $offer]))->assertOk();
    $this->actingAs($this->management)->get(route('recruitment.offers.signed-letter', [$requisition, $offer]))->assertForbidden();

    // A second file replaces the first, which is removed from the disk.
    $this->actingAs($this->hr)->post($store, ['signed_letter' => $file('pengganti.pdf')])->assertSessionDoesntHaveErrors();
    expect(Storage::disk('local')->exists($first))->toBeFalse()->and($offer->refresh()->signed_letter_name)->toBe('pengganti.pdf');

    $second = $offer->refresh()->signed_letter_path;

    $this->actingAs($this->hr)->delete(route('recruitment.offers.signed-letter.destroy', [$requisition, $offer]))->assertRedirect();
    expect($offer->refresh()->hasSignedLetter())->toBeFalse()->and(Storage::disk('local')->exists($second))->toBeFalse();
});

it('takes only a PDF or a picture of the signed letter, up to 5 MB', function () {
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));
    $store = route('recruitment.offers.signed-letter.store', [$requisition, $offer]);

    $this->actingAs($this->hr)->post($store, [])->assertSessionHasErrors('signed_letter');
    $this->actingAs($this->hr)->post($store, ['signed_letter' => UploadedFile::fake()->create('surat.docx', 10)])->assertSessionHasErrors('signed_letter');
    $this->actingAs($this->hr)->post($store, ['signed_letter' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('signed_letter');
    $this->actingAs($this->hr)->post($store, ['signed_letter' => UploadedFile::fake()->image('foto.jpg')])->assertSessionDoesntHaveErrors();
});

it('shows HR how the offer email is doing, and lets them send it again when it failed', function () {
    Queue::fake();
    [$requisition, $offer] = approvedLetterOffer();
    setLetterTerms($requisition, $offer, ['start_on' => '2026-12-01', 'probation_months' => 3]);
    mailboxReady();
    $this->actingAs($this->hr)->post(route('recruitment.offers.send', [$requisition, $offer]), ['notify_candidate' => '1', 'email_subject' => 'Penawaran', 'email_body' => 'Halo']);
    $message = EmailMessage::acrossCompanies()->sole();
    $resend = route('recruitment.offers.email.resend', [$requisition, $offer]);

    expect(offerProps($requisition)['email'])->toMatchArray(['status' => 'pending', 'label' => 'Menunggu'])
        ->and(offerProps($requisition)['canResendEmail'])->toBeFalse();
    $this->actingAs($this->hr)->post($resend)->assertForbidden();

    $message->forceFill(['status' => EmailStatus::Failed, 'error' => 'Authentication failed'])->save();

    expect(offerProps($requisition)['email'])->toMatchArray(['status' => 'failed', 'error' => 'Authentication failed'])
        ->and(offerProps($requisition)['canResendEmail'])->toBeTrue();

    $this->actingAs($this->management)->post($resend)->assertForbidden();
    $this->actingAs($this->hr)->post($resend)->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($message->refresh()->status)->toBe(EmailStatus::Pending)->and($offer->refresh()->stage())->toBe(OfferStage::Sent);
});
