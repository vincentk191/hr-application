<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseItem;
use App\Models\OffboardingItem;
use App\Models\User;
use App\Support\Offboarding\OffboardingCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The checklist stage of an offboarding case (OFF-ITM, OFF-CHK): each department's supervisor picks the items
 * that apply, confirms the list and ticks the items off, and HR moves the case on once everything is done.
 *
 * Mira (Management) manages Rudi, who manages Sinta, the leaver. The department supervisors are Ivan (IT) and
 * Fani (Fasilitas), neither of whom is above Sinta. HR has no supervisor, and Keuangan's is Sinta herself.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->hr = ckUser(Role::HrAdmin, $this->company);
    $this->mira = ckMember('Mira', null, Role::Management, $this->company);
    $this->rudi = ckMember('Rudi', $this->mira, Role::Supervisor, $this->company);
    $this->sinta = ckMember('Sinta', $this->rudi, Role::Employee, $this->company);
    $this->ivan = ckMember('Ivan', null, Role::Supervisor, $this->company);
    $this->fani = ckMember('Fani', null, Role::Supervisor, $this->company);

    $this->it = Department::factory()->forCompany($this->company)->create(['name' => 'IT', 'supervisor_employee_id' => $this->ivan->id]);
    $this->facilities = Department::factory()->forCompany($this->company)->create(['name' => 'Fasilitas', 'supervisor_employee_id' => $this->fani->id]);
    $this->hrDepartment = Department::factory()->forCompany($this->company)->create(['name' => 'HR']);
    $this->finance = Department::factory()->forCompany($this->company)->create(['name' => 'Keuangan', 'supervisor_employee_id' => $this->sinta->id]);
});

function ckUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function ckMember(string $name, ?Employee $manager, Role $role, Company $company): Employee
{
    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name,
        'manager_id' => $manager?->id,
        'user_id' => ckUser($role, $company)->id,
    ]);
}

/**
 * A case Management has approved, so its checklist is open.
 */
function checklistCase(): OffboardingCase
{
    $hr = test()->hr;

    test()->actingAs($hr)->post(route('offboarding.store'), [
        'employee_id' => test()->sinta->id, 'exit_type' => 'contract_end', 'notice_on' => '2026-11-02',
        'last_working_on' => '2026-12-02', 'reason' => 'Kontrak selesai', 'compensation_months' => 0, 'send' => '1',
    ])->assertRedirect();

    $case = OffboardingCase::query()->latest('id')->firstOrFail();
    test()->actingAs(test()->mira->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();

    return $case->refresh();
}

function ckGroup(OffboardingCase $case, string $name): OffboardingCaseDepartment
{
    return OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)->where('name', $name)->firstOrFail();
}

function ckMaster(string $name): OffboardingItem
{
    return OffboardingItem::query()->where('name', $name)->firstOrFail();
}

/**
 * Pick every master item of a department, then confirm the list.
 */
function ckPickAll(User $owner, OffboardingCase $case, OffboardingCaseDepartment $group): void
{
    foreach (OffboardingItem::query()->where('department_id', $group->department_id)->get() as $item) {
        test()->actingAs($owner)->post(route('offboarding.checklist.select', [$case, $group]), ['offboarding_item_id' => $item->id])->assertRedirect();
    }

    test()->actingAs($owner)->post(route('offboarding.checklist.confirm', [$case, $group]))->assertRedirect();
}

function ckTickAll(User $owner, OffboardingCaseDepartment $group): void
{
    foreach ($group->items()->get() as $item) {
        test()->actingAs($owner)->put(route('offboarding.checklist.done', $item), ['note' => 'Beres'])->assertRedirect();
    }
}

// --- Opening the checklist ---

it('makes the default items and a group per department when Management approves, owned by each supervisor', function () {
    expect(OffboardingItem::count())->toBe(0);

    $case = checklistCase();

    expect(OffboardingItem::count())->toBe(count(OffboardingCatalog::DEFAULTS))
        ->and(ckMaster('Laptop dan perangkat kerja dikembalikan')->department_id)->toBe($this->it->id)
        ->and(ckMaster('Seragam dan alat pelindung diri dikembalikan')->department_id)->toBe($this->facilities->id)
        ->and(ckMaster('Serah terima pekerjaan dan pengetahuan')->department_id)->toBeNull();

    $groups = OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)->get()->keyBy('name');

    expect($groups->keys()->sort()->values()->all())->toBe(['Fasilitas', 'HR', 'IT', 'Keuangan', 'Tanpa departemen'])
        ->and($groups['IT']->owner_user_id)->toBe($this->ivan->user_id)
        ->and($groups['Fasilitas']->owner_user_id)->toBe($this->fani->user_id)
        // HR has no supervisor, and Keuangan's is the person leaving: both fall to HR Admin.
        ->and($groups['HR']->owner_user_id)->toBeNull()
        ->and($groups['Keuangan']->owner_user_id)->toBeNull()
        ->and($groups['Tanpa departemen']->owner_user_id)->toBeNull();

    expect($this->ivan->user->notifications()->count())->toBe(1)
        ->and($this->fani->user->notifications()->count())->toBe(1)
        ->and($this->rudi->user->notifications()->count())->toBe(1);
});

it('never puts the defaults back over a company\'s own list', function () {
    OffboardingItem::factory()->create(['company_id' => $this->company->id, 'name' => 'Hanya satu', 'department_id' => $this->it->id]);

    $case = checklistCase();

    expect(OffboardingItem::count())->toBe(1)
        ->and(OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)->pluck('name')->all())->toBe(['IT']);
});

it('does not open a checklist for a case that is still waiting for Management', function () {
    $this->actingAs($this->hr)->post(route('offboarding.store'), [
        'employee_id' => $this->sinta->id, 'exit_type' => 'contract_end', 'notice_on' => '2026-11-02',
        'last_working_on' => '2026-12-02', 'reason' => 'Kontrak selesai', 'send' => '1',
    ])->assertRedirect();

    $case = OffboardingCase::latest('id')->firstOrFail();

    expect(OffboardingCaseDepartment::where('offboarding_case_id', $case->id)->count())->toBe(0);
    $this->actingAs($this->ivan->user)->get(route('offboarding.show', $case))->assertForbidden();
});

// --- Who sees it, who works it ---

it('shows the case to a department supervisor who is not above the leaver, once the checklist is open', function () {
    $case = checklistCase();

    $this->actingAs($this->ivan->user)->get(route('offboarding.show', $case))->assertOk();
    $this->actingAs($this->ivan->user)->get(route('offboarding.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('cases', 1));

    $outsider = ckMember('Luar', null, Role::Supervisor, $this->company);
    $this->actingAs($outsider->user)->get(route('offboarding.show', $case))->assertForbidden();
});

it('lets only a department\'s own supervisor pick its items, and HR pick where nobody owns them', function () {
    $case = checklistCase();
    $laptop = ckMaster('Laptop dan perangkat kerja dikembalikan');
    $itGroup = ckGroup($case, 'IT');
    $hrGroup = ckGroup($case, 'HR');
    $pick = fn (User $by, OffboardingCaseDepartment $group, OffboardingItem $item) => $this->actingAs($by)
        ->post(route('offboarding.checklist.select', [$case, $group]), ['offboarding_item_id' => $item->id]);

    $pick($this->fani->user, $itGroup, $laptop)->assertForbidden();
    $pick($this->hr, $itGroup, $laptop)->assertForbidden();
    $pick($this->ivan->user, $itGroup, $laptop)->assertRedirect();

    expect($itGroup->items()->count())->toBe(1);

    // HR owns the HR group, which has no supervisor.
    $pick($this->hr, $hrGroup, ckMaster('Wawancara keluar dilakukan'))->assertRedirect();
    $pick($this->ivan->user, $hrGroup, ckMaster('Wawancara keluar dilakukan'))->assertForbidden();

    // And not an item that belongs to another department.
    $pick($this->ivan->user, $itGroup, ckMaster('Wawancara keluar dilakukan'))->assertSessionHasErrors('item');
});

it('shows the owner only the items still to pick, and the leaver the list without any controls', function () {
    $case = checklistCase();
    $itGroup = ckGroup($case, 'IT');
    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.select', [$case, $itGroup]), ['offboarding_item_id' => ckMaster('Akses VPN dicabut')->id])->assertRedirect();

    $this->actingAs($this->ivan->user)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.checklist.groups', fn ($groups) => collect($groups)->firstWhere('name', 'IT')['canSelect'] === true
                && count(collect($groups)->firstWhere('name', 'IT')['available']) === 3
                && count(collect($groups)->firstWhere('name', 'IT')['items']) === 1
                && collect($groups)->firstWhere('name', 'Fasilitas')['canSelect'] === false));

    $this->actingAs($this->sinta->user)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.groups', fn ($groups) => collect($groups)->every(fn ($group) => $group['canWork'] === false && $group['available'] === [])));
});

// --- Confirming and working the list ---

it('locks the list once it is confirmed, even when no item applies', function () {
    $case = checklistCase();
    $facilities = ckGroup($case, 'Fasilitas');

    $this->actingAs($this->fani->user)->post(route('offboarding.checklist.confirm', [$case, $facilities]))->assertRedirect();

    expect($facilities->refresh()->confirmed_at)->not->toBeNull();

    $this->actingAs($this->fani->user)->post(route('offboarding.checklist.select', [$case, $facilities]), ['offboarding_item_id' => ckMaster('Kendaraan operasional diserahkan')->id])
        ->assertSessionHasErrors('group');
    $this->actingAs($this->fani->user)->post(route('offboarding.checklist.confirm', [$case, $facilities]))->assertSessionHasErrors('group');
});

it('takes an item off an unconfirmed list, but not off a confirmed one, except what was added for this case', function () {
    $case = checklistCase();
    $it = ckGroup($case, 'IT');
    $vpn = ckMaster('Akses VPN dicabut');

    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.select', [$case, $it]), ['offboarding_item_id' => $vpn->id])->assertRedirect();
    $item = $it->items()->firstOrFail();
    $this->actingAs($this->ivan->user)->delete(route('offboarding.checklist.remove', $item))->assertRedirect();

    expect($it->items()->count())->toBe(0);

    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.select', [$case, $it]), ['offboarding_item_id' => $vpn->id])->assertRedirect();
    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.confirm', [$case, $it]))->assertRedirect();
    $this->actingAs($this->ivan->user)->delete(route('offboarding.checklist.remove', $it->items()->firstOrFail()))->assertSessionHasErrors('item');

    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.adhoc', [$case, $it]), ['name' => 'Teruskan email ke atasan', 'kind' => 'process'])->assertRedirect();
    $adhoc = $it->items()->where('is_adhoc', true)->firstOrFail();
    $this->actingAs($this->ivan->user)->delete(route('offboarding.checklist.remove', $adhoc))->assertRedirect();

    expect($it->items()->count())->toBe(1);
});

it('lets the owner or HR add an item for this case alone, and nobody else', function () {
    $case = checklistCase();
    $it = ckGroup($case, 'IT');
    $add = fn (User $by) => $this->actingAs($by)->post(route('offboarding.checklist.adhoc', [$case, $it]), ['name' => 'Alihkan klien', 'kind' => 'process']);

    $add($this->fani->user)->assertForbidden();
    $add($this->ivan->user)->assertRedirect();
    $add($this->hr)->assertRedirect();

    expect($it->items()->where('is_adhoc', true)->count())->toBe(2);

    $this->actingAs($this->hr)->post(route('offboarding.checklist.adhoc', [$case, $it]), ['name' => '', 'kind' => 'process'])->assertSessionHasErrors('name');
    $this->actingAs($this->hr)->post(route('offboarding.checklist.adhoc', [$case, $it]), ['name' => 'X', 'kind' => 'lain'])->assertSessionHasErrors('kind');
});

it('lets only the owner tick an item, and only after the list is confirmed', function () {
    $case = checklistCase();
    $it = ckGroup($case, 'IT');
    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.select', [$case, $it]), ['offboarding_item_id' => ckMaster('Akses VPN dicabut')->id])->assertRedirect();
    $item = $it->items()->firstOrFail();

    $this->actingAs($this->ivan->user)->put(route('offboarding.checklist.done', $item), ['note' => 'x'])->assertSessionHasErrors('item');

    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.confirm', [$case, $it]))->assertRedirect();

    $this->actingAs($this->fani->user)->put(route('offboarding.checklist.done', $item))->assertForbidden();
    $this->actingAs($this->hr)->put(route('offboarding.checklist.done', $item))->assertForbidden();
    $this->actingAs($this->sinta->user)->put(route('offboarding.checklist.done', $item))->assertForbidden();

    $this->actingAs($this->ivan->user)->put(route('offboarding.checklist.done', $item), ['note' => 'Akun VPN dihapus 3 Des'])->assertRedirect();

    $item->refresh();
    expect($item->isDone())->toBeTrue()
        ->and($item->done_by_user_id)->toBe($this->ivan->user_id)
        ->and($item->note)->toBe('Akun VPN dihapus 3 Des');

    $this->actingAs($this->ivan->user)->put(route('offboarding.checklist.reopen', $item))->assertRedirect();
    expect($item->refresh()->isDone())->toBeFalse();
});

it('lets HR set aside a department with nothing in it, and take it back', function () {
    $case = checklistCase();
    $facilities = ckGroup($case, 'Fasilitas');

    $this->actingAs($this->fani->user)->put(route('offboarding.checklist.involvement', [$case, $facilities]), ['not_involved' => true])->assertForbidden();
    $this->actingAs($this->hr)->put(route('offboarding.checklist.involvement', [$case, $facilities]), ['not_involved' => true])->assertRedirect();

    expect($facilities->refresh()->not_involved)->toBeTrue()->and($facilities->isSettled())->toBeTrue();

    $this->actingAs($this->hr)->put(route('offboarding.checklist.involvement', [$case, $facilities]), ['not_involved' => false])->assertRedirect();
    expect($facilities->refresh()->isSettled())->toBeFalse();

    $this->actingAs($this->fani->user)->post(route('offboarding.checklist.select', [$case, $facilities]), ['offboarding_item_id' => ckMaster('Kendaraan operasional diserahkan')->id])->assertRedirect();
    $this->actingAs($this->hr)->put(route('offboarding.checklist.involvement', [$case, $facilities]), ['not_involved' => true])->assertSessionHasErrors('group');
});

it('will not mix up a group with another case', function () {
    $case = checklistCase();
    $other = OffboardingCase::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.confirm', [$other, ckGroup($case, 'IT')]))->assertNotFound();
});

// --- Sign-off waits for the checklist ---

it('keeps sign-off locked and says why, until every list is confirmed and every item ticked', function () {
    $case = checklistCase();

    $this->actingAs($this->hr)->post(route('offboarding.proceed', $case))
        ->assertSessionHasErrors(['proceed' => 'Kasus belum bisa lanjut ke sign-off: 5 departemen belum mengonfirmasi daftarnya.']);

    ckPickAll($this->ivan->user, $case, ckGroup($case, 'IT'));
    ckPickAll($this->fani->user, $case, ckGroup($case, 'Fasilitas'));
    ckPickAll($this->hr, $case, ckGroup($case, 'HR'));
    ckPickAll($this->hr, $case, ckGroup($case, 'Keuangan'));
    ckPickAll($this->hr, $case, ckGroup($case, 'Tanpa departemen'));

    $this->actingAs($this->hr)->post(route('offboarding.proceed', $case))
        ->assertSessionHasErrors('proceed');

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.checklist.progress.ready', false)
            ->where('case.checklist.progress.blockers', ['12 item belum dicentang pemiliknya.'])
            ->where('case.canProceed', true));

    ckTickAll($this->ivan->user, ckGroup($case, 'IT'));
    ckTickAll($this->fani->user, ckGroup($case, 'Fasilitas'));
    ckTickAll($this->hr, ckGroup($case, 'HR'));
    ckTickAll($this->hr, ckGroup($case, 'Keuangan'));
    ckTickAll($this->hr, ckGroup($case, 'Tanpa departemen'));

    expect(OffboardingCaseItem::where('offboarding_case_id', $case->id)->whereNull('done_at')->count())->toBe(0);

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.progress.ready', true)->where('case.checklist.progress.done', 12));

    $this->actingAs($this->ivan->user)->post(route('offboarding.proceed', $case))->assertForbidden();
    $this->actingAs($this->mira->user)->post(route('offboarding.proceed', $case))->assertForbidden();
    $this->actingAs($this->hr)->post(route('offboarding.proceed', $case))->assertRedirect();

    expect($case->refresh()->workflow->current_stage)->toBe('sign_off');
});

it('does not count a department set aside, and counts an item added later', function () {
    $case = checklistCase();

    foreach (['IT' => $this->ivan->user, 'Fasilitas' => $this->fani->user] as $name => $owner) {
        ckPickAll($owner, $case, ckGroup($case, $name));
        ckTickAll($owner, ckGroup($case, $name));
    }
    foreach (['HR', 'Keuangan', 'Tanpa departemen'] as $name) {
        $this->actingAs($this->hr)->put(route('offboarding.checklist.involvement', [$case, ckGroup($case, $name)]), ['not_involved' => true])->assertRedirect();
    }

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))->assertInertia(fn (Assert $page) => $page->where('case.checklist.progress.ready', true));

    // Something turns up after everyone thought they were done: it has to be ticked too.
    $this->actingAs($this->hr)->post(route('offboarding.checklist.adhoc', [$case, ckGroup($case, 'IT')]), ['name' => 'Alihkan nomor telepon kantor', 'kind' => 'digital'])->assertRedirect();
    $this->actingAs($this->hr)->post(route('offboarding.proceed', $case))->assertSessionHasErrors('proceed');
});

it('tells HR when a department confirms, and when the checklist is complete', function () {
    $case = checklistCase();
    $this->hr->notifications()->delete();

    foreach (['IT' => $this->ivan->user, 'Fasilitas' => $this->fani->user] as $name => $owner) {
        ckPickAll($owner, $case, ckGroup($case, $name));
        ckTickAll($owner, ckGroup($case, $name));
    }

    expect($this->hr->notifications()->get()->pluck('data.body')->all())->toContain('IT: daftar item sudah dikonfirmasi');

    foreach (['HR', 'Keuangan', 'Tanpa departemen'] as $name) {
        $this->actingAs($this->hr)->put(route('offboarding.checklist.involvement', [$case, ckGroup($case, $name)]), ['not_involved' => true])->assertRedirect();
    }

    $this->actingAs($this->hr)->get(route('offboarding.show', $case))->assertInertia(fn (Assert $page) => $page->where('case.checklist.progress.ready', true));
});

it('lets HR manage the master list from the admin panel', function () {
    // The panel is for parent-wide accounts that may enter it.
    $groupHr = User::factory()->create(['company_id' => null]);
    $groupHr->assignRole(Role::HrAdmin->value);

    $this->actingAs($groupHr)->get('/admin/offboarding-items')->assertOk();
    $this->actingAs($this->ivan->user)->get('/admin/offboarding-items')->assertForbidden();
});
