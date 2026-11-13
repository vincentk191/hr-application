<?php

namespace App\Support\Recruitment;

use App\Models\Candidate;
use App\Models\GoogleConnection;
use App\Models\Requisition;
use App\Support\Google\GoogleAuthLost;
use App\Support\Google\GoogleCalendar;
use App\Support\Google\GoogleEventGone;
use App\Support\Google\GoogleOAuth;
use App\Support\Google\GoogleUnavailable;
use App\Support\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The routine check of the interviews made through Google (REC-INT). HR may change an event on Google Calendar itself, so
 * the app reads each interview that is still ahead (or was yesterday) and follows what it finds:
 *
 * - a new time or length is adopted, with a notice to HR and the people who sit in (Google has already emailed the
 *   candidate), and the reminders and the form link follow the new day;
 * - a deleted event is only flagged for HR, who decide, since a deleted event may be a mistake;
 * - the Meet link and who has accepted or declined are kept up to date.
 *
 * Reading is cheap: each event is asked for with the version already known, and Google answers "unchanged" without sending it.
 * A company whose Google account stopped working is skipped, and HR told once; it picks up again once reconnected.
 */
class GoogleInterviewSync
{
    public function __construct(private GoogleOAuth $oauth, private GoogleCalendar $calendar, private InterviewEvent $events, private Notifier $notifier) {}

    /**
     * @return array{checked: int, moved: int, deleted: int}
     */
    public function run(): array
    {
        $totals = ['checked' => 0, 'moved' => 0, 'deleted' => 0];

        if (! $this->oauth->isConfigured()) {
            return $totals;
        }

        foreach (GoogleConnection::acrossCompanies()->whereNull('needs_reconnect_at')->get() as $connection) {
            $this->check($connection, $totals);
        }

        return $totals;
    }

    /**
     * @param  array{checked: int, moved: int, deleted: int}  $totals
     */
    private function check(GoogleConnection $connection, array &$totals): void
    {
        $candidates = Candidate::acrossCompanies()
            ->where('company_id', $connection->company_id)
            ->inProcess()
            ->whereNotNull('google_event_id')
            ->whereNull('google_missing_at')
            ->where('interview_at', '>', now()->subDay())
            ->orderBy('interview_at')
            ->get();

        $requisitions = Requisition::acrossCompanies()->with('company')->whereIn('id', $candidates->pluck('requisition_id')->unique())->get()->keyBy('id');

        foreach ($candidates as $candidate) {
            $requisition = $requisitions->get($candidate->requisition_id);

            if (! $requisition instanceof Requisition) {
                continue;
            }

            try {
                $event = $this->calendar->find($connection, (string) $candidate->google_event_id, $candidate->google_etag);
            } catch (GoogleEventGone) {
                $this->flagDeleted($requisition, $candidate, $totals);

                continue;
            } catch (GoogleAuthLost $lost) {
                $this->events->lost($lost);

                return;
            } catch (GoogleUnavailable $unavailable) {
                // Try again at the next run rather than half-checking the rest with a Google that is not answering.
                Log::warning('Google interviews could not be checked.', ['company' => $connection->company_id, 'error' => $unavailable->getMessage()]);

                return;
            }

            $totals['checked']++;

            if ($event === null) {
                $candidate->forceFill(['google_synced_at' => now()])->save();

                continue;
            }

            if (($event['status'] ?? null) === 'cancelled') {
                $this->flagDeleted($requisition, $candidate, $totals);

                continue;
            }

            if ($this->follow($requisition, $candidate, $event)) {
                $totals['moved']++;
            }
        }

        $connection->forceFill(['last_synced_at' => now()])->save();
    }

    /**
     * Takes over what changed on Google.
     *
     * @param  array<string, mixed>  $event
     * @return bool whether the time or length of the interview changed
     */
    private function follow(Requisition $requisition, Candidate $candidate, array $event): bool
    {
        $moved = false;
        $start = $this->moment($event['start'] ?? null);
        $end = $this->moment($event['end'] ?? null);

        if ($start !== null && $end !== null) {
            $minutes = max(5, (int) round($start->diffInMinutes($end)));
            $moved = ! $start->equalTo($candidate->interview_at) || $minutes !== (int) $candidate->interview_minutes;

            if ($moved) {
                $candidate->forceFill([
                    'interview_at' => $start->utc(),
                    'interview_minutes' => $minutes,
                    // A new time is a new day to remind.
                    'candidate_reminded_at' => null,
                    'interviewers_reminded_at' => null,
                    'evaluation_prompted_at' => null,
                ]);

                // The form opens on the interview day, so its link follows the day.
                if ($candidate->form_submitted_at === null && $candidate->invite_expires_at !== null) {
                    $candidate->forceFill(['invite_expires_at' => $candidate->formClosesAt()]);
                }
            }
        }

        $link = $event['hangoutLink'] ?? null;

        if ($candidate->interview_mode === 'online' && filled($link) && $link !== $candidate->google_meet_link) {
            $candidate->google_meet_link = (string) $link;
            $candidate->interview_place = (string) $link;
        }

        $candidate->forceFill([
            'google_etag' => $event['etag'] ?? $candidate->google_etag,
            'google_responses' => InterviewEvent::responsesOf($event),
            'google_synced_at' => now(),
        ])->save();

        if ($moved) {
            $this->notifier->interviewMovedOnGoogle($requisition, $candidate);
        }

        return $moved;
    }

    /**
     * @param  array{checked: int, moved: int, deleted: int}  $totals
     */
    private function flagDeleted(Requisition $requisition, Candidate $candidate, array &$totals): void
    {
        $candidate->forceFill(['google_missing_at' => now(), 'google_synced_at' => now()])->save();
        $this->notifier->interviewEventDeleted($requisition, $candidate);
        $totals['deleted']++;
    }

    /**
     * A moment of a timed event; an all-day event has none, and is left as it is.
     *
     * @param  array<string, mixed>|null  $part
     */
    private function moment(?array $part): ?CarbonImmutable
    {
        return isset($part['dateTime']) ? CarbonImmutable::parse((string) $part['dateTime']) : null;
    }
}
