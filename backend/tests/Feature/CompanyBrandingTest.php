<?php

use App\Enums\Role;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Branding;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * A company's colour and logo, and the colour for the combined view, set from the admin panel and
 * worn by the app.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    $this->admin = User::factory()->create(['company_id' => null]);
    $this->admin->assignRole(Role::SystemAdmin->value);
    $this->acme = Company::factory()->create(['name' => 'Acme']);
});

/** A real 1x1 PNG: the upload field checks that it is an image, and there is no GD here to make one. */
function logoFile(string $name = 'logo.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    ));
}

it('saves a company\'s colour and logo from its form', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCompany::class, ['record' => $this->acme->getKey()])
        ->fillForm(['brand_color' => '#1d4ed8', 'logo_path' => logoFile()])
        ->call('save')
        ->assertHasNoFormErrors();

    $acme = $this->acme->fresh();

    expect($acme->brand_color)->toBe('#1d4ed8')
        ->and($acme->logo_path)->toStartWith('company-logos/')
        ->and($acme->logoUrl())->toContain('company-logos/');
    Storage::disk('public')->assertExists($acme->logo_path);
});

it('saves a separate dark-mode logo, and falls back to the light one when there is none', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCompany::class, ['record' => $this->acme->getKey()])
        ->fillForm(['logo_path' => logoFile(), 'logo_dark_path' => logoFile('logo-dark.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $acme = $this->acme->fresh();

    expect($acme->logo_dark_path)->toStartWith('company-logos/')
        ->and($acme->logoDarkUrl())->toContain('company-logos/')
        ->and($acme->logoDarkUrl())->not->toBe($acme->logoUrl());
    Storage::disk('public')->assertExists($acme->logo_dark_path);

    $this->actingAs($this->admin)->withSession([SetActiveCompany::SESSION_KEY => (string) $acme->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branding.logoDarkUrl', fn ($url) => str_contains($url, 'company-logos/')));

    $acme->update(['logo_dark_path' => null]);
    expect($acme->fresh()->logoDarkUrl())->toBeNull();
});

it('lets a company be created with a colour, and without one', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateCompany::class)
        ->fillForm(['code' => 'BLU', 'name' => 'Biru', 'brand_color' => '#0000ff', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();
    Livewire::test(CreateCompany::class)
        ->fillForm(['code' => 'PLN', 'name' => 'Polos', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Company::where('code', 'BLU')->value('brand_color'))->toBe('#0000ff')
        ->and(Company::where('code', 'PLN')->value('brand_color'))->toBeNull();
});

it('refuses a colour that is not a six-digit hex, and a logo that is not an image', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCompany::class, ['record' => $this->acme->getKey()])
        ->fillForm(['brand_color' => 'merah'])
        ->call('save')
        ->assertHasFormErrors(['brand_color']);

    Livewire::test(EditCompany::class, ['record' => $this->acme->getKey()])
        ->fillForm(['logo_path' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    expect($this->acme->fresh()->brand_color)->toBeNull();
});

it('sets the colour for "Semua perusahaan" from the companies list, and logs it', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListCompanies::class)
        ->callAction('groupColor', ['color' => '#0f766e'])
        ->assertHasNoActionErrors();

    expect(app(Branding::class)->groupColor())->toBe('#0f766e');

    $log = Activity::where('log_name', 'settings')->latest('id')->first();
    expect($log->properties['after'])->toBe('#0f766e')->and($log->causer_id)->toBe($this->admin->id);

    Livewire::test(ListCompanies::class)->callAction('groupColor', ['color' => null]);
    expect(app(Branding::class)->groupColor())->toBeNull();
});

it('only lets people who manage companies at the group colour', function () {
    $hr = User::factory()->create(['company_id' => null]);
    $hr->assignRole(Role::HrAdmin->value);

    $this->actingAs($hr)->get('/admin/companies')->assertForbidden();
});

it('gives the app the colour and logo of the company being looked at', function () {
    $this->acme->update(['brand_color' => '#1d4ed8', 'logo_path' => 'company-logos/acme.png']);
    app(Branding::class)->setGroupColor('#0f766e');

    $this->actingAs($this->admin)->withSession([SetActiveCompany::SESSION_KEY => (string) $this->acme->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.name', 'Acme')
            ->where('branding.color', '#1d4ed8')
            ->where('branding.logoUrl', fn ($url) => str_contains($url, 'company-logos/acme.png')));
});

it('gives the group colour, and no logo, when all companies are viewed together', function () {
    $this->acme->update(['brand_color' => '#1d4ed8', 'logo_path' => 'company-logos/acme.png']);
    app(Branding::class)->setGroupColor('#0f766e');

    $this->actingAs($this->admin)->withSession([SetActiveCompany::SESSION_KEY => SetActiveCompany::COMBINED])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.color', '#0f766e')
            ->where('branding.logoUrl', null)
            ->where('branding.name', null));
});

it('wears the default look when nothing is set', function () {
    $this->actingAs($this->admin)->withSession([SetActiveCompany::SESSION_KEY => (string) $this->acme->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branding.color', null)->where('branding.logoUrl', null));
});

it('shows a company-bound account its own company\'s colour', function () {
    $this->acme->update(['brand_color' => '#b45309']);
    $other = Company::factory()->create(['brand_color' => '#000000']);
    $staff = User::factory()->create(['company_id' => $this->acme->id]);
    $staff->assignRole(Role::Employee->value);

    $this->actingAs($staff)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branding.color', '#b45309'));
    unset($other);
});

it('ignores a stored group colour that is not a valid hex', function () {
    app(AppSettings::class)->set(Branding::GROUP_COLOR_KEY, 'url(javascript:x)');

    expect(app(Branding::class)->groupColor())->toBeNull();

    app(Branding::class)->setGroupColor('not-a-colour');
    expect(app(Branding::class)->groupColor())->toBeNull();
});
