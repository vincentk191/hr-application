<?php

use App\Actions\Recruitment\SubmitRequisition;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\RequisitionTemplate;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The requisition's fixed template (REC-WZ1, REC-WZ2): the start date safeguard, the contract and the direct superior,
 * the sections that must be filled before sending, and how a requisition relates to the base template HR sets.
 *
 * The tree: Mira (Management) manages Rudi (Supervisor, head of Gudang); Rudi manages Sinta.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->gudang = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Gudang']);

    $this->mira = tplMember('Mira', null, Role::Management, $this->company, $this->gudang);
    $this->rudi = tplMember('Rudi', $this->mira, Role::Supervisor, $this->company, $this->gudang);
    $this->sinta = tplMember('Sinta', $this->rudi, Role::Employee, $this->company, $this->gudang);

    $this->gudang->update(['supervisor_employee_id' => $this->rudi->id]);
});

function tplMember(string $name, ?Employee $manager, Role $role, Company $company, Department $department): Employee
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name,
        'manager_id' => $manager?->id,
        'department_id' => $department->id,
        'user_id' => $user->id,
    ]);
}

/** Today in the company's timezone, which is what the start date is judged against. */
function tplToday(): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Jakarta')->startOfDay();
}

/**
 * Every field the template asks for, filled.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tplFields(array $overrides = []): array
{
    return [
        'department_id' => test()->gudang->id,
        'position_name' => 'Staf Gudang',
        'quota' => 2,
        'employment_type' => 'contract',
        'contract_months' => 12,
        'manager_employee_id' => test()->rudi->id,
        'reason' => 'Volume kiriman naik',
        'target_start_on' => tplToday()->addMonth()->toDateString(),
        'about' => 'Kami mencari staf gudang yang teliti.',
        'responsibilities' => [['title' => 'Barang keluar', 'items' => ['Menyiapkan barang keluar.']]],
        'benefits' => ['BPJS Kesehatan & BPJS Ketenagakerjaan.'],
        'checklist' => ['education' => ['Minimal SMA/SMK'], 'experience' => [], 'hard' => ['Menguasai Excel'], 'soft' => ['Teliti'], 'bonus' => []],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function tplSave(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->rudi->user)->post(route('recruitment.store'), tplFields($overrides));
}

function tplLatest(): Requisition
{
    return Requisition::query()->latest('id')->firstOrFail();
}

// --- The start date ---

it('refuses a start date in the past, and accepts today or later', function () {
    tplSave(['target_start_on' => tplToday()->subDay()->toDateString()])
        ->assertSessionHasErrors(['target_start_on' => 'Target mulai kerja tidak boleh di masa lalu.']);

    expect(Requisition::count())->toBe(0);

    tplSave(['target_start_on' => tplToday()->toDateString()])->assertSessionHasNoErrors();
    tplSave(['target_start_on' => null])->assertSessionHasNoErrors();

    expect(Requisition::count())->toBe(2);
});

it('refuses a start date years away, which is usually a typo', function () {
    tplSave(['target_start_on' => tplToday()->addYears(2)->toDateString()])->assertSessionHasNoErrors();
    tplSave(['target_start_on' => tplToday()->addYears(2)->addDay()->toDateString()])->assertSessionHasErrors('target_start_on');
    tplSave(['target_start_on' => '2062-01-01'])->assertSessionHasErrors('target_start_on');
    tplSave(['target_start_on' => 'besok'])->assertSessionHasErrors('target_start_on');
});

it('judges today in the company timezone, not the server clock', function () {
    // 01:00 on the 29th in Jakarta is still the 28th in UTC.
    $this->travelTo(CarbonImmutable::parse('2026-10-28 18:00', 'UTC'));

    tplSave(['target_start_on' => '2026-10-28'])->assertSessionHasErrors('target_start_on');
    tplSave(['target_start_on' => '2026-10-29'])->assertSessionHasNoErrors();
});

it('catches a draft whose start date has passed since it was saved, before it is sent', function () {
    tplSave()->assertRedirect();
    $draft = tplLatest();
    $saved = $draft->target_start_on->toDateString();

    $this->travelTo(now()->addMonths(2));

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $draft), tplFields(['target_start_on' => $saved, 'send' => 1]))
        ->assertSessionHasErrors('target_start_on');

    expect((string) $draft->refresh()->workflow->current_stage)->toBe('draft');

    // The action refuses it too, whatever called it.
    expect(fn () => app(SubmitRequisition::class)->handle($draft->refresh(), $this->rudi->user))
        ->toThrow(ValidationException::class, 'sudah lewat');

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $draft), tplFields(['target_start_on' => tplToday()->addDays(10)->toDateString(), 'send' => 1]))
        ->assertRedirect(route('recruitment.show', $draft));
});

it('does not block correcting a sent requisition because its old start date has since passed', function () {
    tplSave(['send' => 1])->assertRedirect();
    $requisition = tplLatest();
    $date = $requisition->target_start_on->toDateString();

    $this->travelTo(now()->addMonths(2));

    // Left alone, the passed date is kept; set to another past date, it is refused.
    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $requisition), tplFields(['target_start_on' => $date, 'position_name' => 'Staf Gudang Senior']))
        ->assertSessionHasNoErrors()->assertRedirect(route('recruitment.show', $requisition));

    expect($requisition->refresh()->position_name)->toBe('Staf Gudang Senior');

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $requisition), tplFields(['target_start_on' => tplToday()->subDays(3)->toDateString()]))
        ->assertSessionHasErrors('target_start_on');
});

// --- Contract and direct superior ---

it('asks for the contract length only for a contract or an internship, and says it plainly', function () {
    tplSave(['employment_type' => 'contract', 'contract_months' => 12])->assertRedirect();
    expect(tplLatest()->contractLabel())->toBe('Kontrak 1 tahun');

    tplSave(['employment_type' => 'intern', 'contract_months' => 6])->assertRedirect();
    expect(tplLatest()->contractLabel())->toBe('Magang 6 bulan');

    // A permanent post has no length: one sent with the form is dropped.
    tplSave(['employment_type' => 'permanent', 'contract_months' => 12])->assertRedirect();
    expect(tplLatest()->contract_months)->toBeNull()
        ->and(tplLatest()->contractLabel())->toBe('Tetap');

    tplSave(['contract_months' => 0])->assertSessionHasErrors('contract_months');
    tplSave(['contract_months' => 61])->assertSessionHasErrors('contract_months');
    tplSave(['employment_type' => 'freelance'])->assertSessionHasErrors('employment_type');
});

it('needs the contract type, its length and the direct superior to send', function () {
    tplSave(['employment_type' => null, 'manager_employee_id' => null, 'send' => 1])
        ->assertSessionHasErrors(['employment_type', 'manager_employee_id']);

    tplSave(['employment_type' => 'contract', 'contract_months' => null, 'send' => 1])->assertSessionHasErrors('contract_months');

    tplSave(['employment_type' => 'probation', 'contract_months' => null, 'send' => 1])->assertSessionHasNoErrors();
});

it('takes the direct superior only from the active staff of the same company', function () {
    $elsewhere = Employee::factory()->forCompany(Company::factory()->create())->create();
    $gone = Employee::factory()->forCompany($this->company)->create(['employment_status' => EmploymentStatus::Inactive]);

    tplSave(['manager_employee_id' => $elsewhere->id])->assertSessionHasErrors('manager_employee_id');
    tplSave(['manager_employee_id' => $gone->id])->assertSessionHasErrors('manager_employee_id');
    tplSave(['manager_employee_id' => 999999])->assertSessionHasErrors('manager_employee_id');
    tplSave(['manager_employee_id' => $this->mira->id])->assertSessionHasNoErrors();

    expect(Requisition::count())->toBe(1);

    // A superior who has left since the draft was saved is caught when it is sent.
    $draft = tplLatest();
    $this->mira->update(['employment_status' => EmploymentStatus::Inactive]);

    $this->actingAs($this->rudi->user)->put(route('recruitment.update', $draft), tplFields(['manager_employee_id' => $this->mira->id, 'send' => 1]))
        ->assertSessionHasErrors('manager_employee_id');
});

// --- The template's sections ---

it('drops blank lines and empty subtitles', function () {
    tplSave([
        'benefits' => ['  ', 'THR sesuai ketentuan.', ''],
        'responsibilities' => [
            ['title' => 'Barang keluar', 'items' => ['', ' Periksa barang. ']],
            ['title' => '  ', 'items' => ['   ']],
        ],
        'checklist' => ['education' => [' ', 'Minimal SMA/SMK'], 'experience' => [], 'hard' => [], 'soft' => [], 'bonus' => ['']],
    ])->assertRedirect();

    $requisition = tplLatest();

    expect($requisition->benefits)->toBe(['THR sesuai ketentuan.'])
        ->and($requisition->responsibilities)->toBe([['title' => 'Barang keluar', 'items' => ['Periksa barang.']]])
        ->and($requisition->checklist['education'])->toBe(['Minimal SMA/SMK'])
        ->and($requisition->checklist['bonus'])->toBe([]);
});

it('needs the posting and the checklist to follow the template before it is sent', function () {
    $cases = [
        'no Tentang Posisi' => [['about' => ''], 'about'],
        'no responsibility' => [['responsibilities' => []], 'responsibilities'],
        'a subtitle without a title' => [['responsibilities' => [['title' => '', 'items' => ['Periksa barang.']]]], 'responsibilities'],
        'a subtitle without a line' => [['responsibilities' => [['title' => 'Barang keluar', 'items' => []]]], 'responsibilities'],
        'no qualification' => [['checklist' => ['education' => [], 'experience' => [], 'hard' => ['Excel'], 'soft' => [], 'bonus' => []]], 'qualification'],
        'no skills' => [['checklist' => ['education' => ['SMA'], 'experience' => [], 'hard' => [], 'soft' => [], 'bonus' => []]], 'skills'],
    ];

    foreach ($cases as $name => [$overrides, $error]) {
        tplSave([...$overrides, 'send' => 1])->assertSessionHasErrors($error);

        expect((string) tplLatest()->refresh()->workflow->current_stage)->toBe('draft', $name);
    }
});

it('accepts no benefits and no Nilai Plus, and one line is enough for a required section', function () {
    tplSave([
        'benefits' => [],
        'checklist' => ['education' => [], 'experience' => ['Minimal 1 tahun'], 'hard' => [], 'soft' => ['Teliti'], 'bonus' => []],
        'send' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect((string) tplLatest()->refresh()->workflow->current_stage)->toBe('submitted');
});

it('limits how many lines and how long', function () {
    tplSave(['benefits' => array_fill(0, 21, 'Satu benefit.')])->assertSessionHasErrors('benefits');
    tplSave(['benefits' => [str_repeat('a', 301)]])->assertSessionHasErrors('benefits.0');
    tplSave(['about' => str_repeat('a', 3001)])->assertSessionHasErrors('about');
    tplSave(['responsibilities' => array_fill(0, 13, ['title' => 'Tugas', 'items' => ['Satu.']])])->assertSessionHasErrors('responsibilities');
    tplSave(['checklist' => ['hard' => array_fill(0, 21, 'Excel')]])->assertSessionHasErrors('checklist.hard');
});

// --- The base template ---

it('hands the wizard the template, the people to choose from and today', function () {
    $this->actingAs($this->rudi->user)->get(route('recruitment.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recruitment/form')
            ->where('today', tplToday()->toDateString())
            ->where('ownEmployeeId', $this->rudi->id)
            ->where('template.about', '')
            ->has('template.benefits', 7)
            ->where('template.checklist.hard', [])
            ->has('employmentTypes', 4)
            ->where('employmentTypes.1', ['value' => 'contract', 'label' => 'Kontrak', 'needsMonths' => true])
            ->has('managers', 3)
            ->where('managers.0.company_id', $this->company->id));
});

it('keeps a requisition\'s own lines when the template changes afterwards', function () {
    tplSave(['send' => 1])->assertRedirect();
    $requisition = tplLatest();

    app(RequisitionTemplate::class)->save(['benefits' => ['Hanya satu benefit baru.']]);

    expect($requisition->refresh()->benefits)->toBe(['BPJS Kesehatan & BPJS Ketenagakerjaan.']);

    $this->actingAs($this->rudi->user)->get(route('recruitment.create'))
        ->assertInertia(fn (Assert $page) => $page->where('template.benefits', ['Hanya satu benefit baru.']));

    $this->actingAs($this->mira->user)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.contractLabel', 'Kontrak 1 tahun')
            ->where('requisition.manager', 'Rudi')
            ->where('requisition.benefits', ['BPJS Kesehatan & BPJS Ketenagakerjaan.'])
            ->where('requisition.checklist.education', ['Minimal SMA/SMK'])
            ->has('requisition.responsibilities', 1));
});

it('keeps a cleared list cleared instead of bringing the built-in lines back', function () {
    $template = app(RequisitionTemplate::class);

    expect($template->current()['benefits'])->toHaveCount(7);

    $template->save(['benefits' => []]);

    expect($template->current()['benefits'])->toBe([])
        ->and($template->startingContent()['benefits'])->toBe([]);
});
