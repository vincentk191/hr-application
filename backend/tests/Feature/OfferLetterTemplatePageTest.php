<?php

use App\Enums\Role;
use App\Filament\Pages\ManageOfferLetterTemplate;
use App\Models\Company;
use App\Models\User;
use App\Support\Recruitment\OfferLetterTemplate;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin page "Template surat penawaran" (REC-OFR): who may open it, what it saves and refuses, and that the signers are
 * kept apart from the wording. What the letters and emails do with it is tested with the offers, in
 * RequisitionOfferLetterTest.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function offerLetterAdmin(Role $role = Role::HrAdmin, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function offerLetterPage()
{
    test()->actingAs(offerLetterAdmin());

    return Livewire::test(ManageOfferLetterTemplate::class);
}

it('lets a parent-wide HR Admin open the page and keeps everyone else out', function () {
    $this->actingAs(offerLetterAdmin());
    expect(ManageOfferLetterTemplate::canAccess())->toBeTrue();

    foreach ([[Role::HrAdmin, true], [Role::SystemAdmin, false], [Role::Management, false], [Role::Supervisor, false], [Role::Employee, false]] as [$role, $pinned]) {
        $this->actingAs(offerLetterAdmin($role, $pinned ? Company::factory()->create() : null));
        expect(ManageOfferLetterTemplate::canAccess())->toBeFalse();
    }
});

it('opens with the built-in wording and previews filled with an example offer', function () {
    offerLetterPage()
        ->assertFormSet(fn (array $state): bool => $state['title'] === OfferLetterTemplate::TITLE
            && $state['opening'] === OfferLetterTemplate::OPENING
            && $state['closing'] === OfferLetterTemplate::CLOSING
            && $state['email_body'] === OfferLetterTemplate::EMAIL_BODY
            && str_contains($state['letter_preview'], 'SURAT PENAWARAN KERJA')
            && str_contains($state['letter_preview'], 'sebagai Staf Gudang')
            && ! str_contains($state['letter_preview'], '{')
            && str_contains($state['email_preview'], 'Halo Budi Santoso,')
            && str_contains($state['email_preview'], 'Gaji pokok/bulan  : Rp 4.500.000')
            && ! str_contains($state['email_preview'], '{'));
});

it('saves the wording and the signer of each company, and audits the change', function () {
    $company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $other = Company::factory()->create(['name' => 'PT Lain']);

    offerLetterPage()
        ->fillForm([
            'title' => 'Surat tawaran',
            'subject' => 'Tawaran {posisi}',
            'opening' => "Yth. {nama},\n\nKami menawarkan {posisi} mulai {tanggal_mulai}.",
            'closing' => 'Salam, {perusahaan}',
            'email_subject' => 'Tawaran {posisi} — {perusahaan}',
            'email_body' => "Halo {nama},\n\n{ketentuan}",
            'signers' => [
                $company->id => ['name' => 'Ratna Dewi', 'title' => 'Kepala HR'],
                $other->id => ['name' => '', 'title' => ''],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $template = app(OfferLetterTemplate::class);

    expect($template->current())->toMatchArray([
        'title' => 'Surat tawaran',
        'subject' => 'Tawaran {posisi}',
        'opening' => "Yth. {nama},\n\nKami menawarkan {posisi} mulai {tanggal_mulai}.",
        'closing' => 'Salam, {perusahaan}',
        'email_subject' => 'Tawaran {posisi} — {perusahaan}',
        'email_body' => "Halo {nama},\n\n{ketentuan}",
        'signers' => [$company->id => ['name' => 'Ratna Dewi', 'title' => 'Kepala HR']],
    ])
        ->and($template->signerFor($company->id))->toBe(['name' => 'Ratna Dewi', 'title' => 'Kepala HR'])
        ->and($template->signerFor($other->id))->toBe(['name' => null, 'title' => 'HR'])
        ->and(Activity::query()->where('description', 'Offer letter template changed')->count())->toBe(1);
});

it('refuses a placeholder that does not exist, and an email without the terms', function () {
    offerLetterPage()->fillForm(['opening' => 'Yth. {nama}, tawaran {gaji_bersih}'])->call('save')->assertHasFormErrors(['opening']);
    offerLetterPage()->fillForm(['closing' => 'Salam {kantor}'])->call('save')->assertHasFormErrors(['closing']);
    offerLetterPage()->fillForm(['subject' => '{ketentuan}'])->call('save')->assertHasFormErrors(['subject']);
    offerLetterPage()->fillForm(['email_subject' => 'Tawaran {kantor}'])->call('save')->assertHasFormErrors(['email_subject']);
    offerLetterPage()->fillForm(['email_body' => 'Halo {nama}, ada tawaran untuk Anda.'])->call('save')->assertHasFormErrors(['email_body']);
    offerLetterPage()->fillForm(['email_body' => 'Halo {nama}, {ketentuan} {nomor_hp}'])->call('save')->assertHasFormErrors(['email_body']);
    offerLetterPage()->fillForm(['title' => ''])->call('save')->assertHasFormErrors(['title' => 'required']);

    expect(app(OfferLetterTemplate::class)->current()['opening'])->toBe(OfferLetterTemplate::OPENING);
});

it('puts the built-in wording back in the form, keeping the signers and saving nothing until Simpan', function () {
    $company = Company::factory()->create();
    app(OfferLetterTemplate::class)->save([
        'title' => 'Lain',
        'subject' => 'Lain',
        'opening' => 'Lain',
        'closing' => 'Lain',
        'email_subject' => 'Lain',
        'email_body' => '{ketentuan}',
        'signers' => [$company->id => ['name' => 'Ratna Dewi', 'title' => 'Kepala HR']],
    ]);

    offerLetterPage()
        ->call('resetToDefault')
        ->assertFormSet(fn (array $state): bool => $state['opening'] === OfferLetterTemplate::OPENING
            && $state['email_body'] === OfferLetterTemplate::EMAIL_BODY
            && $state['signers'][$company->id]['name'] === 'Ratna Dewi');

    expect(app(OfferLetterTemplate::class)->current()['opening'])->toBe('Lain');
});
