<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Recruitment stage 02, Posting (REC-PST): HR posts the listing on the job platforms by hand and records where and
 * when, moves the requisition on to Screening once it is up somewhere, or calls it off with a reason.
 *
 * "Today" is 2026-11-04 in Jakarta. Rudi raised the requisition; HR and HR-2 are HR Admins, Mira is Management.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Gudang']);

    $this->hr = postingUser(Role::HrAdmin, $this->company);
    $this->management = postingUser(Role::Management, $this->company);
    $this->requester = postingUser(Role::Supervisor, $this->company);
});

function postingUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * A sent requisition sitting at the given stage, raised by the requester.
 */
function requisitionAtStage(RequisitionStage $stage): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'submitted_at' => now(),
    ]);

    $instance = app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester);
    $instance->update(['current_stage' => $stage->value]);

    // The tracker dates a stage by the move into it, so leave a record for every stage passed on the way.
    foreach ($stage === RequisitionStage::Draft ? [] : RequisitionStage::pipeline() as $passed) {
        $instance->transitions()->create(['from_stage' => null, 'to_stage' => $passed->value, 'actor_user_id' => test()->requester->id]);

        if ($passed === $stage) {
            break;
        }
    }

    return $requisition->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function postingFields(array $overrides = []): array
{
    return ['platform' => 'glints', 'url' => 'https://glints.com/id/opportunities/jobs/abc', 'posted_on' => '2026-11-04', ...$overrides];
}

// --- Recording a posting ---

it('lets HR record where the listing was posted and shows it to the requester', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);

    $this->actingAs($this->hr)->post(route('recruitment.postings.store', $requisition), postingFields())->assertRedirect();
    $this->actingAs($this->hr)->post(route('recruitment.postings.store', $requisition), postingFields([
        'platform' => 'other', 'platform_name' => 'Instagram CMP', 'url' => null,
    ]))->assertRedirect();

    $this->assertDatabaseHas('requisition_postings', ['requisition_id' => $requisition->id, 'platform' => 'glints', 'posted_by_user_id' => $this->hr->id]);

    $this->actingAs($this->requester)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.statusLabel', 'Diposting di Glints, Instagram CMP')
            ->where('requisition.postings.0.label', 'Glints')
            ->where('requisition.postings.0.postedOn', '2026-11-04')
            ->where('requisition.postings.1.label', 'Instagram CMP')
            ->where('requisition.canManagePostings', false));
});

it('says "Belum diposting" until something is recorded', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.statusLabel', 'Belum diposting')
            ->where('requisition.canManagePostings', true)
            ->where('requisition.screeningBlockers.0', 'Tandai lowongan sudah diposting di setidaknya satu platform dulu.'));
});

it('lets only HR record postings, and only while the requisition is at Posting', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);

    foreach ([$this->requester, $this->management] as $user) {
        $this->actingAs($user)->post(route('recruitment.postings.store', $requisition), postingFields())->assertForbidden();
    }

    $submitted = requisitionAtStage(RequisitionStage::Submitted);
    $this->actingAs($this->hr)->post(route('recruitment.postings.store', $submitted), postingFields())->assertForbidden();

    $screening = requisitionAtStage(RequisitionStage::Screening);
    $this->actingAs($this->hr)->post(route('recruitment.postings.store', $screening), postingFields())->assertForbidden();

    expect(RequisitionPosting::query()->count())->toBe(0);
});

it('checks the platform, the link and the date', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);
    $store = fn (array $fields) => $this->actingAs($this->hr)->post(route('recruitment.postings.store', $requisition), postingFields($fields));

    $store(['platform' => 'friendster'])->assertSessionHasErrors('platform');
    $store(['platform' => 'other', 'platform_name' => ''])->assertSessionHasErrors('platform_name');
    $store(['url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
    $store(['url' => 'glints.com/lowongan'])->assertSessionHasErrors('url');
    $store(['posted_on' => '2026-11-05'])->assertSessionHasErrors('posted_on');
    $store(['posted_on' => ''])->assertSessionHasErrors('posted_on');
    $store(['url' => null, 'posted_on' => '2026-10-30'])->assertSessionDoesntHaveErrors();

    expect(RequisitionPosting::query()->count())->toBe(1);
});

it('records a named platform once, but "Lainnya" as often as needed', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);
    $store = fn (array $fields) => $this->actingAs($this->hr)->post(route('recruitment.postings.store', $requisition), postingFields($fields));

    $store([])->assertSessionDoesntHaveErrors();
    $store([])->assertSessionHasErrors('platform');
    $store(['platform' => 'other', 'platform_name' => 'Grup WhatsApp'])->assertSessionDoesntHaveErrors();
    $store(['platform' => 'other', 'platform_name' => 'Papan pengumuman'])->assertSessionDoesntHaveErrors();

    expect(RequisitionPosting::query()->count())->toBe(3);
});

it('lets HR correct or remove a posting, but only on its own requisition', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);
    $other = requisitionAtStage(RequisitionStage::Posting);
    $posting = RequisitionPosting::factory()->for($requisition)->create();

    $this->actingAs($this->hr)->put(route('recruitment.postings.update', [$requisition, $posting]), postingFields([
        'platform' => 'jobstreet', 'url' => 'https://id.jobstreet.com/job/1',
    ]))->assertRedirect();

    expect($posting->refresh()->platform->value)->toBe('jobstreet')->and($posting->url)->toBe('https://id.jobstreet.com/job/1');

    $this->actingAs($this->hr)->put(route('recruitment.postings.update', [$other, $posting]), postingFields())->assertNotFound();
    $this->actingAs($this->hr)->delete(route('recruitment.postings.destroy', [$other, $posting]))->assertNotFound();

    $this->actingAs($this->requester)->delete(route('recruitment.postings.destroy', [$requisition, $posting]))->assertForbidden();
    $this->actingAs($this->hr)->delete(route('recruitment.postings.destroy', [$requisition, $posting]))->assertRedirect();

    expect(RequisitionPosting::query()->count())->toBe(0);
});

// --- Moving on to Screening ---

it('keeps "Lanjut ke Screening" shut until the listing is recorded, and lets only HR use it', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);

    $this->actingAs($this->hr)->post(route('recruitment.screening', $requisition))->assertSessionHasErrors('advance');
    expect(stageOfPosting($requisition))->toBe('posting');

    RequisitionPosting::factory()->for($requisition)->create();

    $this->actingAs($this->requester)->post(route('recruitment.screening', $requisition))->assertForbidden();
    $this->actingAs($this->management)->post(route('recruitment.screening', $requisition))->assertForbidden();
    $this->actingAs($this->hr)->post(route('recruitment.screening', $requisition))->assertRedirect();

    expect(stageOfPosting($requisition))->toBe('screening');

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.tabs.1.state', 'done')
            ->where('requisition.tabs.2.state', 'current'));
});

function stageOfPosting(Requisition $requisition): string
{
    return (string) $requisition->refresh()->workflow->current_stage;
}

// --- Calling a requisition off ---

it('lets HR call a requisition off with a reason, and tells the requester', function () {
    $requisition = requisitionAtStage(RequisitionStage::Posting);

    $this->actingAs($this->hr)->post(route('recruitment.cancel', $requisition), ['note' => '  '])->assertSessionHasErrors('note');
    expect(stageOfPosting($requisition))->toBe('posting');

    $this->actingAs($this->requester)->post(route('recruitment.cancel', $requisition), ['note' => 'Tidak jadi'])->assertForbidden();
    $this->actingAs($this->management)->post(route('recruitment.cancel', $requisition), ['note' => 'Tidak jadi'])->assertForbidden();

    $this->actingAs($this->hr)->post(route('recruitment.cancel', $requisition), ['note' => 'Posisi diisi dari internal.'])->assertRedirect();

    expect(stageOfPosting($requisition))->toBe('cancelled')
        ->and($requisition->cancel_reason)->toBe('Posisi diisi dari internal.')
        ->and($requisition->workflow->comments()->where('body', 'Posisi diisi dari internal.')->exists())->toBeTrue()
        ->and($this->requester->notifications()->latest()->firstOrFail()->data['title'])->toContain('dibatalkan');

    $this->actingAs($this->requester)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.stageLabel', 'Dibatalkan')
            ->where('requisition.cancelledReason', 'Posisi diisi dari internal.')
            ->where('requisition.cancelledAt', '2026-11-04')
            ->where('requisition.cancelledBy', $this->hr->name)
            ->where('requisition.tabs.0.state', 'done')
            ->where('requisition.tabs.1.state', 'done')
            ->where('requisition.tabs.2.state', 'locked')
            ->where('requisition.canCancel', false));

    $this->actingAs($this->hr)->get(route('recruitment.index'))
        ->assertInertia(fn (Assert $page) => $page->where('summary.open', 0)->has('requisitions', 0));
    $this->actingAs($this->hr)->get(route('recruitment.index', ['status' => 'cancelled']))
        ->assertInertia(fn (Assert $page) => $page->has('requisitions', 1));
});

it('cancels at any stage from Posting to Offer, never before approval or after it is filled', function () {
    foreach ([RequisitionStage::Screening, RequisitionStage::Interview, RequisitionStage::Offer] as $stage) {
        $requisition = requisitionAtStage($stage);

        $this->actingAs($this->hr)->post(route('recruitment.cancel', $requisition), ['note' => 'Dibatalkan'])->assertRedirect();
        expect(stageOfPosting($requisition))->toBe('cancelled');
    }

    foreach ([RequisitionStage::Draft, RequisitionStage::Submitted, RequisitionStage::Filled] as $stage) {
        $requisition = requisitionAtStage($stage);

        $this->actingAs($this->hr)->post(route('recruitment.cancel', $requisition), ['note' => 'Dibatalkan'])->assertForbidden();
        expect(stageOfPosting($requisition))->toBe($stage->value);
    }
});
