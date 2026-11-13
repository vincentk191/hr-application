<?php

use App\Actions\Recruitment\InviteCandidate;
use App\Enums\EmploymentType;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\CandidateOffer;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

/**
 * Making the employee profile of a hire (REC-FIL-006/007): the form is the one for any new employee, filled in from the
 * candidate, the requisition and the accepted offer; the profile goes into the requisition's company, is linked to the offer,
 * and cannot be made twice.
 *
 * "Now" is 2026-11-04 12:00 in Jakarta.
 */
beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);
    $this->branch = Branch::factory()->forCompany($this->company)->create();
    $this->position = Position::factory()->create(['company_id' => $this->company->id, 'department_id' => $this->department->id, 'name' => 'Staf Gudang']);

    $this->hr = hireUser(Role::HrAdmin, $this->company);
    $this->requester = hireUser(Role::Supervisor, $this->company);
    $this->management = hireUser(Role::Management, $this->company);
    $this->boss = hireUser(Role::Supervisor, $this->company);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id]);

    $this->requisition = hireRequisition($this->branch);
    $this->offer = hireOffer($this->requisition, 'Budi Santoso', 'budi@example.com', '081234567890');
});

function hireUser(Role $role, ?Company $company): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function hireRequisition(?Branch $branch): Requisition
{
    $requisition = Requisition::factory()->create([
        'company_id' => test()->company->id,
        'department_id' => test()->department->id,
        'branch_id' => $branch?->id,
        'created_by_user_id' => test()->requester->id,
        'manager_employee_id' => test()->bossEmployee->id,
        'position_name' => 'staf gudang',
        'employment_type' => EmploymentType::Contract,
        'contract_months' => 12,
        'quota' => 2,
        'submitted_at' => now(),
    ]);

    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], test()->requester)
        ->update(['current_stage' => RequisitionStage::Offer->value]);

    return $requisition->refresh();
}

/**
 * A candidate interviewed, chosen, approved at Rp 4.500.000, given terms, sent and accepted.
 */
function hireOffer(Requisition $requisition, string $name, string $email, string $phone, bool $accept = true): CandidateOffer
{
    $candidate = Candidate::factory()->for($requisition)->create(['name' => $name, 'email' => $email, 'phone' => $phone]);

    app(InviteCandidate::class)->handle($requisition, $candidate, [
        'interview_at' => '2026-11-05T10:00',
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'note' => null,
    ], test()->hr);

    CandidateEvaluation::factory()->create(['candidate_id' => $candidate->id, 'user_id' => test()->hr->id]);
    $candidate->forceFill(['selected_at' => now(), 'selected_by_user_id' => test()->boss->id])->save();

    test()->actingAs(test()->hr)->post(route('recruitment.offers.store', $requisition), ['candidate_ids' => [$candidate->id]]);
    $offer = $candidate->offers()->latest('id')->firstOrFail();
    test()->actingAs(test()->management)->put(route('recruitment.offers.decide', [$requisition, $offer]), ['decision' => 'approve', 'salary' => 4500000]);
    test()->actingAs(test()->hr)->put(route('recruitment.offers.terms', [$requisition, $offer]), ['start_on' => '2026-12-01', 'probation_months' => 3]);
    test()->actingAs(test()->hr)->post(route('recruitment.offers.send', [$requisition, $offer]));

    if ($accept) {
        test()->actingAs(test()->hr)->post(route('recruitment.offers.accept', [$requisition, $offer]));
    }

    return $offer->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hireFields(array $overrides = []): array
{
    return [
        'employee_number' => 'EMP-0100',
        'full_name' => 'Budi Santoso',
        'national_id' => '3201010101010001',
        'branch_id' => test()->branch->id,
        'department_id' => test()->department->id,
        'position_id' => test()->position->id,
        'manager_id' => test()->bossEmployee->id,
        'employment_status' => 'active',
        'employment_type' => 'contract',
        'joined_on' => '2026-12-01',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
        'base_salary_idr' => 4500000,
        ...$overrides,
    ];
}

function hireConvert(Requisition $requisition, CandidateOffer $offer, array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.offers.convert', [$requisition, $offer]), hireFields($fields));
}

it('fills the employee form in from the candidate, the requisition and the offer', function () {
    $props = $this->actingAs($this->hr)->get(route('recruitment.offers.employee', [$this->requisition, $this->offer]))
        ->assertOk()->viewData('page')['props'];

    expect($props['hire'])->toMatchArray([
        'companyId' => $this->company->id,
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
        'branchId' => $this->branch->id,
        'departmentId' => $this->department->id,
        // "staf gudang" in the requisition finds the company's "Staf Gudang".
        'positionId' => $this->position->id,
        'jobTitle' => 'staf gudang',
        'employmentType' => 'contract',
        'joinedOn' => '2026-12-01',
        'managerId' => $this->bossEmployee->id,
        'baseSalary' => 4500000,
        'probationMonths' => 3,
    ])
        ->and($props['companies'])->toBeNull()
        ->and($props['offerId'])->toBe($this->offer->id);
});

it('leaves the Cabang and the position to HR when the requisition names none that match', function () {
    $requisition = hireRequisition(null);
    $requisition->update(['position_name' => 'Posisi Baru']);
    $offer = hireOffer($requisition, 'Ani', 'ani@example.com', '081200000002');

    $hire = $this->actingAs($this->hr)->get(route('recruitment.offers.employee', [$requisition, $offer]))->assertOk()->viewData('page')['props']['hire'];

    expect($hire['branchId'])->toBeNull()->and($hire['positionId'])->toBeNull()->and($hire['jobTitle'])->toBe('Posisi Baru');

    hireConvert($requisition, $offer, ['branch_id' => ''])->assertSessionHasErrors('branch_id');
});

it('makes the employee in the requisition\'s company, links the offer, and records it', function () {
    hireConvert($this->requisition, $this->offer)->assertSessionDoesntHaveErrors()->assertRedirect();

    $employee = Employee::query()->where('employee_number', 'EMP-0100')->sole();

    expect($employee->company_id)->toBe($this->company->id)
        ->and($employee->full_name)->toBe('Budi Santoso')
        ->and($employee->national_id)->toBe('3201010101010001')
        ->and($employee->joined_on->toDateString())->toBe('2026-12-01')
        ->and($employee->manager_id)->toBe($this->bossEmployee->id)
        ->and($employee->position_id)->toBe($this->position->id)
        ->and($employee->base_salary_idr)->toBe(4500000)
        ->and($this->offer->refresh()->employee_id)->toBe($employee->id)
        ->and(Activity::query()->where('description', 'Pelamar diterima dijadikan karyawan')->count())->toBe(1);

    // The hire then registers an account against this number and NIK like anyone else; there is no account yet.
    expect($employee->user_id)->toBeNull();
});

it('puts the profile in the requisition\'s company even for HR who views every company', function () {
    $everyCompany = hireUser(Role::HrAdmin, null);

    hireConvert($this->requisition, $this->offer, as: $everyCompany)->assertSessionDoesntHaveErrors()->assertRedirect();

    expect(Employee::query()->where('employee_number', 'EMP-0100')->sole()->company_id)->toBe($this->company->id);
});

it('makes the profile once, and the offer then shows who it became', function () {
    hireConvert($this->requisition, $this->offer)->assertSessionDoesntHaveErrors();
    $employeeId = $this->offer->refresh()->employee_id;

    hireConvert($this->requisition, $this->offer, ['employee_number' => 'EMP-0101', 'national_id' => '3201010101010002'])->assertForbidden();
    $this->actingAs($this->hr)->get(route('recruitment.offers.employee', [$this->requisition, $this->offer]))->assertForbidden();
    expect(Employee::query()->where('employee_number', 'EMP-0101')->exists())->toBeFalse();

    $offers = fn (User $as): array => $this->actingAs($as)->get(route('recruitment.show', $this->requisition))->viewData('page')['props']['requisition']['offers'];

    expect($offers($this->hr)[0])->toMatchArray(['canConvert' => false, 'hasEmployee' => true, 'employeeId' => $employeeId])
        // The direct superior may open it, the new employee being theirs; nobody can make it again.
        ->and($offers($this->boss)[0])->toMatchArray(['canConvert' => false, 'hasEmployee' => true, 'employeeId' => $employeeId])
        ->and($offers($this->management)[0])->toMatchArray(['canConvert' => false, 'hasEmployee' => true]);
});

it('checks the profile like any new employee, and makes nothing when it is refused', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'EMP-0100', 'national_id' => '3201010101010009']);

    hireConvert($this->requisition, $this->offer)->assertSessionHasErrors('employee_number');
    hireConvert($this->requisition, $this->offer, ['employee_number' => 'EMP-0200', 'national_id' => '3201010101010009'])->assertSessionHasErrors(['national_id' => 'NIK ini sudah terdaftar pada karyawan lain.']);
    hireConvert($this->requisition, $this->offer, ['employee_number' => 'EMP-0200', 'national_id' => '123'])->assertSessionHasErrors('national_id');

    // A Cabang from another company is not one to choose.
    $foreign = Branch::factory()->forCompany(Company::factory()->create())->create();
    hireConvert($this->requisition, $this->offer, ['employee_number' => 'EMP-0200', 'branch_id' => $foreign->id])->assertSessionHasErrors('branch_id');

    expect($this->offer->refresh()->employee_id)->toBeNull()
        ->and(Employee::query()->where('employee_number', 'EMP-0200')->exists())->toBeFalse();
});

it('is HR\'s, for an accepted offer only', function () {
    foreach ([$this->management, $this->boss, $this->requester] as $user) {
        $this->actingAs($user)->get(route('recruitment.offers.employee', [$this->requisition, $this->offer]))->assertForbidden();
        hireConvert($this->requisition, $this->offer, as: $user)->assertForbidden();
    }

    $sent = hireOffer($this->requisition, 'Citra', 'citra@example.com', '081200000003', accept: false);

    $this->actingAs($this->hr)->get(route('recruitment.offers.employee', [$this->requisition, $sent]))->assertForbidden();
    hireConvert($this->requisition, $sent, ['employee_number' => 'EMP-0300', 'national_id' => '3201010101010003'])->assertForbidden();

    // HR of another company cannot even find the requisition.
    $other = hireUser(Role::HrAdmin, Company::factory()->create());
    hireConvert($this->requisition, $this->offer, as: $other)->assertNotFound();

    expect(Employee::query()->where('employee_number', 'like', 'EMP-0%')->exists())->toBeFalse();
});

it('tells HR which hires can be made employees on the Terisi tab', function () {
    $offers = fn (User $as): array => $this->actingAs($as)->get(route('recruitment.show', $this->requisition))->viewData('page')['props']['requisition']['offers'];

    expect($offers($this->hr)[0]['canConvert'])->toBeTrue()
        ->and($offers($this->management)[0]['canConvert'])->toBeFalse()
        ->and($offers($this->boss)[0]['canConvert'])->toBeFalse();
});
