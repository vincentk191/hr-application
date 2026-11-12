<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The interview invitation as a plain-text email (REC-INT). The body is the same text HR can copy for WhatsApp, so
 * the two channels never say different things. It carries the interview as a calendar event (`.ics`) to add with one tap.
 */
class CandidateInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $calendar  the `.ics` text of the interview, attached when there is one
     */
    public function __construct(public string $subjectLine, public string $body, public ?string $calendar = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.candidate-invitation');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $calendar = $this->calendar;

        return $calendar === null ? [] : [
            Attachment::fromData(fn (): string => $calendar, 'interview.ics')->withMime('text/calendar; charset=UTF-8; method=PUBLISH'),
        ];
    }
}
