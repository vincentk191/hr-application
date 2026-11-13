<?php

use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Mail\CandidateInterviewCancelled;
use App\Mail\CandidateInvitation;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\GoogleConnection;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Access\PublicRoutes;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Interviews made through the company's Google account (REC-INT): HR connects the account once, then inviting a candidate
 * makes an event on its calendar with a Meet room, and Google sends the one invitation. A time changed on Google is followed
 * by a routine check. Google itself is never called: every request is faked.
 *
 * "Now" is 2026-11-10 12:00 in Jakarta.
 */
beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    // Rendering a page asks the Inertia server-side-rendering address; nobody is listening in a test.
    Http::fake(['localhost:5173/*' => Http::response('', 404)]);
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00', 'Asia/Jakarta'));

    config(['services.google' => [
        'client_id' => 'client-id.apps.googleusercontent.com',
        'client_secret' => 'client-secret',
        'redirect' => 'https://hr-vpn.example.test/google/callback',
    ]]);

    $this->company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $this->department = Department::factory()->create(['company_id' => $this->company->id]);

    $this->hr = googleUser(Role::HrAdmin, $this->company, 'Hana HR');
    $this->supervisor = googleUser(Role::Supervisor, $this->company, 'Rudi Pemohon');
    $this->boss = googleUser(Role::Supervisor, $this->company, 'Bu Atasan');
    $bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->boss->id, 'full_name' => 'Bu Atasan']);

    $this->requisition = Requisition::factory()->create([
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'created_by_user_id' => $this->supervisor->id,
        'manager_employee_id' => $bossEmployee->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($this->requisition, new RequisitionWorkflow, [], $this->supervisor)
        ->update(['current_stage' => RequisitionStage::Interview->value]);
});

function googleUser(Role $role, Company $company, string $name): User
{
    $user = User::factory()->create(['company_id' => $company->id, 'name' => $name]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Replaces what the fake internet answers from here on. A second Http::fake() only adds stubs behind the first, which would
 * keep answering.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeAgain(array $stubs): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([...$stubs, 'localhost:5173/*' => Http::response('', 404)]);
}

/** The company's Google account, connected and with an access token that has not run out. */
function connectedGoogle(array $overrides = []): GoogleConnection
{
    return GoogleConnection::factory()->forCompany(test()->company)->create([
        'google_email' => 'recruitment@majujaya.test',
        'access_token' => 'access-token',
        'access_token_expires_at' => now()->addHour(),
        ...$overrides,
    ]);
}

/**
 * Google's answer for an event.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function googleEvent(array $overrides = []): array
{
    return [
        'id' => 'evt123',
        'etag' => '"etag-1"',
        'status' => 'confirmed',
        'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
        'start' => ['dateTime' => '2026-11-12T10:00:00+07:00'],
        'end' => ['dateTime' => '2026-11-12T11:00:00+07:00'],
        'attendees' => [
            ['email' => 'recruitment@majujaya.test', 'organizer' => true, 'self' => true, 'responseStatus' => 'accepted'],
            ['email' => 'budi@example.com', 'responseStatus' => 'needsAction'],
        ],
        ...$overrides,
    ];
}

/** Every call to the Calendar API answers with the event; a deletion answers with nothing, as Google does. */
function fakeCalendar(array $event = []): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => fn (Request $request) => $request->method() === 'DELETE'
            ? Http::response('', 204)
            : Http::response(googleEvent($event)),
    ]);
}

/**
 * The calls made to Google Calendar with the given method.
 *
 * @return list<Request>
 */
function calendarCalls(string $method): array
{
    return Http::recorded(fn (Request $request): bool => $request->method() === $method && str_contains($request->url(), 'googleapis.com/calendar'))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

/** @param array<string, mixed> $fields */
function googleInvite(Candidate $candidate, array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->post(route('recruitment.candidates.invite', [test()->requisition, $candidate]), [
        'interview_at' => '2026-11-12T10:00',
        'interview_mode' => 'online',
        'note' => 'Mohon hadir 10 menit lebih awal.',
        'participants' => [test()->hr->id],
        ...$fields,
    ]);
}

function googleCandidate(array $overrides = []): Candidate
{
    return Candidate::factory()->for(test()->requisition)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com', ...$overrides]);
}

/**
 * A candidate whose interview is already an event on Google, on the 12th at 10:00.
 *
 * @param  array<string, mixed>  $overrides
 */
function googleInvitedCandidate(array $overrides = []): Candidate
{
    return googleCandidate([
        'invited_at' => now(),
        'invite_expires_at' => CarbonImmutable::parse('2026-11-12 23:59:59', 'Asia/Jakarta')->utc(),
        'interview_at' => CarbonImmutable::parse('2026-11-12 10:00', 'Asia/Jakarta')->utc(),
        'interview_mode' => 'online',
        'interview_place' => 'https://meet.google.com/abc-defg-hij',
        'google_event_id' => 'evt123',
        'google_etag' => '"etag-1"',
        'google_meet_link' => 'https://meet.google.com/abc-defg-hij',
        'google_responses' => ['budi@example.com' => 'needsAction'],
        ...$overrides,
    ]);
}

/** @return list<string> */
function googleNoticeTitles(User $user): array
{
    return $user->notifications()->pluck('data')->map(fn (array $data): string => (string) $data['title'])->all();
}

// --- Connecting ---

it('sends HR to Google with the narrow calendar permission and a one-off state', function () {
    $this->actingAs($this->hr)->get(route('recruitment.google.connect', $this->requisition))
        ->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth?');

    $state = session('google_oauth.state');
    parse_str((string) parse_url($this->actingAs($this->hr)->get(route('recruitment.google.connect', $this->requisition))->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('client-id.apps.googleusercontent.com')
        ->and($query['redirect_uri'])->toBe('https://hr-vpn.example.test/google/callback')
        ->and($query['access_type'])->toBe('offline')
        ->and($query['prompt'])->toBe('consent')
        ->and(explode(' ', $query['scope']))->toContain('https://www.googleapis.com/auth/calendar.events')
        ->and(strlen((string) $state))->toBe(40);
});

it('lets only those who run recruitment connect, and says so when the server is not set up', function () {
    $this->actingAs($this->supervisor)->get(route('recruitment.google.connect', $this->requisition))->assertForbidden();

    config(['services.google.client_id' => null]);

    $this->actingAs($this->hr)->get(route('recruitment.google.connect', $this->requisition))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error');

    expect(session('google_oauth'))->toBeNull();
});

it('keeps the account Google sends HR back with, and stores the refresh token encrypted', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-1', 'refresh_token' => 'refresh-secret-1', 'expires_in' => 3599,
            'scope' => 'openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/calendar.events',
        ]),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'recruitment@majujaya.test']),
    ]);

    $this->actingAs($this->hr)
        ->withSession(['google_oauth' => ['state' => 'abc', 'user' => $this->hr->id, 'requisition' => $this->requisition->id]])
        ->get('/google/callback?state=abc&code=the-code')
        ->assertRedirect(route('recruitment.show', $this->requisition));

    $connection = GoogleConnection::acrossCompanies()->where('company_id', $this->company->id)->firstOrFail();

    expect($connection->google_email)->toBe('recruitment@majujaya.test')
        ->and($connection->refresh_token)->toBe('refresh-secret-1')
        ->and($connection->needs_reconnect_at)->toBeNull()
        ->and($connection->connected_by_user_id)->toBe($this->hr->id)
        ->and(DB::table('google_connections')->value('refresh_token'))->not->toContain('refresh-secret-1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oauth2.googleapis.com/token')
        && $request['code'] === 'the-code' && $request['grant_type'] === 'authorization_code');
});

it('connects nothing when the state is wrong, the consent was refused, the calendar permission was unticked, or there is no refresh token', function () {
    $pending = ['google_oauth' => ['state' => 'abc', 'user' => $this->hr->id, 'requisition' => $this->requisition->id]];

    $this->actingAs($this->hr)->withSession($pending)->get('/google/callback?state=forged&code=x')->assertRedirect(route('recruitment.index'));
    $this->actingAs($this->hr)->withSession($pending)->get('/google/callback?state=abc&error=access_denied')->assertRedirect(route('recruitment.show', $this->requisition));

    Http::fake([
        'oauth2.googleapis.com/token' => Http::sequence()
            ->push(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'scope' => 'openid email'])
            ->push(['access_token' => 'a', 'expires_in' => 3600, 'scope' => 'https://www.googleapis.com/auth/calendar.events']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'recruitment@majujaya.test']),
    ]);

    $this->actingAs($this->hr)->withSession($pending)->get('/google/callback?state=abc&code=x')->assertInertiaFlash('toast.type', 'error');
    $this->actingAs($this->hr)->withSession($pending)->get('/google/callback?state=abc&code=x')->assertInertiaFlash('toast.type', 'error');

    expect(GoogleConnection::acrossCompanies()->count())->toBe(0);
});

it('does not take a callback that someone else started', function () {
    $pending = ['google_oauth' => ['state' => 'abc', 'user' => $this->hr->id, 'requisition' => $this->requisition->id]];
    $other = googleUser(Role::HrAdmin, $this->company, 'Hari HR');

    $this->actingAs($other)->withSession($pending)->get('/google/callback?state=abc&code=x')->assertRedirect(route('recruitment.index'));

    expect(GoogleConnection::acrossCompanies()->count())->toBe(0);
});

it('disconnects: tells Google, forgets the account, and goes back to the app\'s own email', function () {
    connectedGoogle();
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response('')]);

    $this->actingAs($this->hr)->delete(route('recruitment.google.disconnect', $this->requisition))->assertRedirect();

    expect(GoogleConnection::acrossCompanies()->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oauth2.googleapis.com/revoke'));

    $candidate = googleCandidate();
    googleInvite($candidate, ['interview_place' => 'https://meet.google.com/aaa-bbbb-ccc']);

    Mail::assertQueued(CandidateInvitation::class);
});

it('shows HR whether Google is ready, and nobody else', function () {
    connectedGoogle();

    $this->actingAs($this->hr)->get(route('recruitment.show', $this->requisition))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('requisition.google.ready', true)
            ->where('requisition.google.email', 'recruitment@majujaya.test')
            ->where('requisition.google.canManage', true));

    $this->actingAs($this->supervisor)->get(route('recruitment.show', $this->requisition))
        ->assertInertia(fn (Assert $page) => $page->where('requisition.google', null));
});

// --- Inviting through Google ---

it('makes the event with a Meet room and lets Google send the only invitation', function () {
    connectedGoogle();
    fakeCalendar();
    $candidate = googleCandidate();

    googleInvite($candidate)->assertRedirect()->assertSessionDoesntHaveErrors();

    Mail::assertNothingQueued();

    $created = calendarCalls('POST');
    expect($created)->toHaveCount(1);

    $request = $created[0];
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
    $body = $request->data();

    expect($query)->toMatchArray(['conferenceDataVersion' => '1', 'sendUpdates' => 'all'])
        ->and($body['conferenceData']['createRequest']['conferenceSolutionKey']['type'])->toBe('hangoutsMeet')
        ->and($body['start'])->toBe(['dateTime' => '2026-11-12T10:00:00+07:00', 'timeZone' => 'Asia/Jakarta'])
        ->and($body['end']['dateTime'])->toBe('2026-11-12T11:00:00+07:00')
        ->and($body['summary'])->toBe('Interview Staf Gudang — Budi Santoso (PT Maju Jaya)')
        // The candidate and the people who sit in, not the connected account itself, and none sees the others.
        ->and(collect($body['attendees'])->pluck('email')->sort()->values()->all())->toBe(collect(['budi@example.com', $this->hr->email, $this->boss->email])->sort()->values()->all())
        ->and($body['guestsCanSeeOtherGuests'])->toBeFalse()
        ->and($body['guestsCanInviteOthers'])->toBeFalse()
        // The personal link to the form is in the description, and the Meet room is named until it has a link.
        ->and($body['description'])->toContain('/lamaran/')->toContain('Mohon hadir 10 menit lebih awal.')
        ->and($body)->not->toHaveKey('location');

    $candidate->refresh();
    expect($candidate->google_event_id)->toBe('evt123')
        ->and($candidate->google_etag)->toBe('"etag-1"')
        ->and($candidate->google_meet_link)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($candidate->interview_place)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($candidate->google_responses)->toBe(['budi@example.com' => 'needsAction'])
        ->and($candidate->invitation_emailed)->toBeTrue()
        ->and($candidate->invitation_message)->toContain('https://meet.google.com/abc-defg-hij');
});

it('does not ask HR for a Meet link when Google makes the room, but still asks for an address when it is in person', function () {
    connectedGoogle();
    fakeCalendar(['hangoutLink' => null]);
    $candidate = googleCandidate();

    googleInvite($candidate, ['interview_mode' => 'onsite'])->assertSessionHasErrors('interview_place');
    expect(calendarCalls('POST'))->toBeEmpty();

    googleInvite($candidate, ['interview_mode' => 'onsite', 'interview_place' => 'Kantor Pusat, lantai 3'])->assertSessionDoesntHaveErrors();

    $body = calendarCalls('POST')[0]->data();
    expect($body['location'])->toBe('Kantor Pusat, lantai 3')
        ->and($body)->not->toHaveKey('conferenceData');

    // Google can be slow to make the room: the link is read again, and the routine check fills it in if it is still not there.
    $candidate = googleCandidate(['name' => 'Ani', 'email' => 'ani@example.com']);
    googleInvite($candidate)->assertSessionDoesntHaveErrors();
    expect($candidate->refresh()->interview_place)->toBe('Google Meet — tautan ada di undangan Google Calendar');
});

it('asks nobody twice: a new time changes the same event instead of making another', function () {
    connectedGoogle();
    // Google answers with everyone who was asked.
    fakeCalendar(['attendees' => [
        ['email' => 'recruitment@majujaya.test', 'organizer' => true, 'self' => true, 'responseStatus' => 'accepted'],
        ['email' => 'budi@example.com', 'responseStatus' => 'needsAction'],
        ['email' => $this->hr->email, 'responseStatus' => 'accepted'],
        ['email' => $this->boss->email, 'responseStatus' => 'needsAction'],
    ]]);
    $candidate = googleCandidate();

    googleInvite($candidate)->assertSessionDoesntHaveErrors();
    googleInvite($candidate, ['interview_at' => '2026-11-13T14:30'])->assertSessionDoesntHaveErrors();

    expect(calendarCalls('POST'))->toHaveCount(1);

    $patches = calendarCalls('PATCH');
    expect($patches)->toHaveCount(1)
        ->and($patches[0]->url())->toContain('/events/evt123')->toContain('sendUpdates=all')
        ->and($patches[0]->data()['start']['dateTime'])->toBe('2026-11-13T14:30:00+07:00')
        // The same people are asked, so they are not sent again and their answers are not reset more than Google does.
        ->and($patches[0]->data())->not->toHaveKey('attendees')
        // The room exists already.
        ->and($patches[0]->data())->not->toHaveKey('conferenceData');
});

it('makes a new event when the old one was deleted on Google', function () {
    connectedGoogle();
    $candidate = googleInvitedCandidate(['google_missing_at' => null]);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt123*' => Http::response(['error' => ['message' => 'Not Found']], 404),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response(googleEvent(['id' => 'evt456'])),
    ]);

    googleInvite($candidate, ['interview_at' => '2026-11-13T09:00'])->assertSessionDoesntHaveErrors();

    expect($candidate->refresh()->google_event_id)->toBe('evt456')->and($candidate->google_missing_at)->toBeNull();
});

it('undoes the whole invitation when Google cannot be reached, so nobody is told and HR can try again', function () {
    connectedGoogle();
    Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'Backend Error']], 503)]);
    $candidate = googleCandidate();

    googleInvite($candidate)->assertSessionHasErrors('google');

    $candidate->refresh();
    expect($candidate->invited_at)->toBeNull()
        ->and($candidate->google_event_id)->toBeNull()
        ->and($candidate->interviewers()->count())->toBe(0)
        ->and(googleNoticeTitles($this->boss))->toBe([]);
    Mail::assertNothingQueued();
});

it('reads a failed request that is not a server error as a no, without trying it again', function () {
    connectedGoogle();
    Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'Google Calendar API has not been used in project 1 before or it is disabled.']], 403)]);

    googleInvite(googleCandidate())->assertSessionHasErrors('google');

    expect(calendarCalls('POST'))->toHaveCount(1);
});

it('marks the account as refused when Google rejects its refresh token, tells HR once, and sends nothing', function () {
    $connection = connectedGoogle(['access_token' => null, 'access_token_expires_at' => null]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);
    $candidate = googleCandidate();

    googleInvite($candidate)->assertSessionHasErrors('google');
    googleInvite($candidate)->assertSessionDoesntHaveErrors('google');

    expect($connection->refresh()->needs_reconnect_at)->not->toBeNull()
        ->and($candidate->refresh()->google_event_id)->toBeNull()
        ->and(collect(googleNoticeTitles($this->hr))->filter(fn (string $title): bool => str_contains($title, 'dihubungkan ulang')))->toHaveCount(1);

    // From now on the invitation goes out the old way, with a link HR pastes in.
    Mail::assertQueued(CandidateInvitation::class, 0);
    googleInvite($candidate, ['interview_place' => 'https://meet.google.com/aaa-bbbb-ccc'])->assertSessionDoesntHaveErrors();
    Mail::assertQueued(CandidateInvitation::class, 1);
});

it('asks for a new token and tries once more when Google says the one in use is no good', function () {
    connectedGoogle();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::sequence()
            ->push(['error' => ['message' => 'Invalid Credentials']], 401)
            ->push(googleEvent()),
    ]);

    googleInvite(googleCandidate())->assertSessionDoesntHaveErrors();

    $retried = calendarCalls('POST');
    expect($retried)->toHaveCount(2)
        ->and($retried[0]->header('Authorization'))->toBe(['Bearer access-token'])
        ->and($retried[1]->header('Authorization'))->toBe(['Bearer fresh-token']);
});

it('invites the old way when the company has no Google account, or the server is not set up for Google', function () {
    $candidate = googleCandidate();

    googleInvite($candidate)->assertSessionHasErrors('interview_place');

    googleInvite($candidate, ['interview_place' => 'https://meet.google.com/aaa-bbbb-ccc'])->assertSessionDoesntHaveErrors();
    Mail::assertQueued(CandidateInvitation::class, 1);

    connectedGoogle();
    config(['services.google.client_secret' => null]);
    $second = googleCandidate(['name' => 'Ani', 'email' => 'ani@example.com']);

    googleInvite($second)->assertSessionHasErrors('interview_place');
});

it('keeps another company\'s account out of this company\'s invitations', function () {
    GoogleConnection::factory()->create(['google_email' => 'lain@lain.test', 'access_token' => 'x', 'access_token_expires_at' => now()->addHour()]);

    googleInvite(googleCandidate())->assertSessionHasErrors('interview_place');
});

// --- Turning a candidate down, removing one, closing the position ---

it('takes the interview off the calendar when HR turns the candidate down, without Google emailing anyone', function () {
    connectedGoogle();
    fakeCalendar();
    $candidate = googleInvitedCandidate();

    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$this->requisition, $candidate]), ['note' => 'Kurang cocok'])->assertRedirect();

    $deleted = calendarCalls('DELETE');
    expect($deleted)->toHaveCount(1)
        ->and($deleted[0]->url())->toContain('/events/evt123')->toContain('sendUpdates=none')
        ->and($candidate->refresh()->google_event_id)->toBeNull()
        ->and($candidate->isRejected())->toBeTrue();
});

it('still turns the candidate down when Google cannot be reached', function () {
    connectedGoogle();
    Http::fake(['www.googleapis.com/*' => Http::response('', 503)]);
    $candidate = googleInvitedCandidate();

    $this->actingAs($this->hr)->post(route('recruitment.candidates.reject', [$this->requisition, $candidate]), ['note' => 'Kurang cocok'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($candidate->refresh()->isRejected())->toBeTrue()->and($candidate->google_event_id)->toBe('evt123');
});

it('takes the interview off the calendar when HR removes the candidate', function () {
    connectedGoogle();
    fakeCalendar();
    $candidate = googleInvitedCandidate();

    $this->actingAs($this->hr)->delete(route('recruitment.candidates.destroy', [$this->requisition, $candidate]))->assertRedirect();

    expect(calendarCalls('DELETE'))->toHaveCount(1)
        ->and(Candidate::query()->whereKey($candidate->id)->exists())->toBeFalse();
});

it('lets Google send the cancellation when the position is closed and HR chooses to tell the candidates', function () {
    connectedGoogle();
    fakeCalendar();
    $google = googleInvitedCandidate();
    $manual = googleCandidate([
        'name' => 'Ani', 'email' => 'ani@example.com', 'invited_at' => now(), 'interview_at' => now()->addDays(2), 'interview_mode' => 'online', 'interview_place' => 'https://meet.google.com/x',
    ]);

    $this->actingAs($this->hr)->post(route('recruitment.cancel', $this->requisition), ['note' => 'Tidak jadi', 'notify_candidates' => '1'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', "{$this->requisition->reference()} dibatalkan. 2 kandidat dikabari lewat email.");

    // Budi hears it from Google; Ani, who was invited the old way, from the app.
    expect(calendarCalls('DELETE')[0]->url())->toContain('sendUpdates=all');
    Mail::assertQueued(CandidateInterviewCancelled::class, 1);
    Mail::assertQueued(CandidateInterviewCancelled::class, fn (CandidateInterviewCancelled $mail): bool => $mail->hasTo('ani@example.com'));
    expect($google->refresh()->google_event_id)->toBeNull();
});

it('takes the interviews off the calendar quietly when the position is closed and HR does not ask to tell anyone', function () {
    connectedGoogle();
    fakeCalendar();
    googleInvitedCandidate();

    $this->actingAs($this->hr)->post(route('recruitment.cancel', $this->requisition), ['note' => 'Tidak jadi'])->assertRedirect();

    expect(calendarCalls('DELETE')[0]->url())->toContain('sendUpdates=none');
    Mail::assertNotQueued(CandidateInterviewCancelled::class);
});

// --- The routine check ---

it('follows a new time set on Google, moves the reminders and the form link with it, and tells HR, the superior and those chosen', function () {
    connectedGoogle();
    $candidate = googleInvitedCandidate(['candidate_reminded_at' => now(), 'interviewers_reminded_at' => now()]);
    $candidate->interviewers()->sync([$this->hr->id]);

    Http::fake(['www.googleapis.com/*' => Http::response(googleEvent([
        'etag' => '"etag-2"',
        'start' => ['dateTime' => '2026-11-14T15:00:00+07:00'],
        'end' => ['dateTime' => '2026-11-14T16:30:00+07:00'],
    ]))]);

    Artisan::call('recruitment:sync-google-events');

    $candidate->refresh();
    expect($candidate->interview_at->toDateTimeString())->toBe('2026-11-14 08:00:00')
        ->and($candidate->interview_minutes)->toBe(90)
        ->and($candidate->google_etag)->toBe('"etag-2"')
        ->and($candidate->candidate_reminded_at)->toBeNull()
        ->and($candidate->interviewers_reminded_at)->toBeNull()
        // The form opens on the interview day and lapses at the end of it.
        ->and($candidate->invite_expires_at->copy()->setTimezone('Asia/Jakarta')->toDateTimeString())->toBe('2026-11-14 23:59:59')
        ->and(googleNoticeTitles($this->hr))->toContain('Jadwal interview diubah di Google: Staf Gudang')
        ->and(googleNoticeTitles($this->boss))->toContain('Jadwal interview diubah di Google: Staf Gudang');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->header('If-None-Match') === ['"etag-1"']);
});

it('leaves an interview alone when Google says it has not changed, and when only the answers did', function () {
    connectedGoogle();
    $candidate = googleInvitedCandidate();

    Http::fake(['www.googleapis.com/*' => Http::response('', 304)]);
    Artisan::call('recruitment:sync-google-events');

    expect($candidate->refresh()->google_etag)->toBe('"etag-1"')->and(googleNoticeTitles($this->hr))->toBe([]);

    fakeAgain(['www.googleapis.com/*' => Http::response(googleEvent([
        'etag' => '"etag-3"',
        'attendees' => [['email' => 'Budi@Example.com', 'responseStatus' => 'accepted']],
        'hangoutLink' => 'https://meet.google.com/new-link-xyz',
    ]))]);
    Artisan::call('recruitment:sync-google-events');

    $candidate->refresh();
    expect($candidate->google_responses)->toBe(['budi@example.com' => 'accepted'])
        ->and($candidate->google_meet_link)->toBe('https://meet.google.com/new-link-xyz')
        ->and($candidate->interview_place)->toBe('https://meet.google.com/new-link-xyz')
        ->and($candidate->interview_at->toDateTimeString())->toBe('2026-11-12 03:00:00')
        ->and(googleNoticeTitles($this->hr))->toBe([]);
});

it('flags an event deleted on Google for HR once, and does not cancel the candidate on its own', function () {
    connectedGoogle();
    $candidate = googleInvitedCandidate();
    Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'Resource has been deleted']], 410)]);

    Artisan::call('recruitment:sync-google-events');
    Artisan::call('recruitment:sync-google-events');

    $candidate->refresh();
    expect($candidate->google_missing_at)->not->toBeNull()
        ->and($candidate->isRejected())->toBeFalse()
        ->and($candidate->interview_at->toDateTimeString())->toBe('2026-11-12 03:00:00')
        ->and(collect(googleNoticeTitles($this->hr))->filter(fn (string $title): bool => str_starts_with($title, 'Interview dihapus di Google')))->toHaveCount(1);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'GET'))->toHaveCount(1);

    $this->actingAs($this->hr)->get(route('recruitment.show', $this->requisition))
        ->assertInertia(fn (Assert $page) => $page->where('requisition.shortlist.0.googleMissing', true));
});

it('reads an event that Google still returns as cancelled the same way', function () {
    connectedGoogle();
    $candidate = googleInvitedCandidate();
    Http::fake(['www.googleapis.com/*' => Http::response(googleEvent(['status' => 'cancelled']))]);

    Artisan::call('recruitment:sync-google-events');

    expect($candidate->refresh()->google_missing_at)->not->toBeNull();
});

it('stops and tells HR once when Google no longer accepts the account, and picks up again after reconnecting', function () {
    $connection = connectedGoogle(['access_token' => null, 'access_token_expires_at' => null]);
    googleInvitedCandidate();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    Artisan::call('recruitment:sync-google-events');
    Artisan::call('recruitment:sync-google-events');

    expect($connection->refresh()->needs_reconnect_at)->not->toBeNull()
        ->and(collect(googleNoticeTitles($this->hr))->filter(fn (string $title): bool => str_contains($title, 'dihubungkan ulang')))->toHaveCount(1);
    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'googleapis.com'))->count())->toBe(1);

    $connection->forceFill(['needs_reconnect_at' => null])->save();
    fakeAgain([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
        'www.googleapis.com/*' => Http::response('', 304),
    ]);
    Artisan::call('recruitment:sync-google-events');

    expect($connection->refresh()->last_synced_at)->not->toBeNull();
});

it('only looks at interviews still in the process and not long past', function () {
    connectedGoogle();
    Http::fake(['www.googleapis.com/*' => Http::response('', 304)]);

    googleInvitedCandidate(['rejected_at' => now()]);
    googleInvitedCandidate(['name' => 'Lama', 'email' => 'lama@example.com', 'interview_at' => now()->subDays(3), 'google_event_id' => 'old']);
    googleInvitedCandidate(['name' => 'Tanpa', 'email' => 'tanpa@example.com', 'google_event_id' => null]);

    Artisan::call('recruitment:sync-google-events');

    expect(Http::recorded()->count())->toBe(0);
});

it('does nothing when the server is not set up for Google', function () {
    connectedGoogle();
    googleInvitedCandidate();
    config(['services.google.redirect' => null]);

    Artisan::call('recruitment:sync-google-events');

    expect(Http::recorded()->count())->toBe(0);
});

it('is on the private entrance only', function () {
    foreach (['recruitment.google.connect', 'recruitment.google.disconnect', 'recruitment.google.callback'] as $name) {
        expect(PublicRoutes::classify(Route::getRoutes()->getByName($name)))->toBe('private');
    }
});
