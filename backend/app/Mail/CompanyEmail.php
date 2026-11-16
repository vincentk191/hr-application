<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * One email of a company, as plain text, from the company's own address (email automation). It is sent by CompanyMailer from a
 * queued job, so it is not queued itself.
 */
class CompanyEmail extends Mailable
{
    /**
     * @param  list<array{name: string, mime: string, path?: string, data?: string}>  $files  what to attach: a file on the private disk, or its content
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $fromAddress,
        public ?string $fromName = null,
        public ?string $replyToAddress = null,
        public array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName ?? ''),
            replyTo: filled($this->replyToAddress) ? [new Address((string) $this->replyToAddress)] : [],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.company-email');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_values(array_map(function (array $file): Attachment {
            $attachment = isset($file['path'])
                ? Attachment::fromStorageDisk('local', $file['path'])
                : Attachment::fromData(fn (): string => (string) ($file['data'] ?? ''), $file['name']);

            return $attachment->as($file['name'])->withMime($file['mime']);
        }, array_filter($this->files, fn (array $file): bool => isset($file['path']) ? Storage::disk('local')->exists($file['path']) : isset($file['data']))));
    }
}
