<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\InAppNotice;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Database\Seeders\RoleSeeder;

/**
 * The public entrance is employee self-service for everyone who signs in (CORE-ACC-002): a manager, a head of department, a
 * requester or a direct superior sees the same menus and notices as any employee there, and the private modules stay on the
 * private entrance. The routes are refused on the public one already; this is that the menus and the bell agree.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    // Management, and also: the head of a department, the one who raised a requisition, and the direct superior named on it.
    $this->boss = User::factory()->create(['company_id' => $this->company->id]);
    $this->boss->assignRole(Role::Management->value);
    $this->bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id, 'joined_on' => '2020-01-01']);
    $this->department->update(['supervisor_employee_id' => $this->bossEmployee->id]);

    $this->requisition = Requisition::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'created_by_user_id' => $this->boss->id,
        'manager_employee_id' => $this->bossEmployee->id,
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($this->requisition, new RequisitionWorkflow, [], $this->boss)
        ->update(['current_stage' => RequisitionStage::Interview->value]);

    $this->boss->notify(new InAppNotice('recruitment', 'Anda atasan langsung', 'REQ-0001', '/recruitment/'.$this->requisition->id));
    $this->boss->notify(new InAppNotice('leave', 'Pengajuan menunggu', 'Cuti', '/leave-requests'));
});

/** The page's shared props as seen through an entrance. */
function entranceProps(string $entrance, string $path = '/dashboard'): array
{
    return test()->withHeaders(['X-Entrance' => $entrance])->actingAs(test()->boss)->get($path)->assertOk()->viewData('page')['props'];
}

it('offers the private menus to such a person on the private entrance', function () {
    $props = entranceProps('private');

    expect($props['auth']['canViewRecruitment'])->toBeTrue()
        ->and($props['notifications']['unread'])->toBe(2);
});

it('offers only the employee\'s own pages on the public entrance, however much their post would allow', function () {
    $props = entranceProps('public');

    expect(collect($props['auth'])->only(['canAccessAdminPanel', 'canViewAttendance', 'canManageAttendance', 'canViewOffboarding', 'canViewRecruitment', 'canReviewProfileChanges', 'canViewEmployees'])->filter()->all())->toBe([])
        ->and($props['recruitmentTodo'])->toBe(0)
        ->and($props['pendingProfileChanges'])->toBe(0)
        ->and($props['openOffboarding'])->toBe(0)
        // Their own profile link is still there.
        ->and($props['auth']['employeeId'])->toBe($this->bossEmployee->id);
});

it('does not offer the private pages in the search on the public entrance', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);

    $titles = fn (User $user, string $entrance, string $query): array => collect($this->withHeaders(['X-Entrance' => $entrance])->actingAs($user)->getJson(route('search', ['q' => $query]))->assertOk()->json('groups'))
        ->flatMap(fn (array $group): array => $group['items'])->pluck('title')->all();

    // HR is offered the private pages on the private entrance; neither HR nor a manager is, on the public one.
    expect($titles($hr, 'private', 'kalender'))->toContain('Kalender')
        ->and($titles($hr, 'public', 'kalender'))->toBe([])
        ->and($titles($this->boss, 'public', 'kalender'))->toBe([])
        ->and($titles($this->boss, 'public', 'karyawan'))->toBe([])
        ->and($titles($this->boss, 'public', 'pengajuan'))->toContain('Pengajuan');
});

it('refuses the private modules on the public entrance for such a person', function () {
    $this->withHeaders(['X-Entrance' => 'public'])->actingAs($this->boss)->get('/recruitment')->assertForbidden();
    $this->withHeaders(['X-Entrance' => 'public'])->actingAs($this->boss)->get('/recruitment/'.$this->requisition->id)->assertForbidden();
    $this->withHeaders(['X-Entrance' => 'private'])->actingAs($this->boss)->get('/recruitment')->assertOk();
});

it('leaves notices about private modules out of the bell and the list on the public entrance', function () {
    $public = entranceProps('public');
    $private = entranceProps('private');

    expect(collect($public['notifications']['recent'])->pluck('title')->all())->toBe(['Pengajuan menunggu'])
        ->and($public['notifications']['unread'])->toBe(1)
        ->and(collect($private['notifications']['recent'])->pluck('title')->sort()->values()->all())->toBe(['Anda atasan langsung', 'Pengajuan menunggu']);

    $list = entranceProps('public', '/notifications')['list'];
    $recruitment = $this->boss->notifications()->where('data->kind', 'recruitment')->firstOrFail();

    expect(collect($list['data'])->pluck('title')->all())->toBe(['Pengajuan menunggu']);

    // A notice that is not shown cannot be opened there either.
    $this->withHeaders(['X-Entrance' => 'public'])->actingAs($this->boss)->get(route('notifications.open', $recruitment->id))->assertNotFound();
    $this->withHeaders(['X-Entrance' => 'private'])->actingAs($this->boss)->get(route('notifications.open', $recruitment->id))->assertRedirect('/recruitment/'.$this->requisition->id);
});
