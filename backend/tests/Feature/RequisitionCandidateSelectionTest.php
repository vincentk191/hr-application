<?php

use App\Actions\Recruitment\InviteCandidate;
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
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * The direct superior's choice (REC-PIP) and the move from Interview to Penawaran: the superior picks the candidate they
 * prefer after the interview, HR is told, and HR takes the requisition on once somebody is picked.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta; interviews are held on the 5th at 10:00.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = selectionUser(Role::HrAdmin, $this->company);
    $this->requester = selectionUser(Role::Supervisor, $this->company);
    $this->boss = selectionUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);
});

function selectionUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function selectionRequisition(RequisitionStage $stage = RequisitionStage::Interview): Requisition
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
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

/** A candidate invited for the 5th at 10:00. */
function selectionCandidate(Requisition $requisition, string $name = 'Budi'): Candidate
{
    $candidate = Candidate::factory()->for($requisition)->create(['name' => $name]);

    app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], test()->hr);

    return $candidate->refresh();
}

/** The interview has been held. */
function afterTheInterview(): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-05 14:00', 'Asia/Jakarta'));
}

function pick(Requisition $requisition, Candidate $candidate, ?User $as = null)
{
    return test()->actingAs($as ?? test()->boss)->post(route('recruitment.candidates.select', [$requisition, $candidate]));
}

it('lets the direct superior pick an interviewed candidate, tells HR, and keeps it on the audit trail', function () {
    $requisition = selectionRequisition();
    $candidate = selectionCandidate($requisition);
    afterTheInterview();

    pick($requisition, $candidate)->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Budi dipilih.']);

    $candidate->refresh();
    $entry = Activity::query()->where('description', 'Kandidat dipilih')->sole();

    expect($candidate->isSelected())->toBeTrue()
        ->and($candidate->selected_by_user_id)->toBe($this->boss->id)
        ->and($entry->causer_id)->toBe($this->boss->id)
        ->and($this->hr->notifications()->get()->pluck('data.title')->implode(' '))->toContain('Budi dipilih')
        ->and($this->boss->notifications()->count())->toBe(0);
});

it('lets nobody else pick, and only a candidate who was interviewed and is still in the process', function () {
    $requisition = selectionRequisition();
    $candidate = selectionCandidate($requisition);

    // The interview has not happened yet.
    pick($requisition, $candidate)->assertForbidden();

    afterTheInterview();

    pick($requisition, $candidate, $this->hr)->assertForbidden();
    pick($requisition, $candidate, $this->requester)->assertForbidden();

    $notInvited = Candidate::factory()->for($requisition)->create();
    pick($requisition, $notInvited)->assertForbidden();

    $rejected = selectionCandidate($requisition, 'Ani');
    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$requisition, $rejected]), ['note' => 'Kurang cocok.']);
    pick($requisition, $rejected)->assertForbidden();

    $other = selectionRequisition();
    pick($requisition, selectionCandidate($other, 'Citra'))->assertNotFound();

    $posting = selectionRequisition(RequisitionStage::Posting);
    pick($posting, selectionCandidate($posting, 'Dewi'))->assertForbidden();

    pick($requisition, $candidate)->assertRedirect();
    pick($requisition, $candidate)->assertForbidden();

    expect(Candidate::query()->selected()->count())->toBe(1);
});

it('lets the superior take the choice back, and nobody else', function () {
    $requisition = selectionRequisition();
    $candidate = selectionCandidate($requisition);
    afterTheInterview();
    pick($requisition, $candidate)->assertRedirect();

    $url = route('recruitment.candidates.unselect', [$requisition, $candidate]);
    $this->actingAs($this->hr)->delete($url)->assertForbidden();

    $this->actingAs($this->boss)->delete($url)->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pilihan Budi dibatalkan.']);

    expect($candidate->refresh()->isSelected())->toBeFalse()
        ->and($candidate->selected_by_user_id)->toBeNull()
        ->and(Activity::query()->where('description', 'Pilihan kandidat dibatalkan')->count())->toBe(1);

    $this->actingAs($this->boss)->delete($url)->assertForbidden();
});

it('moves to Penawaran only once somebody is picked, and only HR does it', function () {
    $requisition = selectionRequisition();
    $candidate = selectionCandidate($requisition);
    afterTheInterview();
    $url = route('recruitment.offer', $requisition);

    $this->actingAs($this->hr)->post($url)->assertSessionHasErrors(['advance' => 'Atasan langsung belum memilih kandidat.']);
    expect($requisition->refresh()->stage())->toBe(RequisitionStage::Interview);

    pick($requisition, $candidate)->assertRedirect();

    $this->actingAs($this->boss)->post($url)->assertForbidden();
    $this->actingAs($this->requester)->post($url)->assertForbidden();

    $this->actingAs($this->hr)->post($url)->assertRedirect()->assertSessionDoesntHaveErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} lanjut ke tahap Penawaran."]);

    expect($requisition->refresh()->stage())->toBe(RequisitionStage::Offer);

    // Once at Penawaran, there is nothing left to advance.
    $this->actingAs($this->hr)->post($url)->assertForbidden();
});

it('does not count a picked candidate who was turned down, until HR takes the rejection back', function () {
    $requisition = selectionRequisition();
    $candidate = selectionCandidate($requisition);
    afterTheInterview();
    pick($requisition, $candidate)->assertRedirect();

    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$requisition, $candidate]), ['note' => 'Referensi buruk.'])->assertRedirect();

    expect($candidate->refresh()->isSelected())->toBeFalse();
    $this->actingAs($this->hr)->post(route('recruitment.offer', $requisition))->assertSessionHasErrors('advance');

    $this->actingAs($this->hr)->delete(route('recruitment.candidates.restore', [$requisition, $candidate]))->assertRedirect();

    expect($candidate->refresh()->isSelected())->toBeTrue();
    $this->actingAs($this->hr)->post(route('recruitment.offer', $requisition))->assertSessionDoesntHaveErrors();
});

it('tells the page who is picked and who may pick', function () {
    $requisition = selectionRequisition();
    $picked = selectionCandidate($requisition, 'Budi');
    $waiting = selectionCandidate($requisition, 'Ani');
    afterTheInterview();
    pick($requisition, $picked)->assertRedirect();

    $this->actingAs($this->boss)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.selectedCount', 1)
            ->where('requisition.shortlist.0.isSelected', true)
            ->where('requisition.shortlist.0.selectedBy', $this->boss->name)
            ->where('requisition.shortlist.0.canSelect', false)
            ->where('requisition.shortlist.0.canUnselect', true)
            ->where('requisition.shortlist.1.isSelected', false)
            ->where('requisition.shortlist.1.canSelect', true)
            ->where('requisition.shortlist.1.canUnselect', false)
            ->where('requisition.canAdvanceToOffer', false));

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.selectedCount', 1)
            ->where('requisition.shortlist.0.isSelected', true)
            ->where('requisition.shortlist.0.canSelect', false)
            ->where('requisition.shortlist.0.canUnselect', false)
            ->where('requisition.shortlist.1.canSelect', false)
            ->where('requisition.canAdvanceToOffer', true)
            ->where('requisition.offerBlockers', []));

    // The requester sees how many were picked, not who.
    $this->actingAs($this->requester)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.selectedCount', 1)
            ->where('requisition.shortlist', null)
            ->where('requisition.canAdvanceToOffer', false));

    expect($waiting->refresh()->isSelected())->toBeFalse();
});
