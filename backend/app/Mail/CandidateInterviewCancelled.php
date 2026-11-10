<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Told to a candidate whose interview is off because the requisition was cancelled (REC-INT), as a plain-text email.
 * HR chooses to send it when cancelling; nothing is sent otherwise.
 */
class CandidateInterviewCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.candidate-invitation');
    }
}
