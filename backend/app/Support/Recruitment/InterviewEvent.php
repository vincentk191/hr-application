<?php

namespace App\Support\Recruitment;

use App\Models\Candidate;
use App\Models\GoogleConnection;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Google\GoogleAuthLost;
use App\Support\Google\GoogleCalendar;
use App\Support\Google\GoogleEventGone;
use App\Support\Google\GoogleOAuth;
use App\Support\Google\GoogleUnavailable;
use App\Support\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * An interview as an event on the company's Google Calendar (REC-INT). When HR has connected the company's Google account,
 * inviting a candidate makes the event, with a Google Meet room, and Google emails the invitation from that account to the
 * candidate and to the people who sit in. A new time changes the same event; a cancellation deletes it.
 *
 * Everyone invited is hidden from the other guests (nobody sees who else was asked), so the candidate never sees the
 * interviewers' addresses nor the interviewers the candidate's.
 *
 * The event's ID, its etag and the Meet link are kept on the candidate, which is what lets the routine check
 * (GoogleInterviewSync) notice a change made on Google itself.
 */
class InterviewEvent
{
    /** Stands in the saved place, and in the event's description, until Google has given the Meet link. */
    public const MEET_PENDING = 'Google Meet — tautan ada di undangan Google Calendar';

    public function __construct(private GoogleOAuth $oauth, private GoogleCalendar $calendar) {}

    /**
     * The company's connection if invitations can go through it now: configured, connected, and not refused by Google.
     */
    public function usableFor(int $companyId): ?GoogleConnection
    {
        $connection = $this->connectionFor($companyId);

        return $connection?->isUsable() === true ? $connection : null;
    }

    /**
     * The company's connection, working or not, when the app is set up to talk to Google at all.
     */
    public function connectionFor(int $companyId): ?GoogleConnection
    {
        if (! $this->oauth->isConfigured()) {
            return null;
        }

        return GoogleConnection::acrossCompanies()->where('company_id', $companyId)->first();
    }

    /**
     * What the Interview tab shows about the company's Google account.
     *
     * @return array{configured: bool, missing: list<string>, connected: bool, ready: bool, email: string|null, canManage: bool}
     */
    public function status(int $companyId, bool $canManage): array
    {
        $connection = $this->connectionFor($companyId);

        return [
            'configured' => $this->oauth->isConfigured(),
            // Which environment settings the server lacks, by name, for HR to pass on.
            'missing' => $this->oauth->missingSettings(),
            'connected' => $connection !== null,
            // Invitations go through Google: connected, and Google has not refused the account.
            'ready' => $connection?->isUsable() === true,
            'email' => $connection?->google_email,
            'canManage' => $canManage,
        ];
    }

    /**
     * Makes the interview's event, or changes the one it already has, and keeps what Google answered. Whoever is invited
     * is emailed by Google. The candidate's `interview_place` becomes the Meet link for an online interview.
     *
     * @param  string|null  $formLink  the candidate's personal form link, null when the form is already filled in
     *
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    public function sync(GoogleConnection $connection, Requisition $requisition, Candidate $candidate, ?string $formLink, ?string $note): void
    {
        $requisition->loadMissing('company');
        $online = $candidate->interview_mode === 'online';
        $fields = $this->fields($connection, $requisition, $candidate, $formLink, $note, $online);

        if ($candidate->hasGoogleEvent()) {
            try {
                $event = $this->calendar->patch(
                    $connection,
                    (string) $candidate->google_event_id,
                    $this->withoutUnchangedGuests($fields, $candidate),
                    $this->meetChange($candidate, $online),
                    GoogleCalendar::NOTIFY_ALL,
                );
            } catch (GoogleEventGone) {
                // Deleted on Google since: the invitation HR is sending now starts a new event.
                $event = $this->create($connection, $fields, $online);
            }
        } else {
            $event = $this->create($connection, $fields, $online);
        }

        $this->remember($connection, $candidate, $event, $online);
    }

    /**
     * Takes the interview off everyone's calendar. Failing to reach Google must not stop HR from turning a candidate down or
     * closing a position, so this reports whether it worked rather than throwing.
     *
     * @param  string  $notify  GoogleCalendar::NOTIFY_ALL to have Google email the cancellation, NOTIFY_NONE for none
     * @return bool whether the interview now has no event on Google
     */
    public function cancel(Candidate $candidate, string $notify): bool
    {
        if (! $candidate->hasGoogleEvent()) {
            return true;
        }

        $connection = $this->usableFor((int) $candidate->company_id);

        if ($connection === null) {
            return false;
        }

        try {
            $this->calendar->delete($connection, (string) $candidate->google_event_id, $notify);
        } catch (GoogleAuthLost $lost) {
            $this->lost($lost);

            return false;
        } catch (GoogleUnavailable $unavailable) {
            Log::warning('Google event could not be deleted.', ['candidate' => $candidate->id, 'error' => $unavailable->getMessage()]);

            return false;
        }

        $candidate->forceFill([
            'google_event_id' => null,
            'google_etag' => null,
            'google_meet_link' => null,
            'google_responses' => null,
            'google_missing_at' => null,
            'google_synced_at' => null,
        ])->save();

        return true;
    }

    /**
     * Keeps the account marked as refused and tells HR, once. The mark is written again here, on its own: the call that found
     * out may have been inside an invitation that was then undone, and undoing it must not forget that Google said no.
     */
    public function lost(GoogleAuthLost $lost): void
    {
        GoogleConnection::acrossCompanies()
            ->whereKey($lost->connection->getKey())
            ->whereNull('needs_reconnect_at')
            ->update(['needs_reconnect_at' => now(), 'access_token' => null, 'access_token_expires_at' => null]);

        if ($lost->first) {
            app(Notifier::class)->googleNeedsReconnect($lost->connection);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    private function create(GoogleConnection $connection, array $fields, bool $online): array
    {
        // Our own ID, hex so it is one Google accepts. It is what makes sending the request again safe. A field with
        // nothing in it (an online interview has no address) is left out rather than sent empty.
        $event = ['id' => bin2hex(random_bytes(12)), ...array_filter($fields, fn (mixed $value): bool => $value !== null)];

        return $this->calendar->insert($connection, $event, $online, GoogleCalendar::NOTIFY_ALL);
    }

    /**
     * What to do about the Meet room when the event is changed: add one for an interview that became online, remove it for
     * one that became in person, otherwise leave it.
     */
    private function meetChange(Candidate $candidate, bool $online): ?bool
    {
        return match (true) {
            $online && blank($candidate->google_meet_link) => true,
            ! $online && filled($candidate->google_meet_link) => false,
            default => null,
        };
    }

    /**
     * The guests are only sent when they differ from who was asked before, so changing the time does not reset anyone's
     * answer more than Google itself does.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function withoutUnchangedGuests(array $fields, Candidate $candidate): array
    {
        $asked = array_map('strtolower', array_keys($candidate->google_responses ?? []));
        $now = array_map(fn (array $guest): string => strtolower((string) $guest['email']), $fields['attendees']);

        sort($asked);
        sort($now);

        if ($asked === $now) {
            unset($fields['attendees']);
        }

        return $fields;
    }

    /**
     * The event as Google wants it: when, what it is called, what the description says, and who is asked.
     *
     * @return array<string, mixed>
     */
    private function fields(GoogleConnection $connection, Requisition $requisition, Candidate $candidate, ?string $formLink, ?string $note, bool $online): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $start = CarbonImmutable::instance($candidate->interview_at)->setTimezone($timezone);
        $end = $start->addMinutes($candidate->interview_minutes ?: InterviewCalendar::DEFAULT_MINUTES);

        // The description is the invitation's own text, with the Meet room named for what it is until its link exists.
        $shown = $candidate->replicate();
        $shown->interview_place = $online ? ($candidate->google_meet_link ?: self::MEET_PENDING) : $candidate->interview_place;

        return [
            'summary' => "Interview {$requisition->position_name} — {$candidate->name} ({$requisition->company->name})",
            'description' => InvitationMessage::text($shown, $requisition, $formLink, $note),
            'location' => $online ? null : trim((string) $candidate->interview_place),
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => $timezone],
            'attendees' => $this->guests($connection, $requisition, $candidate),
            'guestsCanSeeOtherGuests' => false,
            'guestsCanInviteOthers' => false,
            'guestsCanModify' => false,
            'extendedProperties' => ['private' => ['hrApp' => 'hr-dashboard', 'hrCandidateId' => (string) $candidate->id]],
        ];
    }

    /**
     * The candidate (when they have an email) and the people who sit in, without the connected account itself.
     *
     * @return list<array{email: string, displayName: string}>
     */
    private function guests(GoogleConnection $connection, Requisition $requisition, Candidate $candidate): array
    {
        $candidate->unsetRelation('interviewers');

        $people = app(InterviewParticipants::class)->attendees($candidate, $requisition)
            ->filter(fn (User $user): bool => $user->is_active && filled($user->email))
            ->map(fn (User $user): array => ['email' => (string) $user->email, 'displayName' => $user->name])
            ->values()
            ->all();

        $guests = [
            ...$people,
            ...(filled($candidate->email) ? [['email' => (string) $candidate->email, 'displayName' => $candidate->name]] : []),
        ];

        $seen = [strtolower($connection->google_email) => true];
        $unique = [];

        foreach ($guests as $guest) {
            $key = strtolower($guest['email']);

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $guest;
            }
        }

        return $unique;
    }

    /**
     * Keeps what Google answered on the candidate: the event, its version, the Meet link and who answered what.
     *
     * @param  array<string, mixed>  $event
     */
    private function remember(GoogleConnection $connection, Candidate $candidate, array $event, bool $online): void
    {
        $meet = $online ? $this->meetLinkOf($connection, $event) : null;

        $candidate->forceFill([
            'google_event_id' => (string) $event['id'],
            'google_etag' => $event['etag'] ?? null,
            'google_meet_link' => $meet,
            'google_responses' => self::responsesOf($event),
            'google_missing_at' => null,
            'google_synced_at' => now(),
            // HR's message and the screens read the Meet link where the pasted one used to be.
            'interview_place' => $online ? ($meet ?? self::MEET_PENDING) : $candidate->interview_place,
        ])->save();
    }

    /**
     * The Meet link of a new event. Google can take a moment to make the room, so an event that answers without one is read
     * once more; if it still has none, the routine check fills it in when it appears.
     *
     * @param  array<string, mixed>  $event
     */
    private function meetLinkOf(GoogleConnection $connection, array $event): ?string
    {
        if (filled($event['hangoutLink'] ?? null)) {
            return (string) $event['hangoutLink'];
        }

        try {
            $again = $this->calendar->find($connection, (string) $event['id']);
        } catch (GoogleEventGone|GoogleUnavailable|GoogleAuthLost) {
            return null;
        }

        return filled($again['hangoutLink'] ?? null) ? (string) $again['hangoutLink'] : null;
    }

    /**
     * Each guest's answer by email; the organiser's own line is left out.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, string>
     */
    public static function responsesOf(array $event): array
    {
        $responses = [];

        foreach ((array) ($event['attendees'] ?? []) as $guest) {
            if (! is_array($guest) || blank($guest['email'] ?? null) || ($guest['organizer'] ?? false) === true || ($guest['self'] ?? false) === true) {
                continue;
            }

            $responses[strtolower((string) $guest['email'])] = (string) ($guest['responseStatus'] ?? 'needsAction');
        }

        return $responses;
    }
}
