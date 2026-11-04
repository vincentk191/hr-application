<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Recruitment stage 03, Screening (REC-SCR): screening happens inside the job platforms, so HR enters the total
 * number of applicants and shortlists the candidates worth taking further. Applicants who are not shortlisted are
 * never stored.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Gudang']);

    $this->hr = screeningUser(Role::HrAdmin, $this->company);
    $this->management = screeningUser(Role::Management, $this->company);
    $this->requester = screeningUser(Role::Supervisor, $this->company);
});

function screeningUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function screeningRequisition(RequisitionStage $stage = RequisitionStage::Screening): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'created_by_user_id' => test()->requester->id,
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => $stage->value]);

    return $requisition->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function candidateFields(array $overrides = []): array
{
    return ['name' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'email' => 'Budi@Example.com', 'source' => 'glints', ...$overrides];
}

function shortlist(Requisition $requisition, array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.candidates.store', $requisition), candidateFields($fields));
}

// --- The applicant total ---

it('lets HR enter the total number of applicants, and shows it with the shortlist', function () {
    $requisition = screeningRequisition();

    $this->actingAs($this->hr)->put(route('recruitment.applicants.update', $requisition), ['applicants_count' => 87])->assertRedirect();
    shortlist($requisition)->assertRedirect();

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.applicantsCount', 87)
            ->where('requisition.shortlistedCount', 1)
            ->where('requisition.statusLabel', '87 pelamar · 1 shortlist')
            ->where('requisition.canManageScreening', true));
});

it('checks the total: a whole number, and never below the shortlist', function () {
    $requisition = screeningRequisition();
    shortlist($requisition, ['phone' => '081200000001', 'email' => null]);
    shortlist($requisition, ['phone' => '081200000002', 'email' => null, 'name' => 'Ani']);
    $put = fn (mixed $count) => $this->actingAs($this->hr)->put(route('recruitment.applicants.update', $requisition), ['applicants_count' => $count]);

    $put('')->assertSessionHasErrors('applicants_count');
    $put('abc')->assertSessionHasErrors('applicants_count');
    $put(-1)->assertSessionHasErrors('applicants_count');
    $put(1)->assertSessionHasErrors('applicants_count');
    $put(2)->assertSessionDoesntHaveErrors();

    expect($requisition->refresh()->applicants_count)->toBe(2);
});

it('raises a total that falls below the shortlist when someone is added', function () {
    $requisition = screeningRequisition();
    $requisition->update(['applicants_count' => 1]);

    shortlist($requisition, ['phone' => '081200000001', 'email' => null]);
    shortlist($requisition, ['phone' => '081200000002', 'email' => null, 'name' => 'Ani']);

    expect($requisition->refresh()->applicants_count)->toBe(2);
});

// --- Shortlisting ---

it('shortlists a candidate with a normalised phone and a lower-case email', function () {
    $requisition = screeningRequisition();

    shortlist($requisition, ['source' => 'other', 'source_name' => 'Instagram CMP', 'note' => 'Pengalaman 3 tahun gudang'])->assertRedirect();

    $candidate = Candidate::query()->firstOrFail();

    expect($candidate->requisition_id)->toBe($requisition->id)
        ->and($candidate->company_id)->toBe($this->company->id)
        ->and($candidate->phone)->toBe('0812-3456-7890')
        ->and($candidate->phone_key)->toBe('6281234567890')
        ->and($candidate->email)->toBe('budi@example.com')
        ->and($candidate->sourceLabel())->toBe('Instagram CMP')
        ->and($candidate->added_by_user_id)->toBe($this->hr->id);
});

it('reads the same phone number written different ways as one', function () {
    expect(Candidate::phoneKey('0812-3456-7890'))->toBe('6281234567890')
        ->and(Candidate::phoneKey('+62 812 3456 7890'))->toBe('6281234567890')
        ->and(Candidate::phoneKey('6281234567890'))->toBe('6281234567890')
        ->and(Candidate::phoneKey('812 3456 7890'))->toBe('6281234567890')
        ->and(Candidate::phoneKey('12345'))->toBeNull()
        ->and(Candidate::phoneKey(''))->toBeNull();
});

it('needs a name, a source and a way to reach the candidate', function () {
    $requisition = screeningRequisition();

    shortlist($requisition, ['name' => ''])->assertSessionHasErrors('name');
    shortlist($requisition, ['source' => 'friendster'])->assertSessionHasErrors('source');
    shortlist($requisition, ['source' => 'other', 'source_name' => ''])->assertSessionHasErrors('source_name');
    shortlist($requisition, ['phone' => null, 'email' => null])->assertSessionHasErrors('phone');
    shortlist($requisition, ['phone' => '12345', 'email' => null])->assertSessionHasErrors('phone');
    shortlist($requisition, ['phone' => 'abc', 'email' => null])->assertSessionHasErrors('phone');
    shortlist($requisition, ['email' => 'bukan-email'])->assertSessionHasErrors('email');
    shortlist($requisition, ['phone' => null, 'email' => 'budi@example.com'])->assertSessionDoesntHaveErrors();

    expect(Candidate::query()->count())->toBe(1);
});

it('warns about a phone number or email already shortlisted, until HR confirms', function () {
    $requisition = screeningRequisition();
    $other = screeningRequisition();
    shortlist($other)->assertSessionDoesntHaveErrors();

    shortlist($requisition, ['name' => 'Budi S.', 'phone' => '+62 812 3456 7890', 'email' => null])
        ->assertSessionHasErrors(['duplicate' => "Budi Santoso sudah ada di shortlist {$other->reference()} ({$other->position_name}) dengan nomor telepon atau email yang sama."]);
    shortlist($requisition, ['name' => 'Budi S.', 'phone' => '0899999999', 'email' => 'BUDI@example.com'])->assertSessionHasErrors('duplicate');
    expect(Candidate::query()->count())->toBe(1);

    shortlist($requisition, ['name' => 'Budi S.', 'confirm_duplicate' => 1])->assertSessionDoesntHaveErrors();
    expect(Candidate::query()->count())->toBe(2);
});

it('keeps a CV on the private disk and serves it only to HR', function () {
    $requisition = screeningRequisition();

    shortlist($requisition, ['cv' => UploadedFile::fake()->create('cv-budi.pdf', 200, 'application/pdf')])->assertSessionDoesntHaveErrors();

    $candidate = Candidate::query()->firstOrFail();
    Storage::disk('local')->assertExists($candidate->cv_path);
    expect($candidate->cv_name)->toBe('cv-budi.pdf');

    $this->actingAs($this->hr)->get(route('recruitment.candidates.cv', [$requisition, $candidate]))->assertDownload('cv-budi.pdf');
    $this->actingAs($this->requester)->get(route('recruitment.candidates.cv', [$requisition, $candidate]))->assertForbidden();
    $this->actingAs($this->management)->get(route('recruitment.candidates.cv', [$requisition, $candidate]))->assertForbidden();
});

it('shows a CV picture inline and refuses a CV of the wrong type or size', function () {
    $requisition = screeningRequisition();

    shortlist($requisition, ['cv' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('cv');
    shortlist($requisition, ['cv' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('cv');
    shortlist($requisition, ['cv' => UploadedFile::fake()->image('cv.png')])->assertSessionDoesntHaveErrors();

    $candidate = Candidate::query()->firstOrFail();
    $inline = $this->actingAs($this->hr)->get(route('recruitment.candidates.cv', [$requisition, $candidate, 'inline' => 1]))->assertOk();

    expect($inline->headers->get('Content-Disposition'))->toStartWith('inline');
});

it('takes someone off the shortlist and deletes their CV, on that requisition only', function () {
    $requisition = screeningRequisition();
    $other = screeningRequisition();
    shortlist($requisition, ['cv' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')]);
    $candidate = Candidate::query()->firstOrFail();

    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$other, $candidate]))->assertNotFound();
    $this->actingAs($this->requester)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertForbidden();
    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$requisition, $candidate]))->assertRedirect();

    expect(Candidate::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($candidate->cv_path);
});

// --- Who may do and see what ---

it('lets only HR shortlist, and only while the requisition is at Screening', function () {
    $requisition = screeningRequisition();

    shortlist($requisition, as: $this->requester)->assertForbidden();
    shortlist($requisition, as: $this->management)->assertForbidden();
    $this->actingAs($this->requester)->put(route('recruitment.applicants.update', $requisition), ['applicants_count' => 5])->assertForbidden();

    foreach ([RequisitionStage::Posting, RequisitionStage::Interview] as $stage) {
        shortlist(screeningRequisition($stage))->assertForbidden();
    }

    expect(Candidate::query()->count())->toBe(0);
});

it('keeps the names to HR and gives everyone else the counts', function () {
    $requisition = screeningRequisition();
    $requisition->update(['applicants_count' => 40]);
    shortlist($requisition);

    $this->actingAs($this->hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page->where('requisition.shortlist.0.name', 'Budi Santoso'));

    foreach ([$this->requester, $this->management] as $user) {
        $this->actingAs($user)->get(route('recruitment.show', $requisition))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requisition.shortlist', null)
                ->where('requisition.applicantsCount', 40)
                ->where('requisition.shortlistedCount', 1)
                ->where('requisition.canManageScreening', false));
    }
});

it('shows the shortlist size in the dashboard row from Screening on', function () {
    $requisition = screeningRequisition();
    shortlist($requisition);
    screeningRequisition(RequisitionStage::Posting);

    $this->actingAs($this->hr)->get(route('recruitment.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisitions.0.candidates', null)
            ->where('requisitions.1.candidates', 1));
});

// --- Moving on to Interview ---

it('keeps "Lanjut ke Interview" shut until someone is shortlisted, and lets only HR use it', function () {
    $requisition = screeningRequisition();

    $this->actingAs($this->hr)->post(route('recruitment.interview', $requisition))->assertSessionHasErrors('advance');
    expect($requisition->refresh()->workflow->current_stage)->toBe('screening');

    shortlist($requisition);

    $this->actingAs($this->requester)->post(route('recruitment.interview', $requisition))->assertForbidden();
    $this->actingAs($this->hr)->post(route('recruitment.interview', $requisition))->assertRedirect();

    expect($requisition->refresh()->workflow->current_stage)->toBe('interview');
});
