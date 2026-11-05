<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's in-app notifications: the full list, opening one (which marks it read and
 * goes where it points), and marking everything read. Only ever their own.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';
        $user = $request->user();

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('notifications/index', [
            // Not named `notifications`: that is the header bell's shared prop, and a page prop of the
            // same name would replace it.
            'list' => [
                'data' => $notifications->getCollection()->map(fn (DatabaseNotification $notification): array => self::present($notification))->values(),
                'links' => $notifications->linkCollection(),
                'total' => $notifications->total(),
            ],
            'filter' => $filter,
        ]);
    }

    /**
     * Open one: mark it read and go to what it is about.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $found = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $found->markAsRead();

        $url = (string) ($found->data['url'] ?? '');

        // Only a path inside this application: a stored URL is never trusted to point elsewhere.
        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array{id: string, kind: string, title: string, body: string, read: bool, at: string|null, openUrl: string}
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => (string) ($notification->data['kind'] ?? ''),
            'title' => (string) ($notification->data['title'] ?? ''),
            'body' => (string) ($notification->data['body'] ?? ''),
            'read' => $notification->read_at !== null,
            'at' => $notification->created_at?->toIso8601String(),
            'openUrl' => route('notifications.open', $notification->id, absolute: false),
        ];
    }
}
