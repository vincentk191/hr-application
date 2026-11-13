<?php

namespace App\Support\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * An interview as a calendar event, an `.ics` file that any calendar app can add (REC-INT): who, when and for how long, the
 * address when it is in person, and the Meet link in the description when it is online.
 *
 * Two copies of it exist. The one for the people who run the interview names the candidate and lists them, the direct
 * superior and the person who invited as participants; the candidate's own, attached to the invitation email, says only what
 * the candidate needs: the position, the company, when and where, and who invited them. The people who sit in are those HR
 * chose for the candidate and the direct superior (InterviewParticipants). Both carry the same event ID, so
 * after a new time the file added again updates the event instead of making a second one.
 */
class InterviewCalendar
{
    /** The length of an interview unless HR says otherwise. */
    public const DEFAULT_MINUTES = 60;

    /** 2026-01-01 00:00 UTC: the event's version counts the seconds since, so a re-invite always gives a higher one. */
    private const VERSION_EPOCH = 1767225600;

    /**
     * For HR and the direct superior: names the candidate.
     *
     * @param  bool  $withCandidateEmail  the candidate is also a participant; only for someone who may see their email
     */
    public function forInterviewers(Candidate $candidate, Requisition $requisition, User $viewer, bool $withCandidateEmail): string
    {
        $organizer = $this->organizer($candidate, $viewer);

        $candidate->loadMissing('interviewers');
        $people = app(InterviewParticipants::class)->attendees($candidate, $requisition)
            ->reject(fn (User $user): bool => $user->email === ($organizer['email'] ?? null))
            ->map(fn (User $user): array => ['name' => $user->name, 'email' => $user->email])
            ->values()
            ->all();

        $attendees = [
            ...$people,
            ...($withCandidateEmail && filled($candidate->email) ? [['name' => $candidate->name, 'email' => (string) $candidate->email]] : []),
        ];

        return $this->build(
            $candidate,
            "Interview: {$candidate->name} — {$requisition->position_name}",
            ["Kandidat: {$candidate->name}", "Posisi: {$requisition->position_name}"],
            $organizer,
            $attendees,
        );
    }

    /**
     * For the candidate: the position and the company, no one else's details beyond who invited them.
     */
    public function forCandidate(Candidate $candidate, Requisition $requisition): string
    {
        $requisition->loadMissing('company');
        $company = $requisition->company->name;
        $organizer = $this->organizer($candidate, null);

        return $this->build(
            $candidate,
            "Interview {$requisition->position_name} — {$company}",
            ["Posisi: {$requisition->position_name}", "Perusahaan: {$company}"],
            $organizer,
            filled($candidate->email) ? [['name' => $candidate->name, 'email' => (string) $candidate->email]] : [],
        );
    }

    /**
     * The file's name: the candidate and the day.
     */
    public function filename(Candidate $candidate): string
    {
        $when = $candidate->interview_at === null ? null : InvitationMessage::local($candidate->interview_at)->format('Y-m-d');

        return 'interview-'.str($candidate->name)->slug()->value().($when === null ? '' : '-'.$when).'.ics';
    }

    /**
     * @param  list<string>  $lead  the lines that open the description
     * @param  array{name: string, email: string}|null  $organizer
     * @param  list<array{name: string, email: string}>  $attendees
     */
    private function build(Candidate $candidate, string $summary, array $lead, ?array $organizer, array $attendees): string
    {
        $start = CarbonImmutable::instance($candidate->interview_at ?? now())->utc();
        $end = $start->addMinutes($candidate->interview_minutes ?: self::DEFAULT_MINUTES);
        $online = $candidate->interview_mode === 'online';
        $place = trim((string) $candidate->interview_place);
        $isLink = preg_match('#^https?://\S+$#i', $place) === 1;

        $description = [
            ...$lead,
            $online ? 'Format: Daring' : 'Format: Tatap muka',
            ...($place === '' ? [] : [($online ? 'Tautan Meet: ' : 'Alamat: ').$place]),
            ...(filled($candidate->invitation_note) ? ['', 'Catatan: '.trim((string) $candidate->invitation_note)] : []),
        ];

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CMP HR//Interview//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:interview-'.$candidate->id.'@'.$this->host(),
            'DTSTAMP:'.CarbonImmutable::now()->utc()->format('Ymd\THis\Z'),
            'SEQUENCE:'.max(0, (int) ($candidate->invited_at->timestamp ?? self::VERSION_EPOCH) - self::VERSION_EPOCH),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($summary),
            'DESCRIPTION:'.$this->escape(implode("\n", $description)),
            // In person: the address is the location. Online: the link is in the description (and as the event's address), not
            // a place to travel to.
            ...(! $online && $place !== '' ? ['LOCATION:'.$this->escape($place)] : []),
            ...($online && $isLink ? ['URL:'.$place] : []),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            ...($organizer === null ? [] : [$this->person('ORGANIZER', $organizer, '')]),
            ...array_map(fn (array $person): string => $this->person('ATTENDEE', $person, ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE'), $attendees),
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$this->escape($summary),
            'TRIGGER:-PT15M',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(fn (string $line): string => $this->fold($line), $lines))."\r\n";
    }

    /**
     * Who invited the candidate, or failing that whoever is downloading.
     *
     * @return array{name: string, email: string}|null
     */
    private function organizer(Candidate $candidate, ?User $fallback): ?array
    {
        $user = $candidate->invited_by_user_id === null ? null : User::query()->find($candidate->invited_by_user_id);
        $user ??= $fallback;

        return $user === null ? null : ['name' => $user->name, 'email' => $user->email];
    }

    /**
     * @param  array{name: string, email: string}  $person
     */
    private function person(string $property, array $person, string $parameters): string
    {
        return $property.';CN='.$this->quoted($person['name']).$parameters.':mailto:'.$person['email'];
    }

    /** A parameter value in quotes, with the characters the format reserves removed. */
    private function quoted(string $value): string
    {
        return '"'.str_replace(['"', "\r", "\n"], ['', '', ' '], $value).'"';
    }

    /** Text as the format wants it: backslash, semicolon, comma and line breaks escaped. */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Lines longer than 75 bytes continue on the next line after a space, never inside a character. */
    private function fold(string $line): string
    {
        $parts = [];
        $limit = 75;

        while (strlen($line) > $limit) {
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            // A continuation line starts with a space of its own, so it holds one byte fewer.
            $limit = 74;
        }

        $parts[] = $line;

        return implode("\r\n ", $parts);
    }

    private function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'hr.local';
    }
}
