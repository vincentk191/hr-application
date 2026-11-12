<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Mail\CandidateInvitation;
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
use Spatie\Activitylog\Models\Activity;

/**
 * An interview as a calendar event (REC-INT): one candidate's `.ics`, for HR and for the direct superior, and the candidate's
 * own copy attached to the invitation email. An hour long unless HR says otherwise.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta; the interview is on the 10th at 10:00 WIB, which is 03:00 UTC.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = icsUser(Role::HrAdmin, $this->company, 'Hana HR');
    $this->management = icsUser(Role::Management, $this->company, 'Mira Manajemen');
    $this->requester = icsUser(Role::Supervisor, $this->company, 'Rudi Pemohon');
    $this->boss = icsUser(Role::Supervisor, $this->company, 'Bu Atasan');
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id, 'full_name' => 'Bu Atasan']);
});

function icsUser(Role $role, Company $company, string $name): User
{
    $user = User::factory()->create(['company_id' => $company->id, 'name' => $name]);
    $user->assignRole($role->value);

    return $user;
}

function icsRequisition(): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'manager_employee_id' => test()->bossEmployee->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => RequisitionStage::Interview->value]);

    return $requisition->refresh();
}

/**
 * @param  array<string, mixed>  $fields
 */
function icsInvite(Requisition $requisition, Candidate $candidate, array $fields = [])
{
    return test()->actingAs(test()->hr)->post(route('recruitment.candidates.invite', [$requisition, $candidate]), [
        'interview_at' => '2026-11-10T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
        ...$fields,
    ]);
}

function icsFile(Requisition $requisition, Candidate $candidate, ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->get(route('recruitment.candidates.calendar', [$requisition, $candidate]));
}

/** What a calendar app reads: a long line is folded onto continuation lines that start with a space, joined back here. */
function icsUnfold(string $ics): string
{
    return str_replace("\r\n ", '', $ics);
}

it('gives HR an online interview as an event an hour long, with the Meet link in the description and the participants', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    icsInvite($requisition, $candidate, ['note' => 'Mohon hadir 10 menit lebih awal.'])->assertSessionDoesntHaveErrors();

    $response = icsFile($requisition, $candidate)->assertOk();
    $ics = icsUnfold($response->getContent());

    expect($response->headers->get('Content-Type'))->toStartWith('text/calendar')
        ->and($response->headers->get('Content-Disposition'))->toContain('interview-budi-santoso-2026-11-10.ics')
        ->and($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($ics)->toContain("DTSTART:20261110T030000Z\r\n")
        // An hour unless HR says otherwise.
        ->and($ics)->toContain("DTEND:20261110T040000Z\r\n")
        ->and($ics)->toContain('SUMMARY:Interview: Budi Santoso — Staf Gudang')
        ->and($ics)->toContain('https://meet.google.com/abc-defg-hij')
        ->and($ics)->toContain('Mohon hadir 10 menit lebih awal.')
        ->and($ics)->toContain("URL:https://meet.google.com/abc-defg-hij\r\n")
        // Online: nothing to travel to.
        ->and($ics)->not->toContain('LOCATION:')
        ->and($ics)->toContain('ORGANIZER;CN="Hana HR":mailto:'.$this->hr->email)
        ->and($ics)->toContain('mailto:budi@example.com')
        ->and($ics)->toContain('mailto:'.$this->boss->email)
        ->and($ics)->toContain("UID:interview-{$candidate->id}@")
        ->and(Activity::query()->where('description', 'Jadwal interview diunduh')->count())->toBe(1);
});

it('puts the address of an in-person interview in the location, for the length HR chose', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Ani', 'email' => null]);
    icsInvite($requisition, $candidate, ['interview_mode' => 'onsite', 'interview_place' => 'Kantor Pusat, Lantai 3; Ruang Meeting', 'interview_minutes' => 45])->assertSessionDoesntHaveErrors();

    $ics = icsUnfold(icsFile($requisition, $candidate)->assertOk()->getContent());

    expect($ics)->toContain('LOCATION:Kantor Pusat\, Lantai 3\; Ruang Meeting')
        ->and($ics)->toContain('Alamat: Kantor Pusat\, Lantai 3\; Ruang Meeting')
        ->and($ics)->not->toContain('URL:')
        ->and($ics)->toContain("DTEND:20261110T034500Z\r\n")
        // No email, so the candidate is not a participant; the superior still is.
        ->and($ics)->not->toContain('mailto:ani')
        ->and($ics)->toContain('mailto:'.$this->boss->email);
});

it('keeps lines within 75 bytes, folded onto continuation lines', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    icsInvite($requisition, $candidate, ['note' => str_repeat('Catatan yang cukup panjang ', 12)])->assertSessionDoesntHaveErrors();

    $lines = explode("\r\n", icsFile($requisition, $candidate)->getContent());

    expect(collect($lines)->every(fn (string $line): bool => strlen($line) <= 75))->toBeTrue()
        ->and(collect($lines)->contains(fn (string $line): bool => str_starts_with($line, ' ')))->toBeTrue()
        // Joined back, the note is whole again.
        ->and(icsUnfold(implode("\r\n", $lines)))->toContain(rtrim(str_repeat('Catatan yang cukup panjang ', 12)));
});

it('stores the duration, keeps it when the invitation is sent again without one, and refuses nonsense', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create();

    expect($candidate->refresh()->interview_minutes)->toBe(60);

    icsInvite($requisition, $candidate, ['interview_minutes' => 4])->assertSessionHasErrors('interview_minutes');
    icsInvite($requisition, $candidate, ['interview_minutes' => 481])->assertSessionHasErrors('interview_minutes');
    icsInvite($requisition, $candidate, ['interview_minutes' => 'lama'])->assertSessionHasErrors('interview_minutes');

    icsInvite($requisition, $candidate, ['interview_minutes' => 30])->assertSessionDoesntHaveErrors();
    expect($candidate->refresh()->interview_minutes)->toBe(30);

    icsInvite($requisition, $candidate, ['interview_at' => '2026-11-11T09:00'])->assertSessionDoesntHaveErrors();
    expect($candidate->refresh()->interview_minutes)->toBe(30);
});

it('gives the direct superior the event without the candidate\'s email', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    icsInvite($requisition, $candidate);

    $ics = icsUnfold(icsFile($requisition, $candidate, $this->boss)->assertOk()->getContent());

    expect($ics)->toContain('SUMMARY:Interview: Budi — Staf Gudang')
        ->and($ics)->toContain('ORGANIZER;CN="Hana HR"')
        ->and($ics)->toContain('mailto:'.$this->boss->email)
        ->and($ics)->not->toContain('budi@example.com');
});

it('is for HR and the direct superior only, for someone scheduled and still in the process', function () {
    $requisition = icsRequisition();
    $scheduled = Candidate::factory()->for($requisition)->create();
    $unscheduled = Candidate::factory()->for($requisition)->create();
    $turnedDown = Candidate::factory()->for($requisition)->create();
    icsInvite($requisition, $scheduled);
    icsInvite($requisition, $turnedDown);
    $turnedDown->forceFill(['rejected_at' => now(), 'rejected_reason' => 'Tidak cocok', 'rejected_by_user_id' => $this->hr->id])->save();

    icsFile($requisition, $scheduled, $this->management)->assertForbidden();
    icsFile($requisition, $scheduled, $this->requester)->assertForbidden();
    icsFile($requisition, $unscheduled)->assertForbidden();
    icsFile($requisition, $turnedDown)->assertForbidden();
    icsFile($requisition, $scheduled)->assertOk();
    icsFile($requisition, $scheduled, $this->boss)->assertOk();

    $props = fn (User $user) => collect($this->actingAs($user)->get(route('recruitment.show', $requisition))->viewData('page')['props']['requisition']['shortlist'])->keyBy('id');

    expect($props($this->hr)[$scheduled->id])->toMatchArray(['canExportEvent' => true, 'interviewMinutes' => 60])
        ->and($props($this->hr)[$unscheduled->id]['canExportEvent'])->toBeFalse();
});

it('attaches the candidate\'s own copy of the event to the invitation email, without the other participants', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);

    icsInvite($requisition, $candidate, ['interview_mode' => 'onsite', 'interview_place' => 'Kantor Pusat', 'interview_minutes' => 20]);

    Mail::assertQueued(CandidateInvitation::class, function (CandidateInvitation $mail): bool {
        $ics = icsUnfold((string) $mail->calendar);
        $attachment = collect($mail->attachments())->first();

        return $attachment !== null
            && str_contains($ics, 'SUMMARY:Interview Staf Gudang — PT Maju Jaya')
            && str_contains($ics, 'LOCATION:Kantor Pusat')
            && str_contains($ics, "DTEND:20261110T032000Z\r\n")
            && str_contains($ics, 'mailto:budi@example.com')
            && str_contains($ics, 'ORGANIZER;CN="Hana HR"')
            // The candidate's copy does not carry the interviewers' details or the candidate's full name as the title.
            && ! str_contains($ics, $this->boss->email)
            && ! str_contains($ics, 'Interview: Budi');
    });
});

it('sends a changed time as the same event with a higher version, so it updates instead of duplicating', function () {
    $requisition = icsRequisition();
    $candidate = Candidate::factory()->for($requisition)->create(['email' => 'budi@example.com']);

    icsInvite($requisition, $candidate);
    $first = icsUnfold(icsFile($requisition, $candidate)->getContent());

    $this->travel(2)->hours();
    icsInvite($requisition, $candidate, ['interview_at' => '2026-11-11T14:00']);
    $second = icsUnfold(icsFile($requisition, $candidate)->getContent());

    $field = fn (string $ics, string $name): string => collect(explode("\r\n", $ics))->first(fn (string $line): bool => str_starts_with($line, $name.':'));

    expect($field($second, 'UID'))->toBe($field($first, 'UID'))
        ->and((int) substr($field($second, 'SEQUENCE'), 9))->toBeGreaterThan((int) substr($field($first, 'SEQUENCE'), 9))
        ->and($field($second, 'DTSTART'))->toBe('DTSTART:20261111T070000Z');
});
