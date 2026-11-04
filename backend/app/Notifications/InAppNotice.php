<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One notification: a title, a line of detail, and where it leads.
 *
 * It is always stored for the in-app list (the database channel, which is the source of truth and is written at
 * once). For someone who has turned push on it is also sent to their phones and browsers (Web Push), through the
 * queue so a push service's round trip never holds up the request that caused it.
 * `kind` groups them for the list (leave, attendance, document); `key`, when given, is what Notifier uses to send
 * the same thing only once.
 */
class InAppNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public ?string $key = null,
    ) {
        // Queue the push only once the surrounding transaction has committed, so a rolled-back event is never announced.
        $this->afterCommit();
    }

    /**
     * The database channel always; Web Push only for someone with a subscription, and only where the keys are set.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->canPush() && method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * The list is written in the request; only the push goes to the queue.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            WebPushChannel::class => (string) config('queue.default'),
        ];
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

    /**
     * What the phone shows. It carries the notice's own path, so tapping it opens the notice (marking it read) and
     * follows it to the page it is about, exactly like choosing it in the bell. The payload is encrypted for the
     * device; it is shown and discarded, never stored by the service worker.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag((string) $this->id)
            ->data(['url' => route('notifications.open', (string) $this->id, absolute: false)]);
    }

    private function canPush(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }
}
