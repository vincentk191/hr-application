<?php

use App\Enums\Role;
use App\Filament\Pages\ManageRecruitmentTemplate;
use App\Models\Company;
use App\Models\User;
use App\Support\Recruitment\RequisitionTemplate;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin page "Template rekrutmen" (REC-TPL-001): who may open it, what it saves, and that what it saves is what a
 * new requisition starts from.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function templateUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function templatePage()
{
    test()->actingAs(templateUser(Role::HrAdmin));

    return Livewire::test(ManageRecruitmentTemplate::class);
}

it('lets a parent-wide HR Admin open the page', function () {
    $this->actingAs(templateUser(Role::HrAdmin));

    expect(ManageRecruitmentTemplate::canAccess())->toBeTrue();
});

it('keeps everyone else out, including HR pinned to one company and a System Admin', function (Role $role, bool $pinned) {
    $this->actingAs(templateUser($role, $pinned ? Company::factory()->create() : null));

    expect(ManageRecruitmentTemplate::canAccess())->toBeFalse();
})->with([
    'HR Admin of one company' => [Role::HrAdmin, true],
    'System Admin' => [Role::SystemAdmin, false],
    'Management' => [Role::Management, false],
    'Supervisor' => [Role::Supervisor, false],
    'Employee' => [Role::Employee, false],
]);

it('opens with the built-in benefits and empty checklist lines', function () {
    templatePage()
        ->assertFormSet(fn (array $state): bool => count(explode("\n", $state['benefits'])) === 7
            && str_contains($state['benefits'], 'BPJS Kesehatan')
            && $state['education'] === '' && $state['hard'] === '');
});

it('saves a line per point, trims blanks and shows it as the template', function () {
    templatePage()
        ->fillForm([
            'benefits' => "  THR sesuai ketentuan.  \n\n   \nBPJS.",
            'education' => 'Minimal SMA/SMK',
            'experience' => '',
            'hard' => "Excel\nWord",
            'soft' => 'Teliti',
            'bonus' => 'Sertifikasi komputer',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(RequisitionTemplate::class)->current())->toBe([
        'benefits' => ['THR sesuai ketentuan.', 'BPJS.'],
        'education' => ['Minimal SMA/SMK'],
        'experience' => [],
        'hard' => ['Excel', 'Word'],
        'soft' => ['Teliti'],
        'bonus' => ['Sertifikasi komputer'],
    ]);

    $change = Activity::query()->where('log_name', 'settings')->where('description', 'Recruitment template changed')->firstOrFail();

    expect($change->properties['after']['hard'])->toBe(['Excel', 'Word'])
        ->and($change->properties['before']['benefits'])->toHaveCount(7);
});

it('keeps a cleared list cleared', function () {
    templatePage()->fillForm(['benefits' => ''])->call('save')->assertHasNoFormErrors();

    expect(app(RequisitionTemplate::class)->current()['benefits'])->toBe([]);

    Livewire::test(ManageRecruitmentTemplate::class)->assertFormSet(['benefits' => '']);
});

it('refuses too many lines or a line that is too long', function () {
    templatePage()
        ->fillForm(['benefits' => implode("\n", array_fill(0, 21, 'Satu benefit.'))])
        ->call('save')
        ->assertHasFormErrors(['benefits']);

    templatePage()
        ->fillForm(['hard' => str_repeat('a', 301)])
        ->call('save')
        ->assertHasFormErrors(['hard']);

    expect(Activity::query()->where('description', 'Recruitment template changed')->count())->toBe(0);
});

it('starts a new requisition from what was saved', function () {
    templatePage()
        ->fillForm(['benefits' => 'Hanya satu benefit.', 'education' => 'Minimal D3'])
        ->call('save');

    expect(app(RequisitionTemplate::class)->startingContent())->toBe([
        'about' => '',
        'responsibilities' => [],
        'benefits' => ['Hanya satu benefit.'],
        'checklist' => ['education' => ['Minimal D3'], 'experience' => [], 'hard' => [], 'soft' => [], 'bonus' => []],
    ]);
});
