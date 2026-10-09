<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Global search (CORE-SRC): pages, employees, leave requests and documents, only what the
 * signed-in person could already open.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->other = Company::factory()->create();
    $this->hr = searchUser(Role::HrAdmin, $this->company);
    $this->budi = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Budi Santoso', 'employee_number' => 'EMP-0042', 'email' => 'budi@example.com']);
});

function searchUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @return array<string, array<int, array<string, mixed>>> item lists by group key
 */
function searchFor(User $user, string $q): array
{
    $groups = test()->actingAs($user)->getJson(route('search', ['q' => $q]))->assertOk()->json('groups');

    return collect($groups)->mapWithKeys(fn (array $group): array => [$group['key'] => $group['items']])->all();
}

function searchDocument(Employee $employee, string $title, bool $restricted = false): EmployeeDocument
{
    return EmployeeDocument::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'category' => 'contract',
        'title' => $title, 'is_restricted' => $restricted,
    ]);
}

it('finds an employee by name, number or email, case-insensitively', function () {
    foreach (['budi', 'SANTOSO', 'emp-0042', 'BUDI@EXAMPLE'] as $q) {
        $found = searchFor($this->hr, $q)['employees'] ?? [];

        expect($found)->toHaveCount(1)
            ->and($found[0]['title'])->toBe('Budi Santoso')
            ->and($found[0]['url'])->toBe("/employees/{$this->budi->id}");
    }
});

it('says nothing for a query that is too short or matches nothing', function () {
    expect(searchFor($this->hr, 'b'))->toBe([])
        ->and(searchFor($this->hr, ''))->toBe([])
        ->and(searchFor($this->hr, 'zzzzqqq'))->toBe([]);
});

it('only searches the company the person works in', function () {
    Employee::factory()->forCompany($this->other)->create(['full_name' => 'Budi Lain Perusahaan']);

    $found = searchFor($this->hr, 'budi')['employees'];

    expect($found)->toHaveCount(1)->and($found[0]['title'])->toBe('Budi Santoso');
});

it('treats % and _ in the query as plain characters', function () {
    Employee::factory()->forCompany($this->company)->create(['full_name' => 'Rina 50% Diskon']);

    expect(searchFor($this->hr, '%'))->toBe([])
        ->and(searchFor($this->hr, '50%')['employees'])->toHaveCount(1)
        ->and(searchFor($this->hr, 'b_di'))->toBe([]);
});

it('does not search the directory for someone who may not open it', function () {
    $staff = searchUser(Role::Employee, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $staff->id, 'full_name' => 'Staf Biasa']);

    expect(searchFor($staff, 'budi'))->toBe([])
        ->and(searchFor($staff, 'staf'))->toBe([]);
});

it('finds a leave request by reference or by the employee, and shows an employee only their own', function () {
    $user = searchUser(Role::Employee, $this->company);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $user->id, 'full_name' => 'Staf Kecil']);

    foreach ([$mine, $this->budi] as $employee) {
        $this->actingAs($this->hr)->post(route('leave-requests.store'), [
            'employee_id' => $employee->id, 'leave_type' => 'annual', 'start_on' => '2026-11-02', 'end_on' => '2026-11-03',
        ])->assertRedirect();
    }

    $ownLeave = LeaveRequest::where('employee_id', $mine->id)->firstOrFail();
    $budiLeave = LeaveRequest::where('employee_id', $this->budi->id)->firstOrFail();

    $hrSees = searchFor($this->hr, $budiLeave->reference())['leave'];
    expect($hrSees)->toHaveCount(1)->and($hrSees[0]['url'])->toBe("/leave-requests?open={$budiLeave->id}");
    expect(searchFor($this->hr, 'budi')['leave'])->toHaveCount(1);

    expect(searchFor($user, strtolower($ownLeave->reference()))['leave'])->toHaveCount(1)
        ->and(searchFor($user, $budiLeave->reference()))->not->toHaveKey('leave')
        ->and(searchFor($user, 'budi'))->not->toHaveKey('leave');
});

it('finds documents the person may read, and never reveals a restricted title', function () {
    searchDocument($this->budi, 'Kontrak kerja Budi');
    searchDocument($this->budi, 'Kontrak rahasia', restricted: true);

    $manager = searchUser(Role::HrAdmin, $this->company);
    $manager->revokePermissionTo('view-sensitive-data');
    $manager->roles()->detach();
    $manager->givePermissionTo('view-documents', 'view-employees');

    $titles = fn (User $user): array => collect(searchFor($user, 'kontrak')['documents'] ?? [])->pluck('title')->all();

    expect($titles($this->hr))->toContain('Kontrak kerja Budi', 'Kontrak rahasia')
        ->and($titles($manager))->toBe(['Kontrak kerja Budi']);

    $found = searchFor($this->hr, 'kerja Budi')['documents'];
    expect($found[0]['url'])->toBe("/employees/{$this->budi->id}?tab=documents");
});

it('lets an employee find their own document but not a colleague\'s', function () {
    $user = searchUser(Role::Employee, $this->company);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $user->id]);
    searchDocument($mine, 'Ijazah saya');
    searchDocument($this->budi, 'Ijazah Budi');

    expect(collect(searchFor($user, 'ijazah')['documents'])->pluck('title')->all())->toBe(['Ijazah saya']);
});

it('offers the pages the person may open, and not the ones they may not', function () {
    expect(collect(searchFor($this->hr, 'absensi')['pages'])->pluck('title')->all())
        ->toContain('Kalender', 'Rekap absensi', 'Masalah absensi');

    $staff = searchUser(Role::Employee, $this->company);

    expect(searchFor($staff, 'absensi'))->not->toHaveKey('pages')
        ->and(collect(searchFor($staff, 'pengajuan')['pages'])->pluck('title')->all())->toBe(['Pengajuan', 'Buat pengajuan']);
});

it('is for signed-in people only', function () {
    $this->getJson(route('search', ['q' => 'budi']))->assertUnauthorized();
});

it('caps each group', function () {
    Employee::factory()->count(8)->forCompany($this->company)->create(['full_name' => 'Sama Nama']);

    expect(searchFor($this->hr, 'sama nama')['employees'])->toHaveCount(5);
});
