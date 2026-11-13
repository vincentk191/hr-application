<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
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
use Illuminate\Support\Facades\Storage;

/**
 * Who sits in on an interview (REC-INT): HR chooses, per candidate, from the HR admins and the Manajemen users of the
 * company, and the direct superior is always there. Those chosen are told, are on the calendar event, are chased for the
 * evaluation, and a Manajemen user among them sees that candidate, without contact details, and may evaluate.
 *
 * "Now" is 2026-11-10 12:00 in Jakarta.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = participantUser(Role::HrAdmin, $this->company, 'Hana HR');
    $this->otherHr = participantUser(Role::HrAdmin, $this->company, 'Hari HR');
    $this->mira = participantUser(Role::Management, $this->company, 'Mira Manajemen');
    $this->otherManagement = participantUser(Role::Management, $this->company, 'Maman Manajemen');
    $this->requester = participantUser(Role::Supervisor, $this->company, 'Rudi Pemohon');
    $this->boss = participantUser(Role::Supervisor, $this->company, 'Bu Atasan');
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id, 'full_name' => 'Bu Atasan']);

    $this->requisition = Requisition::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'created_by_user_id' => $this->requester->id,
        'manager_employee_id' => $this->bossEmployee->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($this->requisition, new RequisitionWorkflow, [], $this->requester)
        ->update(['current_stage' => RequisitionStage::Interview->value]);
});

function participantUser(Role $role, Company $company, string $name): User
{
    $user = User::factory()->create(['company_id' => $company->id, 'name' => $name]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  list<int>  $participants
 * @param  array<string, mixed>  $fields
 */
function participantInvite(Candidate $candidate, array $participants, array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.candidates.invite', [test()->requisition, $candidate]), [
        'interview_at' => '2026-11-12T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'participants' => $participants,
        ...$fields,
    ]);
}

/**
 * A candidate already invited, with the given people chosen. Their interview was at 10:00 today unless said otherwise.
 *
 * @param  list<User>  $chosen
 * @param  array<string, mixed>  $overrides
 */
function participantCandidate(string $name, array $chosen, array $overrides = []): Candidate
{
    $candidate = Candidate::factory()->for(test()->requisition)->create([
        'name' => $name,
        'phone' => '081200000001',
        'note' => 'Catatan HR',
        'invited_at' => now(),
        'invite_expires_at' => now()->addDay(),
        'interview_at' => '2026-11-10 03:00:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/x',
        ...$overrides,
    ]);
    $candidate->interviewers()->sync(array_map(fn (User $user): int => $user->id, $chosen));

    return $candidate;
}

/**
 * @return list<string>
 */
function noticeTitles(User $user): array
{
    return $user->notifications()->pluck('data')->map(fn (array $data): string => (string) $data['title'])->all();
}

it('offers HR admins and Manajemen of the company, and nobody else', function () {
    $otherCompany = Company::factory()->create();
    participantUser(Role::HrAdmin, $otherCompany, 'HR Perusahaan Lain');
    participantUser(Role::Management, $otherCompany, 'Manajemen Perusahaan Lain');
    User::factory()->create(['company_id' => $this->company->id, 'name' => 'Nonaktif', 'is_active' => false])->assignRole(Role::Management->value);

    $options = fn (User $as): array => $this->actingAs($as)->get(route('recruitment.show', $this->requisition))->viewData('page')['props']['requisition']['interviewerOptions'];

    expect($options($this->hr))->toBe([
        ['id' => $this->hr->id, 'name' => 'Hana HR', 'group' => 'hr'],
        ['id' => $this->otherHr->id, 'name' => 'Hari HR', 'group' => 'hr'],
        ['id' => $this->otherManagement->id, 'name' => 'Maman Manajemen', 'group' => 'management'],
        ['id' => $this->mira->id, 'name' => 'Mira Manajemen', 'group' => 'management'],
    ])
        // Only those who may invite are given the list.
        ->and($options($this->boss))->toBe([])
        ->and($options($this->mira))->toBe([]);
});

it('keeps the people HR chose for a candidate, and replaces them when the invitation is made again', function () {
    $budi = Candidate::factory()->for($this->requisition)->create(['name' => 'Budi']);

    participantInvite($budi, [$this->hr->id, $this->mira->id])->assertSessionDoesntHaveErrors();
    expect($budi->interviewers()->pluck('users.id')->sort()->values()->all())->toBe(collect([$this->hr->id, $this->mira->id])->sort()->values()->all());

    participantInvite($budi, [$this->otherHr->id], ['interview_at' => '2026-11-13T10:00'])->assertSessionDoesntHaveErrors();
    expect($budi->interviewers()->pluck('users.id')->all())->toBe([$this->otherHr->id]);
});

it('refuses someone who is not on the list, and an empty list', function () {
    $otherCompany = Company::factory()->create();
    $outsider = participantUser(Role::Management, $otherCompany, 'Manajemen Perusahaan Lain');
    $budi = Candidate::factory()->for($this->requisition)->create();

    participantInvite($budi, [$this->boss->id])->assertSessionHasErrors('participants.0');
    participantInvite($budi, [$outsider->id])->assertSessionHasErrors('participants.0');
    participantInvite($budi, [])->assertSessionHasErrors('participants');
    $this->actingAs($this->hr)->post(route('recruitment.candidates.invite', [$this->requisition, $budi]), [
        'interview_at' => '2026-11-12T10:00', 'interview_mode' => 'online', 'interview_place' => 'x',
    ])->assertSessionHasErrors('participants');

    expect($budi->refresh()->inviteState())->toBe('none')->and($budi->interviewers()->count())->toBe(0);
});

it('tells those chosen and the direct superior, not the sender nor the HR left out, and again when the time moves', function () {
    $budi = Candidate::factory()->for($this->requisition)->create(['name' => 'Budi']);

    participantInvite($budi, [$this->hr->id, $this->mira->id])->assertSessionDoesntHaveErrors();

    expect(noticeTitles($this->mira))->toBe(['Anda diundang interview: Staf Gudang'])
        ->and(noticeTitles($this->boss))->toBe(['Anda diundang interview: Staf Gudang'])
        ->and(noticeTitles($this->hr))->toBe([])
        ->and(noticeTitles($this->otherHr))->toBe([])
        ->and(noticeTitles($this->otherManagement))->toBe([]);

    participantInvite($budi, [$this->hr->id, $this->mira->id], ['interview_at' => '2026-11-13T10:00'])->assertSessionDoesntHaveErrors();

    expect(noticeTitles($this->mira))->toContain('Jadwal interview diubah: Staf Gudang')
        ->and($this->mira->notifications()->count())->toBe(2);
});

it('puts everyone chosen and the direct superior on the calendar event, except the one who invited', function () {
    $budi = Candidate::factory()->for($this->requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);
    participantInvite($budi, [$this->hr->id, $this->mira->id])->assertSessionDoesntHaveErrors();

    $ics = str_replace("\r\n ", '', $this->actingAs($this->hr)->get(route('recruitment.candidates.calendar', [$this->requisition, $budi]))->assertOk()->getContent());

    expect($ics)->toContain('ATTENDEE;CN="Mira Manajemen"')
        ->toContain('ATTENDEE;CN="Bu Atasan"')
        ->toContain('ATTENDEE;CN="Budi"')
        ->not->toContain('ATTENDEE;CN="Hari HR"')
        ->not->toContain('ATTENDEE;CN="Maman Manajemen"');
});

it('shows a chosen Manajemen user only the candidates they sit in on, without contact details', function () {
    participantCandidate('Budi', [$this->mira]);
    participantCandidate('Ani', [$this->otherManagement]);
    Candidate::factory()->for($this->requisition)->create(['name' => 'Citra']);

    $shortlist = $this->actingAs($this->mira)->get(route('recruitment.show', $this->requisition))->viewData('page')['props']['requisition']['shortlist'];

    expect($shortlist)->toHaveCount(1)
        ->and($shortlist[0])->toMatchArray(['name' => 'Budi', 'phone' => null, 'email' => null, 'note' => null])
        ->and($this->actingAs($this->otherManagement)->get(route('recruitment.show', $this->requisition))->viewData('page')['props']['requisition']['shortlist'][0]['name'])->toBe('Ani');

    // Management who is chosen for nobody sees no list at all, as before.
    $nobody = participantUser(Role::Management, $this->company, 'Mono Manajemen');
    expect($this->actingAs($nobody)->get(route('recruitment.show', $this->requisition))->viewData('page')['props']['requisition']['shortlist'])->toBeNull();
});

it('lets a chosen Manajemen user download the event, open the CV and evaluate once the interview has passed, and no one else of Manajemen', function () {
    Storage::disk('local')->put('candidate-cvs/x.pdf', 'pdf');
    $budi = participantCandidate('Budi', [$this->mira], ['cv_path' => 'candidate-cvs/x.pdf', 'cv_name' => 'cv-budi.pdf']);
    $evaluation = ['rating' => 4, 'recommendation' => 'continue', 'note' => 'Komunikasi baik.'];
    $route = fn (string $name): string => route($name, [$this->requisition, $budi]);

    $this->actingAs($this->mira)->get($route('recruitment.candidates.calendar'))->assertOk();
    $this->actingAs($this->mira)->get($route('recruitment.candidates.cv'))->assertOk();
    $this->actingAs($this->mira)->put($route('recruitment.candidates.evaluation'), $evaluation)->assertSessionDoesntHaveErrors();
    expect($budi->evaluations()->pluck('user_id')->all())->toBe([$this->mira->id]);

    $this->actingAs($this->otherManagement)->get($route('recruitment.candidates.calendar'))->assertForbidden();
    $this->actingAs($this->otherManagement)->get($route('recruitment.candidates.cv'))->assertForbidden();
    $this->actingAs($this->otherManagement)->put($route('recruitment.candidates.evaluation'), $evaluation)->assertForbidden();

    // Being chosen does not let them choose the candidate or invite anyone.
    $this->actingAs($this->mira)->post($route('recruitment.candidates.select'))->assertForbidden();
    participantInvite($budi, [$this->mira->id], as: $this->mira)->assertForbidden();
});

it('keeps a chosen Manajemen user from evaluating before the interview, or once the candidate is out', function () {
    $ahead = participantCandidate('Ani', [$this->mira], ['interview_at' => '2026-11-11 03:00:00']);
    $out = participantCandidate('Citra', [$this->mira], ['rejected_at' => now()]);
    $evaluation = ['rating' => 4, 'recommendation' => 'continue', 'note' => 'Komunikasi baik.'];

    $this->actingAs($this->mira)->put(route('recruitment.candidates.evaluation', [$this->requisition, $ahead]), $evaluation)->assertForbidden();
    $this->actingAs($this->mira)->get(route('recruitment.candidates.calendar', [$this->requisition, $out]))->assertForbidden();
});

it('announces today\'s interviews and asks for evaluations only of those chosen, and the direct superior', function () {
    participantCandidate('Budi', [$this->hr, $this->mira]);
    participantCandidate('Ani', [$this->otherHr], ['interview_at' => '2026-11-08 03:00:00']);

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();

    expect(noticeTitles($this->hr))->toBe(['Interview hari ini: Staf Gudang (1 kandidat)'])
        ->and(noticeTitles($this->mira))->toBe(['Interview hari ini: Staf Gudang (1 kandidat)'])
        ->and(noticeTitles($this->otherHr))->toBe(['Menunggu penilaian: Staf Gudang (1 kandidat)'])
        ->and(noticeTitles($this->otherManagement))->toBe([])
        // The direct superior is told of both, each as one notice.
        ->and(noticeTitles($this->boss))->toEqualCanonicalizing(['Interview hari ini: Staf Gudang (1 kandidat)', 'Menunggu penilaian: Staf Gudang (1 kandidat)']);
});
