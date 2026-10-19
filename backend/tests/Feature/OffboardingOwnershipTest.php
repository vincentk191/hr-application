<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingItem;
use App\Models\Position;
use App\Models\User;
use App\Support\Offboarding\OffboardingCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Who owns an offboarding item, across companies (OFF-ITM-007): a named person, from any company, or else the
 * supervisor of the item's department, or else HR. The owner sees only what they need to cross their items off.
 *
 * Company B has the leaver, Sinta. Ivan works in IT at company A, and owns two items on company B's cases.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->a = Company::factory()->create(['code' => 'CMP-A']);
    $this->b = Company::factory()->create(['code' => 'CMP-B']);

    $this->hrB = owUser(Role::HrAdmin, $this->b);
    $this->hrA = owUser(Role::HrAdmin, $this->a);
    $this->miraB = owMember('Mira', null, Role::Management, $this->b);
    $this->rudiB = owMember('Rudi', $this->miraB, Role::Supervisor, $this->b);
    $this->sintaB = owMember('Sinta', $this->rudiB, Role::Employee, $this->b);

    $itA = Department::factory()->forCompany($this->a)->create(['name' => 'IT']);
    $title = Position::factory()->forDepartment($itA)->create(['name' => 'IT Support']);
    $this->ivan = Employee::factory()->forCompany($this->a)->create([
        'full_name' => 'Ivan', 'department_id' => $itA->id, 'position_id' => $title->id, 'user_id' => owUser(Role::Employee, $this->a)->id,
    ]);

    $this->laptop = OffboardingItem::factory()->create(['name' => 'Laptop dikembalikan', 'department_name' => 'IT', 'owner_employee_id' => $this->ivan->id]);
    $this->vpn = OffboardingItem::factory()->create(['name' => 'VPN dicabut', 'department_name' => 'IT', 'owner_employee_id' => $this->ivan->id]);
    $this->badge = OffboardingItem::factory()->create(['name' => 'Kartu dikembalikan', 'department_name' => 'HR']);
});

function owUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

function owMember(string $name, ?Employee $manager, Role $role, Company $company): Employee
{
    return Employee::factory()->forCompany($company)->create([
        'full_name' => $name, 'manager_id' => $manager?->id, 'user_id' => owUser($role, $company)->id,
    ]);
}

/**
 * A case in company B whose checklist is open.
 */
function owCase(?Employee $leaver = null): OffboardingCase
{
    $leaver ??= test()->sintaB;

    test()->actingAs(test()->hrB)->post(route('offboarding.store'), [
        'employee_id' => $leaver->id, 'exit_type' => 'contract_end', 'notice_on' => '2026-11-02', 'last_working_on' => '2026-12-02',
        'reason' => 'Rahasia: kinerja', 'compensation_months' => 2, 'compensation_reason' => 'Rahasia: kompensasi', 'send' => '1',
    ])->assertRedirect();

    $case = OffboardingCase::query()->latest('id')->firstOrFail();
    test()->actingAs(test()->miraB->user)->put(route('offboarding.decide', $case), ['decision' => 'approve'])->assertRedirect();

    return $case->refresh();
}

function owGroup(OffboardingCase $case, string $owner): OffboardingCaseDepartment
{
    return OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)
        ->when($owner === 'hr', fn ($query) => $query->whereNull('owner_user_id'), fn ($query) => $query->where('owner_user_id', $owner))
        ->firstOrFail();
}

// --- Who owns an item ---

it('gives a named person their items in one group, with their job title and department from their record', function () {
    $case = owCase();
    $group = owGroup($case, (string) $this->ivan->user_id);

    expect($group->name)->toBe('IT')
        ->and($group->owner_title)->toBe('IT Support')
        ->and($group->owner_department)->toBe('IT')
        ->and($group->item_ids)->toBe([$this->laptop->id, $this->vpn->id]);

    // The department's supervisor in the leaver's company is not asked: a named person overrides.
    $itB = Department::factory()->forCompany($this->b)->create(['name' => 'IT', 'supervisor_employee_id' => $this->rudiB->id]);
    $other = owCase(owMember('Lain', $this->rudiB, Role::Employee, $this->b));

    expect(OffboardingCaseDepartment::where('offboarding_case_id', $other->id)->where('owner_user_id', $this->ivan->user_id)->exists())->toBeTrue()
        ->and(OffboardingCaseDepartment::where('offboarding_case_id', $other->id)->where('owner_user_id', $this->rudiB->user_id)->exists())->toBeFalse();
});

it('makes a group for each owner, and one for HR where nobody can own an item', function () {
    $ina = Employee::factory()->forCompany($this->a)->create(['full_name' => 'Ina', 'user_id' => owUser(Role::Employee, $this->a)->id]);
    $this->vpn->update(['owner_employee_id' => $ina->id]);

    $case = owCase();
    $owners = OffboardingCaseDepartment::where('offboarding_case_id', $case->id)->pluck('owner_user_id')->all();

    expect($owners)->toContain($this->ivan->user_id)->toContain($ina->user_id)->toContain(null)
        ->and(count($owners))->toBe(3);
});

it('falls back to the department supervisor, then HR, when the named person cannot act', function () {
    Department::factory()->forCompany($this->b)->create(['name' => 'IT', 'supervisor_employee_id' => $this->rudiB->id]);
    $this->ivan->user->forceFill(['is_active' => false])->save();

    $case = owCase();

    expect(OffboardingCaseDepartment::where('offboarding_case_id', $case->id)->where('owner_user_id', $this->rudiB->user_id)->exists())->toBeTrue();

    // The leaver cannot own their own item either.
    $this->ivan->user->forceFill(['is_active' => true])->save();
    $this->laptop->update(['owner_employee_id' => $this->sintaB->id]);
    $this->vpn->update(['owner_employee_id' => $this->sintaB->id]);
    $second = owCase(owMember('Dua', $this->rudiB, Role::Employee, $this->b));

    expect(OffboardingCaseDepartment::where('offboarding_case_id', $second->id)->where('owner_user_id', $this->sintaB->user_id)->exists())->toBeTrue();
});

// --- The owner from another company ---

it('lets the owner open the case from another company, and see only what they need', function () {
    $case = owCase();

    $this->actingAs($this->ivan->user)->get(route('offboarding.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('offboarding/owner')
            ->where('case.employee.name', 'Sinta')
            ->where('case.lastWorkingOn', '2026-12-02')
            ->where('case.checklist.groups.0.ownerTitle', 'IT Support · IT')
            ->missing('case.reason')
            ->missing('case.compensationMonths')
            ->missing('case.files')
            ->missing('case.chain')
            ->where('case.exitType', null)
            ->where('case.createdBy', null));

    $content = $this->actingAs($this->ivan->user)->get(route('offboarding.show', $case))->getContent();
    expect($content)->not->toContain('Rahasia');
});

it('lets the owner do their checklist work across companies, and nothing else', function () {
    $case = owCase();
    $group = owGroup($case, (string) $this->ivan->user_id);
    $by = $this->actingAs($this->ivan->user);

    $by->post(route('offboarding.checklist.select', [$case, $group]), ['offboarding_item_id' => $this->laptop->id])->assertRedirect();
    $by->post(route('offboarding.checklist.confirm', [$case, $group]))->assertRedirect();

    $item = $group->items()->firstOrFail();
    $by->put(route('offboarding.checklist.done', $item), ['note' => 'Diterima'])->assertRedirect();

    expect($item->refresh()->isDone())->toBeTrue();

    // Not the rest of the case.
    $hrGroup = owGroup($case, 'hr');
    $by->post(route('offboarding.checklist.confirm', [$case, $hrGroup]))->assertForbidden();
    $by->post(route('offboarding.comments.store', $case), ['body' => 'x'])->assertForbidden();
    $by->get(route('offboarding.berita-acara', $case))->assertForbidden();
    $by->put(route('offboarding.last-day', $case), ['last_working_on' => '2026-12-20'])->assertForbidden();
    $by->post(route('offboarding.cancel', $case), ['note' => 'x'])->assertForbidden();
    $by->post(route('offboarding.proceed', $case))->assertForbidden();
    $by->get(route('offboarding.edit', $case))->assertForbidden();
});

it('keeps every other case of that company closed to them', function () {
    $mine = owCase();
    $other = owCase(owMember('Lain', $this->rudiB, Role::Employee, $this->b));
    // Ivan owns nothing on the second case once his items are given to someone else for it.
    owGroup($other, (string) $this->ivan->user_id)->update(['owner_user_id' => null]);

    $this->actingAs($this->ivan->user)->get(route('offboarding.show', $mine))->assertOk();
    $this->actingAs($this->ivan->user)->get(route('offboarding.show', $other))->assertForbidden();

    $this->actingAs($this->ivan->user)->get(route('offboarding.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('cases', 1)
            ->where('cases.0.reference', $mine->reference())
            ->where('cases.0.limited', true)
            ->where('cases.0.exitType', null)
            ->where('cases.0.createdBy', null));
});

it('keeps HR of another company out of a case, and its checklist', function () {
    $case = owCase();
    $group = owGroup($case, 'hr');

    $this->actingAs($this->hrA)->get(route('offboarding.show', $case))->assertForbidden();
    $this->actingAs($this->hrA)->post(route('offboarding.checklist.confirm', [$case, $group]))->assertForbidden();
    $this->actingAs($this->hrA)->put(route('offboarding.checklist.owner', [$case, $group]), ['user_id' => $this->hrA->id])->assertForbidden();
});

it('counts the cases they own in the menu badge, once', function () {
    owCase();

    $this->actingAs($this->ivan->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('openOffboarding', 1)->where('auth.canViewOffboarding', true));
});

// --- When the owner cannot act ---

it('has HR do the work of a group whose owner has been deactivated, and lets HR hand it on', function () {
    $case = owCase();
    $group = owGroup($case, (string) $this->ivan->user_id);
    $this->ivan->user->forceFill(['is_active' => false])->save();

    // A deactivated account cannot act, and HR can in its place.
    expect($this->ivan->user->can('workGroup', $group))->toBeFalse();

    $this->actingAs($this->hrB)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.groups', fn ($groups) => collect($groups)->firstWhere('id', $group->id)['ownerUnavailable'] === true
            && collect($groups)->firstWhere('id', $group->id)['canWork'] === true));

    $ina = owMember('Ina', null, Role::Employee, $this->a);
    $this->actingAs($this->hrB)->put(route('offboarding.checklist.owner', [$case, $group]), ['user_id' => $ina->user_id])->assertRedirect();

    expect($group->refresh()->owner_user_id)->toBe($ina->user_id)
        ->and($ina->user->notifications()->count())->toBe(1);

    $this->actingAs($ina->user)->post(route('offboarding.checklist.confirm', [$case, $group]))->assertRedirect();
});

it('refuses a new owner who cannot take it, and lets HR take a group back', function () {
    $case = owCase();
    $group = owGroup($case, (string) $this->ivan->user_id);
    $set = fn (?int $userId) => $this->actingAs($this->hrB)->put(route('offboarding.checklist.owner', [$case, $group]), ['user_id' => $userId]);

    $set($this->sintaB->user_id)->assertSessionHasErrors('user_id');
    $set(999999)->assertSessionHasErrors('user_id');
    $this->actingAs($this->ivan->user)->put(route('offboarding.checklist.owner', [$case, $group]), ['user_id' => $this->ivan->user_id])->assertForbidden();

    $set(null)->assertRedirect();
    expect($group->refresh()->owner_user_id)->toBeNull()->and($group->owner_title)->toBeNull();

    $this->actingAs($this->hrB)->post(route('offboarding.checklist.confirm', [$case, $group]))->assertRedirect();
});

it('offers HR the people they can hand a group to, with job title and company', function () {
    $case = owCase();

    $this->actingAs($this->hrB)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.ownerOptions', fn ($options) => collect($options)->contains(fn ($option) => str_contains($option['label'], 'Ivan') && str_contains($option['label'], 'IT Support') && str_contains($option['label'], 'CMP-A'))));
});

// --- The master list ---

it('lets only a parent-wide HR Admin or System Admin manage the group-wide list', function () {
    $groupHr = User::factory()->create(['company_id' => null]);
    $groupHr->assignRole(Role::HrAdmin->value);
    $groupAdmin = User::factory()->create(['company_id' => null]);
    $groupAdmin->assignRole(Role::SystemAdmin->value);

    expect($groupHr->can('viewAny', OffboardingItem::class))->toBeTrue()
        ->and($groupAdmin->can('viewAny', OffboardingItem::class))->toBeTrue()
        ->and($this->hrB->can('viewAny', OffboardingItem::class))->toBeFalse()
        ->and($this->ivan->user->can('viewAny', OffboardingItem::class))->toBeFalse()
        ->and($groupHr->can('update', $this->laptop))->toBeTrue()
        ->and($this->hrB->can('update', $this->laptop))->toBeFalse();

    $this->actingAs($groupHr)->get('/admin/offboarding-items')->assertOk();
    $this->actingAs($groupHr)->get("/admin/offboarding-items/{$this->laptop->id}/edit")->assertOk();
});

it('makes the defaults once for the whole group, not once per company', function () {
    OffboardingItem::query()->delete();

    $first = owCase();
    $count = OffboardingItem::count();

    $this->actingAs($this->hrA)->post(route('offboarding.store'), ['employee_id' => owMember('X', null, Role::Employee, $this->a)->id])->assertRedirect();

    expect($count)->toBe(count(OffboardingCatalog::DEFAULTS))
        ->and(OffboardingItem::count())->toBe($count)
        ->and($first->checklistDepartments()->count())->toBeGreaterThan(0);
});

it('keeps a group\'s owner details as they were when the owner later moves department', function () {
    $case = owCase();
    $group = owGroup($case, (string) $this->ivan->user_id);

    $this->ivan->update(['department_id' => null, 'position_id' => null]);

    expect($group->refresh()->owner_title)->toBe('IT Support')->and($group->owner_department)->toBe('IT');
});

// --- Bringing a running checklist up to date (OFF-ITM-008) ---

it('adds items created since the checklist opened to their owners, and tells them', function () {
    $case = owCase();
    $before = OffboardingCaseDepartment::where('offboarding_case_id', $case->id)->count();
    $ivanGroup = owGroup($case, (string) $this->ivan->user_id);
    $this->ivan->user->notifications()->delete();

    $token = OffboardingItem::factory()->create(['name' => 'Token dikembalikan', 'department_name' => 'IT', 'owner_employee_id' => $this->ivan->id]);
    $desk = OffboardingItem::factory()->create(['name' => 'Meja dikosongkan', 'department_name' => 'HR']);

    $this->actingAs($this->hrB)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.canRefresh', true)->where('case.checklist.newItems', 2));

    $this->actingAs($this->hrB)->post(route('offboarding.checklist.refresh', $case))->assertRedirect();

    expect($ivanGroup->refresh()->item_ids)->toBe([$this->laptop->id, $this->vpn->id, $token->id])
        ->and(owGroup($case, 'hr')->item_ids)->toContain($desk->id)
        ->and(OffboardingCaseDepartment::where('offboarding_case_id', $case->id)->count())->toBe($before)
        ->and($this->ivan->user->notifications()->count())->toBe(1);

    // Nothing is left to add the second time, and nobody is told again.
    $this->actingAs($this->hrB)->post(route('offboarding.checklist.refresh', $case))->assertRedirect();
    expect($this->ivan->user->notifications()->count())->toBe(1);
});

it('puts a new item in a group of its own when its owner has already confirmed their list', function () {
    $case = owCase();
    $group = owGroup($case, (string) $this->ivan->user_id);
    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.select', [$case, $group]), ['offboarding_item_id' => $this->laptop->id])->assertRedirect();
    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.confirm', [$case, $group]))->assertRedirect();
    $confirmedAt = $group->refresh()->confirmed_at;

    $token = OffboardingItem::factory()->create(['name' => 'Token dikembalikan', 'department_name' => 'IT', 'owner_employee_id' => $this->ivan->id]);
    $this->actingAs($this->hrB)->post(route('offboarding.checklist.refresh', $case))->assertRedirect();

    $extra = OffboardingCaseDepartment::where('offboarding_case_id', $case->id)->where('owner_user_id', $this->ivan->user_id)->where('id', '!=', $group->id)->firstOrFail();

    expect($group->refresh()->item_ids)->toBe([$this->laptop->id, $this->vpn->id])
        ->and($group->confirmed_at->equalTo($confirmedAt))->toBeTrue()
        ->and($group->items()->count())->toBe(1)
        ->and($extra->item_ids)->toBe([$token->id])
        ->and($extra->name)->toBe('IT (tambahan)')
        ->and($extra->confirmed_at)->toBeNull();

    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.select', [$case, $extra]), ['offboarding_item_id' => $token->id])->assertRedirect();
});

it('leaves out inactive items, and offers the button to HR only while the case is at the checklist', function () {
    $case = owCase();
    OffboardingItem::factory()->create(['name' => 'Lama', 'is_active' => false]);

    $this->actingAs($this->hrB)->get(route('offboarding.show', $case))
        ->assertInertia(fn (Assert $page) => $page->where('case.checklist.newItems', 0));

    // Not the owner, the leaver, nor HR of another company.
    $this->actingAs($this->ivan->user)->post(route('offboarding.checklist.refresh', $case))->assertForbidden();
    $this->actingAs($this->sintaB->user)->post(route('offboarding.checklist.refresh', $case))->assertForbidden();
    $this->actingAs($this->hrA)->post(route('offboarding.checklist.refresh', $case))->assertForbidden();

    $case->workflow->update(['current_stage' => 'sign_off']);
    $this->actingAs($this->hrB)->post(route('offboarding.checklist.refresh', $case))->assertForbidden();
});
