<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
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
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

/**
 * Interview reminders (REC-INT): candidates with an email are emailed the day before; HR and the direct superior are told
 * the morning of. Each once. "Now" is 2026-11-09 08:00 in Jakarta; Budi's interview is tomorrow 10:00, Ani's is today.
 */
beforeEach(function () {
    Mail::fake();
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = reminderUser(Role::HrAdmin, $this->company);
    $this->boss = reminderUser(Role::Supervisor, $this->company);
    $bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);

    $this->requisition = reminderRequisition($bossEmployee->id);
});

function reminderUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function reminderRequisition(?int $managerId, RequisitionStage $stage = RequisitionStage::Interview): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->hr->id,
        'manager_employee_id' => $managerId,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->hr)->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function interviewAt(Requisition $requisition, string $local, array $overrides = []): Candidate
{
    return Candidate::factory()->for($requisition)->create([
        'invited_at' => now(), 'invite_expires_at' => now()->addDays(3),
        'interview_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(),
        'interview_mode' => 'online', 'interview_place' => 'https://meet.google.com/abc',
        ...$overrides,
    ]);
}

it('emails a candidate the day before, once, with when and where', function () {
    $budi = interviewAt($this->requisition, '2026-11-10 10:00', ['name' => 'Budi', 'email' => 'budi@example.com']);

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();

    Mail::assertQueued(CandidateReminder::class, 1);
    Mail::assertQueued(CandidateReminder::class, fn (CandidateReminder $mail): bool => $mail->hasTo('budi@example.com')
        && str_contains($mail->subjectLine, 'Pengingat interview besok')
        && str_contains($mail->body, 'Halo Budi')
        && str_contains($mail->body, 'Selasa, 10 November 2026')
        && str_contains($mail->body, '10:00 WIB')
        && str_contains($mail->body, 'https://meet.google.com/abc')
        && ! str_contains($mail->body, '/lamaran/'));
    expect($budi->refresh()->candidate_reminded_at)->not->toBeNull();

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();
    Mail::assertQueued(CandidateReminder::class, 1);
});

it('leaves a candidate without an email, and those not interviewing tomorrow', function () {
    interviewAt($this->requisition, '2026-11-10 10:00', ['email' => null]);
    interviewAt($this->requisition, '2026-11-11 10:00', ['email' => 'later@example.com']);
    interviewAt($this->requisition, '2026-11-09 15:00', ['email' => 'today@example.com']);
    Candidate::factory()->for($this->requisition)->create(['email' => 'never@example.com']);

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('judges tomorrow in company time, not UTC', function () {
    // 00:30 Jakarta on the 10th is still the 9th in UTC; 23:30 on the 10th is the 10th in both.
    interviewAt($this->requisition, '2026-11-10 00:30', ['email' => 'early@example.com']);
    interviewAt($this->requisition, '2026-11-10 23:30', ['email' => 'late@example.com']);
    interviewAt($this->requisition, '2026-11-11 00:30', ['email' => 'next@example.com']);

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();

    Mail::assertQueued(CandidateReminder::class, 2);
    Mail::assertNotQueued(CandidateReminder::class, fn (CandidateReminder $mail): bool => $mail->hasTo('next@example.com'));
});

it('tells HR and the direct superior about today\'s interviews, once, with the times', function () {
    interviewAt($this->requisition, '2026-11-09 14:00', ['name' => 'Ani']);
    interviewAt($this->requisition, '2026-11-09 10:00', ['name' => 'Budi']);
    interviewAt($this->requisition, '2026-11-10 10:00', ['name' => 'Besok']);

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();

    foreach ([$this->hr, $this->boss] as $person) {
        $notices = $person->notifications()->get();

        expect($notices)->toHaveCount(1)
            ->and($notices->first()->data['title'])->toBe('Interview hari ini: Staf Gudang (2 kandidat)')
            ->and(json_encode($notices->first()->data))->toContain('Budi 10:00, Ani 14:00')->not->toContain('Besok');
    }

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();
    expect($this->hr->notifications()->count())->toBe(1)->and($this->boss->notifications()->count())->toBe(1);
});

it('reminds again after the interview is moved to a new time', function () {
    $budi = interviewAt($this->requisition, '2026-11-09 10:00', ['email' => 'budi@example.com']);
    $this->artisan('recruitment:interview-reminders');
    expect($this->hr->notifications()->count())->toBe(1)->and($budi->refresh()->interviewers_reminded_at)->not->toBeNull();

    // HR invites again for tomorrow: the new day is reminded on its own.
    $this->actingAs($this->hr)->post(route('recruitment.candidates.invite', [$this->requisition, $budi]), [
        'interview_at' => '2026-11-10T10:00', 'interview_mode' => 'online', 'interview_place' => 'https://meet.google.com/new',
    ])->assertSessionDoesntHaveErrors();
    expect($budi->refresh()->interviewers_reminded_at)->toBeNull()->and($budi->candidate_reminded_at)->toBeNull();

    $this->artisan('recruitment:interview-reminders');
    Mail::assertQueued(CandidateReminder::class, fn (CandidateReminder $mail): bool => str_contains($mail->body, 'https://meet.google.com/new'));
});

it('leaves requisitions that are cancelled, filled or not yet at Interview alone', function () {
    foreach ([RequisitionStage::Cancelled, RequisitionStage::Filled, RequisitionStage::Posting] as $stage) {
        $other = reminderRequisition(null, $stage);
        interviewAt($other, '2026-11-10 10:00', ['email' => "{$stage->value}@example.com"]);
        interviewAt($other, '2026-11-09 10:00');
    }

    $this->artisan('recruitment:interview-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($this->hr->notifications()->count())->toBe(0);
});

it('is on the schedule every morning at 08:00 company time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'recruitment:interview-reminders'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 8 * * *')->and($event->timezone)->toBe('Asia/Jakarta');
});
