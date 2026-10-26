<?php

use App\Actions\Attendance\ImportScanLog;
use App\Enums\Role;
use App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents;
use App\Models\AttendanceAgent;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceIngestRequest;
use App\Models\AttendancePunch;
use App\Models\AttendanceUnmappedPunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\AgentStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * The attendance ingest endpoint the on-site sync agent pushes to (ATT-CAL-003), its scoped tokens
 * (CORE-AUT-008), throttling (CORE-AUT-007), and the status and log around it (ATT-DEV-001/003/004).
 * The agents are switched off by default (FEATURE_ATTENDANCE_AGENTS); these tests switch them on.
 */
beforeEach(function () {
    config(['features.attendance_agents' => true]);
    $this->seed(RoleSeeder::class);
    config(['attendance.device_timezone' => 'Asia/Jakarta']);
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->forCompany($this->company)->create();
    AttendanceEnrolMap::create(['company_id' => $this->company->id, 'employee_id' => $this->employee->id, 'device_sn' => 'SN1', 'pin' => '1042']);
    $this->agent = AttendanceAgent::create(['name' => 'SERVER-CAMAT']);
    $this->token = $this->agent->issueToken();
});

/**
 * Each request runs in a fresh process in production; inside one test the app, and with it the
 * guard's remembered agent, persists. Forgetting the guards first makes every call authenticate for
 * itself, as it would for real.
 */
function withAgentToken(string $token): mixed
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

function punchPayload(array $overrides = []): array
{
    return ['sn' => 'SN1', 'pin' => '1042', 'scan_at' => '2026-10-05T07:55:00', 'verify_mode' => 1, 'inout_mode' => 0, 'source' => 'push', ...$overrides];
}

function ingest(array $punches, array $extra = []): TestResponse
{
    return withAgentToken(test()->token)->postJson('/api/ingest/attendance', ['punches' => $punches, ...$extra]);
}

// --- Authentication and scope ---

it('refuses a request with no token', function () {
    $this->postJson('/api/ingest/attendance', ['punches' => []])->assertUnauthorized();
    $this->getJson('/api/ingest/attendance/instructions')->assertUnauthorized();
});

it('refuses a wrong token', function () {
    withAgentToken('999|nottherealtoken')->postJson('/api/ingest/attendance', ['punches' => []])->assertUnauthorized();
});

it('does not let a signed-in browser session stand in for a token', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::HrAdmin->value);

    $this->actingAs($user)->postJson('/api/ingest/attendance', ['punches' => [punchPayload()]])->assertUnauthorized();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(0);
});

it('refuses a token that lacks the ingest ability', function () {
    $other = AttendanceAgent::create(['name' => 'Lain'])->createToken('x', ['something:else'])->plainTextToken;

    withAgentToken($other)->postJson('/api/ingest/attendance', ['punches' => [punchPayload()]])->assertForbidden();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(0);
});

it('stops working the moment a token is rotated', function () {
    $old = $this->token;
    $new = $this->agent->issueToken();

    withAgentToken($old)->postJson('/api/ingest/attendance', ['punches' => []])->assertUnauthorized();
    withAgentToken($new)->postJson('/api/ingest/attendance', ['punches' => []])->assertOk();
});

it('revokes an agent\'s tokens when it is deleted', function () {
    $this->agent->delete();

    withAgentToken($this->token)->postJson('/api/ingest/attendance', ['punches' => []])->assertUnauthorized();
    expect($this->agent->tokens()->count())->toBe(0);
});

// --- Filing punches ---

it('files a mapped punch under its employee, reading scanner time as local', function () {
    ingest([punchPayload()])
        ->assertOk()
        ->assertJson(['accepted' => 1, 'duplicates' => 0, 'unmapped' => 0, 'last_accepted_scan_at' => '2026-10-05T07:55:00']);

    $punch = AttendancePunch::acrossCompanies()->firstOrFail();

    expect($punch->employee_id)->toBe($this->employee->id)
        ->and($punch->company_id)->toBe($this->company->id)
        ->and($punch->scan_at->toDateTimeString())->toBe('2026-10-05 00:55:00')
        ->and($punch->source)->toBe('push');
});

it('holds an unmapped punch rather than dropping it', function () {
    ingest([punchPayload(['pin' => '9999'])])->assertOk()->assertJson(['accepted' => 0, 'unmapped' => 1]);

    expect(AttendanceUnmappedPunch::count())->toBe(1);
});

it('does nothing the second time the same batch arrives', function () {
    $batch = [punchPayload(), punchPayload(['scan_at' => '2026-10-05T17:02:00'])];

    ingest($batch)->assertJson(['accepted' => 2]);
    ingest($batch)->assertOk()->assertJson(['accepted' => 0, 'duplicates' => 2]);

    expect(AttendancePunch::acrossCompanies()->count())->toBe(2);
});

it('treats a punch already loaded from a file as a duplicate, and the reverse', function () {
    $file = UploadedFile::fake()->createWithContent('log.csv', "pin,scan_at\n1042,2026-10-05 07:55:00\n");
    app(ImportScanLog::class)->handle($file, 'SN1', null);

    ingest([punchPayload()])->assertJson(['accepted' => 0, 'duplicates' => 1]);

    expect(AttendancePunch::acrossCompanies()->count())->toBe(1);
});

it('keeps the push and sweep sources apart', function () {
    ingest([punchPayload(), punchPayload(['scan_at' => '2026-10-05T17:00:00', 'source' => 'sweep'])])->assertJson(['accepted' => 2]);

    expect(AttendancePunch::acrossCompanies()->orderBy('scan_at')->pluck('source')->all())->toBe(['push', 'sweep']);
});

it('accepts a space-separated timestamp and numeric identifiers', function () {
    ingest([punchPayload(['pin' => 1042, 'scan_at' => '2026-10-05 08:00:00'])])->assertOk()->assertJson(['accepted' => 1]);
});

// --- Validation: the whole batch or nothing ---

it('refuses the whole batch when any row is malformed', function () {
    ingest([punchPayload(), punchPayload(['scan_at' => 'kemarin'])])->assertUnprocessable();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(0);
});

it('refuses a row missing a required field, an oversized batch and an unknown source', function () {
    ingest([['sn' => 'SN1', 'scan_at' => '2026-10-05T07:55:00']])->assertUnprocessable();
    ingest(array_fill(0, 1001, punchPayload()))->assertUnprocessable();
    ingest([punchPayload(['source' => 'carrier-pigeon'])])->assertUnprocessable();
    withAgentToken($this->token)->postJson('/api/ingest/attendance', [])->assertUnprocessable();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(0);
});

it('logs a refused batch so the failure can be seen', function () {
    ingest([punchPayload(['scan_at' => 'kemarin'])])->assertUnprocessable();

    $log = AttendanceIngestRequest::firstOrFail();

    expect($log->status)->toBe('rejected')
        ->and($log->attendance_agent_id)->toBe($this->agent->id)
        ->and($log->rows)->toBe(1)
        ->and($log->message)->not->toBeNull();
});

// --- The agent's record ---

it('logs every successful request with its counts', function () {
    ingest([punchPayload(), punchPayload(['pin' => '9999'])])->assertOk();

    $log = AttendanceIngestRequest::firstOrFail();

    expect($log->status)->toBe('ok')
        ->and([$log->rows, $log->accepted, $log->duplicates, $log->unmapped])->toBe([2, 1, 0, 1]);
});

it('treats an empty batch as a heartbeat and records the listener\'s', function () {
    ingest([], ['heartbeat' => ['listener_alive_at' => '2026-10-05T07:50:00+07:00']])->assertOk()->assertJson(['accepted' => 0]);

    $agent = $this->agent->fresh();

    expect($agent->last_seen_at)->not->toBeNull()
        ->and($agent->last_heartbeat_at->utc()->toDateTimeString())->toBe('2026-10-05 00:50:00')
        ->and(AttendanceIngestRequest::count())->toBe(1);
});

it('answers a "sync now" request on the next poll and clears it after the next report', function () {
    withAgentToken($this->token)->getJson('/api/ingest/attendance/instructions')->assertOk()->assertJson(['sync_now' => false]);

    // What the Data absensi button does, without a browser session in the same test as a token.
    $this->agent->forceFill(['sync_requested_at' => now()])->save();

    withAgentToken($this->token)->getJson('/api/ingest/attendance/instructions')->assertJson(['sync_now' => true]);

    ingest([])->assertOk();

    withAgentToken($this->token)->getJson('/api/ingest/attendance/instructions')->assertJson(['sync_now' => false]);
});

it('lets a parent-wide attendance manager ask for a sync, and nobody else', function () {
    $pinned = User::factory()->create(['company_id' => $this->company->id]);
    $pinned->assignRole(Role::HrAdmin->value);

    $this->actingAs($pinned)->post(route('attendance.agents.sync-now'))->assertForbidden();
    expect($this->agent->fresh()->sync_requested_at)->toBeNull();

    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole(Role::HrAdmin->value);

    $this->actingAs($admin)->post(route('attendance.agents.sync-now'))->assertRedirect();
    expect($this->agent->fresh()->sync_requested_at)->not->toBeNull();
});

// --- Throttling ---

it('throttles an agent that hammers the endpoint, per token', function () {
    $status = null;

    for ($i = 0; $i < 121; $i++) {
        $status = ingest([])->status();
    }

    expect($status)->toBe(429);

    // A different agent's token has its own allowance.
    $other = AttendanceAgent::create(['name' => 'Lain']);
    withAgentToken($other->issueToken())->postJson('/api/ingest/attendance', ['punches' => []])->assertOk();
});

// --- Is the agent reporting in? ---

it('raises the alarm only in working hours, and only when the agent has gone quiet', function () {
    $status = fn (string $now) => app(AgentStatus::class)->all(CarbonImmutable::parse($now, 'Asia/Jakarta'))[0]['stale'];

    config(['attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10]]);

    AttendanceIngestRequest::create(['attendance_agent_id' => $this->agent->id, 'status' => 'ok', 'created_at' => CarbonImmutable::parse('2026-10-07 08:30', 'Asia/Jakarta')->utc()]);

    expect($status('2026-10-07 10:00'))->toBeFalse()   // 1.5h since the last run
        ->and($status('2026-10-07 12:00'))->toBeTrue()   // 3.5h
        ->and($status('2026-10-07 19:00'))->toBeFalse()  // after hours: silence is normal
        ->and($status('2026-10-10 12:00'))->toBeFalse(); // Saturday
});

it('treats an agent that has never reported as stale during working hours', function () {
    config(['attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10]]);

    expect(app(AgentStatus::class)->all(CarbonImmutable::parse('2026-10-07 10:00', 'Asia/Jakarta'))[0])
        ->toMatchArray(['stale' => true, 'lastRunAt' => null]);
});

it('ignores a refused request when judging whether the agent is alive', function () {
    config(['attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10]]);
    AttendanceIngestRequest::create(['attendance_agent_id' => $this->agent->id, 'status' => 'rejected', 'created_at' => CarbonImmutable::parse('2026-10-07 09:59', 'Asia/Jakarta')->utc()]);

    expect(app(AgentStatus::class)->all(CarbonImmutable::parse('2026-10-07 10:00', 'Asia/Jakarta'))[0]['stale'])->toBeTrue();
});

it('shows the agents on the data page and the alarm on the dashboard', function () {
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole(Role::HrAdmin->value);

    $this->actingAs($admin)->get(route('attendance.device'))
        ->assertInertia(fn ($page) => $page->has('agents', 1)->where('agents.0.name', 'SERVER-CAMAT')->etc());

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('attendanceStatus.agentStale')->etc());
});

// --- Managing agents from the admin panel ---

it('creates an agent from the admin panel with a working token, shown once', function () {
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole(Role::HrAdmin->value);
    $this->actingAs($admin);

    Livewire::test(ManageAttendanceAgents::class)
        ->callAction('create', ['name' => 'SERVER-BARU'])
        ->assertHasNoFormErrors();

    $agent = AttendanceAgent::firstWhere('name', 'SERVER-BARU');

    expect($agent)->not->toBeNull()->and($agent->tokens()->count())->toBe(1);
});

it('keeps the agent admin screens to parent-wide attendance managers', function () {
    $this->actingAs(User::factory()->create(['company_id' => null]))->get('/admin/attendance-agents')->assertForbidden();

    $pinned = User::factory()->create(['company_id' => $this->company->id]);
    $pinned->assignRole(Role::HrAdmin->value);
    $this->actingAs($pinned)->get('/admin/attendance-agents')->assertForbidden();

    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole(Role::HrAdmin->value);
    $this->actingAs($admin)->get('/admin/attendance-agents')->assertOk();
    $this->actingAs($admin)->get('/admin/attendance-ingest-requests')->assertOk();
});
