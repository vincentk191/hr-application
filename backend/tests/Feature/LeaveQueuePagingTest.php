<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Pengajuan queue is read a page at a time, so it stays quick however many requests have piled up (UI/UX audit UX-03).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    // A request may not start in the past, so the clock stays before the dates filed below.
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    $this->company = Company::factory()->create();

    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
});

/** One request filed by HR for a new employee of the company. */
function queuedLeave(User $filer, Company $company, ?Employee $employee = null): LeaveRequest
{
    $employee ??= Employee::factory()->forCompany($company)->create();

    test()->actingAs($filer)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_on' => '2026-11-02',
        'end_on' => '2026-11-04',
        'reason' => 'Acara keluarga',
    ])->assertRedirect(route('leave-requests.index'));

    return LeaveRequest::latest('id')->firstOrFail();
}

it('shows the queue 25 at a time with the real total', function () {
    foreach (range(1, 27) as $ignored) {
        queuedLeave($this->hr, $this->company);
    }

    $this->actingAs($this->hr)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 25)
            ->where('pagination', ['total' => 27, 'from' => 1, 'to' => 25, 'currentPage' => 1, 'lastPage' => 2]));

    $this->actingAs($this->hr)->get(route('leave-requests.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 2)
            ->where('pagination.from', 26)
            ->where('pagination.to', 27)
            ->where('pagination.currentPage', 2));
});

it('counts only what the filters leave, not the whole company', function () {
    queuedLeave($this->hr, $this->company);
    queuedLeave($this->hr, $this->company);

    $this->actingAs($this->hr)->get(route('leave-requests.index', ['status' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 0)
            ->where('pagination.total', 0)
            ->where('mine', []));
});

it('finds what waits on a person in the database, and pins all of it', function () {
    $supervisorUser = User::factory()->create(['company_id' => $this->company->id]);
    $supervisorUser->assignRole(Role::Supervisor->value);
    $supervisor = Employee::factory()->forCompany($this->company)->create(['user_id' => $supervisorUser->id]);

    foreach (range(1, 2) as $ignored) {
        queuedLeave($this->hr, $this->company, Employee::factory()->forCompany($this->company)->reportingTo($supervisor)->create());
    }

    // Someone else's team: it waits on another Atasan, so it is not this supervisor's to decide.
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherUser->assignRole(Role::Supervisor->value);
    $other = Employee::factory()->forCompany($this->company)->create(['user_id' => $otherUser->id]);
    queuedLeave($this->hr, $this->company, Employee::factory()->forCompany($this->company)->reportingTo($other)->create());

    // HR sees all three and none is theirs.
    $this->actingAs($this->hr)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 3)
            ->where('mine', [])
            ->where('waitingOnMe', 0));

    $this->actingAs($this->hr)->get(route('leave-requests.index', ['status' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 0)->has('requests', 0));

    // The supervisor's own queue agrees with the badge, and the pinned list is the same two.
    $this->actingAs($supervisorUser)->get(route('leave-requests.index', ['status' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('waitingOnMe', 2)
            ->where('pagination.total', 2)
            ->has('requests', 2)
            ->has('mine', 2)
            ->where('mine.0.canDecide', true));
});

it('opens a request a link points at even when it is not on the first page', function () {
    $oldest = queuedLeave($this->hr, $this->company);

    foreach (range(1, 26) as $ignored) {
        queuedLeave($this->hr, $this->company);
    }

    $this->actingAs($this->hr)->get(route('leave-requests.index', ['open' => $oldest->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 25)
            ->where('requests', fn ($rows) => collect($rows)->doesntContain('id', $oldest->id))
            ->where('opened.id', $oldest->id));
});

it('does not open a request from another company', function () {
    $foreign = Employee::factory()->forCompany(Company::factory()->create())->create();
    $leave = LeaveRequest::factory()->forEmployee($foreign)->create();

    $this->actingAs($this->hr)->get(route('leave-requests.index', ['open' => $leave->id]))
        ->assertInertia(fn (Assert $page) => $page->where('opened', null));
});

it('still exports every filtered request, not one page of them', function () {
    foreach (range(1, 27) as $ignored) {
        queuedLeave($this->hr, $this->company);
    }

    $csv = $this->actingAs($this->hr)->get(route('leave-requests.export'))->streamedContent();

    // The heading row and one line per request.
    expect(count(array_filter(explode("\n", $csv), fn (string $line): bool => trim($line) !== '')))->toBe(28);
});
