<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One in-app notification: a title, a line of detail, and where it leads.
 *
 * In-app only, by decision: the channel is the database and nothing is sent by email or push.
 * `kind` groups them for the list (leave, attendance, document); `key`, when given, is what
 * Notifier uses to send the same thing only once.
 */
class InAppNotice extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public ?string $key = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'key' => $this->key,
        ];
    }
}
