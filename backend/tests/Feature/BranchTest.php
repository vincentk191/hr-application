<?php

use App\Enums\Role;
use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Cabang, the offices a company runs: set by the System Admin, one company each.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->admin = branchUser(Role::SystemAdmin);
});

function branchUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

it('lets the system admin add a Cabang to a company when a new office opens', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateBranch::class)
        ->fillForm(['company_id' => $this->company->id, 'name' => 'Surabaya'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Branch::acrossCompanies()->where('company_id', $this->company->id)->pluck('name')->all())->toBe(['Surabaya']);

    $branch = Branch::acrossCompanies()->firstOrFail();

    Livewire::test(EditBranch::class, ['record' => $branch->getKey()])
        ->fillForm(['name' => 'Surabaya Timur'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($branch->fresh()->name)->toBe('Surabaya Timur');
});

it('keeps a Cabang name unique within a company, but not across companies', function () {
    Branch::factory()->forCompany($this->company)->create(['name' => 'Medan']);
    $other = Company::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(CreateBranch::class)
        ->fillForm(['company_id' => $this->company->id, 'name' => 'Medan'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    Livewire::test(CreateBranch::class)
        ->fillForm(['company_id' => $other->id, 'name' => 'Medan'])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('asks for at least one Cabang when a company is created, and files them under it', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateCompany::class)
        ->fillForm(['code' => 'NEW', 'name' => 'Baru', 'is_active' => true, 'branches' => []])
        ->call('create')
        ->assertHasFormErrors(['branches']);

    Livewire::test(CreateCompany::class)
        ->fillForm(['code' => 'NEW', 'name' => 'Baru', 'is_active' => true, 'branches' => [['name' => 'Pusat'], ['name' => 'Pusat']]])
        ->call('create')
        ->assertHasFormErrors();

    expect(Company::where('code', 'NEW')->exists())->toBeFalse();
});

it('keeps the Cabang screen to whoever manages companies', function () {
    $this->actingAs(branchUser(Role::HrAdmin, $this->company))->get('/admin/branches')->assertForbidden();

    $this->actingAs($this->admin)->get('/admin/branches')->assertOk();
});

it('will not delete a Cabang that still has employees', function () {
    $branch = Branch::factory()->forCompany($this->company)->create();
    $empty = Branch::factory()->forCompany($this->company)->create();
    Employee::factory()->inBranch($branch)->create();

    $gate = Gate::forUser($this->admin);

    expect($gate->denies('delete', $branch))->toBeTrue()
        ->and($gate->allows('delete', $empty))->toBeTrue()
        ->and(Gate::forUser(branchUser(Role::HrAdmin, $this->company))->denies('update', $branch))->toBeTrue();
});
