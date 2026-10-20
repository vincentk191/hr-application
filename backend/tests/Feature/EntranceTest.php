<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Access\AccessTier;
use App\Support\Access\PublicRoutes;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The two entrances (CORE-ACC-001..004): the private one over the VPN, and a public one that serves only an
 * employee's own pages. The tests mark a request public the way Nginx does, with the X-Entrance header from a
 * trusted proxy (the test client is 127.0.0.1, which is trusted by default).
 *
 * Mira (Management) manages Rudi (Supervisor), who manages Sinta (Employee). Lukas is a plain employee.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    config(['attendance.device_timezone' => 'Asia/Jakarta']);

    $this->company = Company::factory()->create();
    $this->hr = enUser(Role::HrAdmin);
    $this->mira = enMember('Mira', null, Role::Management);
    $this->rudi = enMember('Rudi', $this->mira, Role::Supervisor);
    $this->sinta = enMember('Sinta', $this->rudi, Role::Employee);
    $this->lukas = enMember('Lukas', null, Role::Employee);
});

function enUser(Role $role): User
{
    $user = User::factory()->create(['company_id' => test()->company->id]);
    $user->assignRole($role->value);

    return $user;
}

function enMember(string $name, ?Employee $manager, Role $role): Employee
{
    return Employee::factory()->forCompany(test()->company)->create(['full_name' => $name, 'manager_id' => $manager?->id, 'user_id' => enUser($role)->id]);
}

function onPublic(User $user)
{
    return test()->withHeaders(['X-Entrance' => 'public'])->actingAs($user);
}

function onPrivate(User $user)
{
    return test()->withHeaders(['X-Entrance' => 'private'])->actingAs($user);
}

// --- Which entrance a request used ---

it('treats a request with no entrance header as private in development, and as public when nothing says otherwise', function () {
    $this->actingAs($this->rudi->user)->get(route('employees.index'))->assertOk();

    config(['entrance.default' => 'public']);
    $this->actingAs($this->rudi->user)->get(route('employees.index'))->assertForbidden();
});

it('believes the entrance header only from a trusted proxy', function () {
    config(['entrance.trusted_proxies' => ['10.9.9.9']]);

    // The client claims to be public, but is not a trusted proxy, so the default (private) stands.
    onPublic($this->rudi->user)->get(route('employees.index'))->assertOk();

    // And a client cannot claim to be private to get in when the default is public.
    config(['entrance.default' => 'public']);
    onPrivate($this->rudi->user)->get(route('employees.index'))->assertForbidden();
});

it('gives each entrance its own session cookie', function () {
    onPublic($this->sinta->user)->get(route('dashboard'))->assertOk();
    expect(config('session.cookie'))->toBe(config('entrance.session_cookies.public'));

    onPrivate($this->sinta->user)->get(route('dashboard'))->assertOk();
    expect(config('session.cookie'))->toBe(config('entrance.session_cookies.private'));
});

// --- What the public entrance serves ---

it('refuses everything that is not self-service on the public entrance, whoever asks', function () {
    $private = [
        route('employees.index'), route('employees.export'), route('employees.create'), route('offboarding.index'),
        route('attendance.calendar'), route('attendance.recap'), route('profile-changes.index'), '/admin',
    ];

    foreach ([$this->hr, $this->mira->user, $this->rudi->user, $this->sinta->user] as $user) {
        foreach ($private as $url) {
            onPublic($user)->get($url)->assertForbidden();
        }

        onPublic($user)->post('/livewire-47186d7c/update')->assertForbidden();
    }

    // The same people reach them on the private entrance.
    onPrivate($this->hr)->get(route('employees.index'))->assertOk();
    onPrivate($this->rudi->user)->get(route('offboarding.index'))->assertOk();
});

it('serves an employee their own pages on the public entrance', function () {
    foreach (['dashboard', 'leave-requests.index', 'leave-requests.create', 'notifications.index', 'profile.edit', 'appearance.edit'] as $name) {
        onPublic($this->sinta->user)->get(route($name))->assertOk();
    }

    onPublic($this->sinta->user)->get(route('employees.show', $this->sinta))->assertOk();
});

it('shows only their own record on the public entrance, even to someone who sees the team elsewhere', function () {
    onPrivate($this->rudi->user)->get(route('employees.show', $this->sinta))->assertOk();

    onPublic($this->rudi->user)->get(route('employees.show', $this->sinta))->assertForbidden();
    onPublic($this->rudi->user)->get(route('employees.show', $this->rudi))->assertOk();
    onPublic($this->hr)->get(route('employees.show', $this->sinta))->assertForbidden();
});

// --- The limited view for elevated accounts ---

it('holds an elevated account to the employee view and says so', function () {
    onPublic($this->rudi->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entrance.public', true)->where('entrance.limited', true)
            ->where('auth.canViewEmployees', false)->where('auth.canViewOffboarding', false)->where('auth.canViewAttendance', false)
            ->where('auth.canAccessAdminPanel', false)->where('pendingForMe', 0)->where('expiringDocuments', null)->where('attendanceStatus', null));

    onPublic($this->sinta->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('entrance.limited', false));

    onPrivate($this->rudi->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('entrance.public', false)->where('entrance.limited', false)->where('auth.canViewEmployees', true));
});

it('lists only their own requests on the public entrance, and no approvals waiting on them', function () {
    $this->actingAs($this->sinta->user)->post(route('leave-requests.store'), ['employee_id' => $this->sinta->id, 'leave_type' => 'annual', 'start_on' => '2026-10-21', 'end_on' => '2026-10-21', 'reason' => 'x'])->assertRedirect();
    $leave = LeaveRequest::query()->firstOrFail();

    onPrivate($this->rudi->user)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requests', 1)->where('waitingOnMe', 1));

    onPublic($this->rudi->user)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requests', 0)->where('waitingOnMe', 0));

    // Deciding needs the private entrance.
    onPublic($this->rudi->user)->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])->assertForbidden();
    onPrivate($this->rudi->user)->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])->assertRedirect();
});

// --- Filing on their own behalf from the public entrance ---

it('lets anyone file a Pengajuan for themselves on the public entrance', function () {
    onPublic($this->sinta->user)->post(route('leave-requests.store'), [
        'employee_id' => $this->sinta->id, 'leave_type' => 'annual', 'start_on' => '2026-10-21', 'end_on' => '2026-10-21', 'reason' => 'Keperluan',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(LeaveRequest::query()->where('employee_id', $this->sinta->id)->count())->toBe(1);
});

it('keeps filing for someone else, and backdating, to the private entrance', function () {
    $form = ['leave_type' => 'annual', 'start_on' => '2026-10-21', 'end_on' => '2026-10-21', 'reason' => 'x'];

    onPublic($this->hr)->post(route('leave-requests.store'), [...$form, 'employee_id' => $this->sinta->id])->assertSessionHasErrors('employee_id');
    onPublic($this->mira->user)->post(route('leave-requests.store'), [...$form, 'employee_id' => $this->sinta->id])->assertSessionHasErrors('employee_id');

    // An HR admin's own request cannot start in the past from the public entrance either.
    $hrEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->hr->id]);
    onPublic($this->hr)->post(route('leave-requests.store'), [...$form, 'employee_id' => $hrEmployee->id, 'start_on' => '2026-10-19', 'end_on' => '2026-10-19'])->assertSessionHasErrors('start_on');

    // On the private entrance both are allowed.
    onPrivate($this->hr)->post(route('leave-requests.store'), [...$form, 'employee_id' => $this->sinta->id])->assertRedirect()->assertSessionHasNoErrors();
});

// --- Who is elevated ---

it('knows who is elevated', function () {
    $tier = app(AccessTier::class);
    $itHead = enMember('Kepala IT', null, Role::Employee);
    Department::factory()->forCompany($this->company)->create(['name' => 'IT', 'supervisor_employee_id' => $itHead->id]);
    $manager = enMember('Atasan', null, Role::Employee);
    Employee::factory()->forCompany($this->company)->create(['manager_id' => $manager->id]);

    expect($tier->isElevated($this->hr))->toBeTrue()
        ->and($tier->isElevated($this->mira->user))->toBeTrue()
        ->and($tier->isElevated($this->rudi->user))->toBeTrue()
        // Employees by role, but with people under them, or a department to head.
        ->and($tier->isElevated($manager->user))->toBeTrue()
        ->and($tier->isElevated($itHead->user))->toBeTrue()
        ->and($tier->isElevated($this->sinta->user))->toBeFalse()
        ->and($tier->isElevated($this->lukas->user))->toBeFalse();
});

// --- Nothing is left unclassed ---

it('has every route classed as public or private, and serves only the public ones on the public entrance', function () {
    $unclassed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => PublicRoutes::classify($route) === null)
        ->map(fn ($route) => ($route->getName() ?? $route->uri()).' ['.implode('|', $route->methods()).']')
        ->values()->all();

    expect($unclassed)->toBe([]);
});
