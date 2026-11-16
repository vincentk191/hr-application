<?php

use App\Enums\Role;
use App\Filament\Pages\ManageOnboardingEmailTemplate;
use App\Models\User;
use App\Support\Recruitment\OnboardingEmailTemplate;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin page "Email sambutan karyawan baru": who may open it and what it saves and refuses. What the accept dialog does
 * with the wording is tested with the offers, in RequisitionFilledTest.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function onboardingPage(Role $role = Role::HrAdmin)
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    test()->actingAs($user);

    return Livewire::test(ManageOnboardingEmailTemplate::class);
}

it('keeps everyone but a parent-wide HR Admin out', function () {
    foreach ([[Role::HrAdmin, true], [Role::SystemAdmin, false], [Role::Management, false], [Role::Employee, false]] as [$role, $allowed]) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->actingAs($user);

        expect(ManageOnboardingEmailTemplate::canAccess())->toBe($allowed);
    }
});

it('opens with the built-in wording, previewed with an example hire', function () {
    onboardingPage()->assertFormSet(fn (array $state): bool => $state['body'] === OnboardingEmailTemplate::BODY
        && str_contains($state['preview'], 'Halo Budi Santoso')
        && str_contains($state['preview'], OnboardingEmailTemplate::BLANK)
        && ! str_contains($state['preview'], '{'));
});

it('saves the wording and audits the change', function () {
    onboardingPage()
        ->fillForm(['subject' => 'Selamat datang, {nama}', 'body' => "Halo {nama},\n\nWaktu: {waktu_tempat}"])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(OnboardingEmailTemplate::class)->current())->toBe(['subject' => 'Selamat datang, {nama}', 'body' => "Halo {nama},\n\nWaktu: {waktu_tempat}"])
        ->and(Activity::query()->where('description', 'Onboarding email template changed')->count())->toBe(1);
});

it('refuses a placeholder that does not exist, so the salary can never be one', function () {
    onboardingPage()->fillForm(['body' => 'Gaji Anda {gaji}'])->call('save')->assertHasFormErrors(['body']);
    onboardingPage()->fillForm(['subject' => ''])->call('save')->assertHasFormErrors(['subject' => 'required']);

    expect(app(OnboardingEmailTemplate::class)->current()['body'])->toBe(OnboardingEmailTemplate::BODY);
});

it('puts the built-in wording back in the form, saving nothing until Simpan', function () {
    app(OnboardingEmailTemplate::class)->save('Lain', 'Lain');

    onboardingPage()->call('resetToDefault')->assertFormSet(fn (array $state): bool => $state['body'] === OnboardingEmailTemplate::BODY);

    expect(app(OnboardingEmailTemplate::class)->current()['body'])->toBe('Lain');
});
