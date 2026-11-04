<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Notifications\InAppNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in person's Web Push subscriptions: this browser or phone turning notifications on or off, and
 * a test notification to see that it arrives. Only ever their own; one person can hold several (phone, laptop).
 */
class PushSubscriptionController extends Controller
{
    /**
     * Subscribe this browser. Subscribing again with the same endpoint updates it; an endpoint that another
     * account held (a shared phone) moves to this one.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        $request->user()->updatePushSubscription($request->endpoint(), $request->publicKey(), $request->authToken());

        return response()->noContent();
    }

    /**
     * Unsubscribe this browser.
     */
    public function destroy(DeletePushSubscriptionRequest $request): Response
    {
        $request->user()->deletePushSubscription($request->endpoint());

        return response()->noContent();
    }

    /**
     * Send the person a test notification, so they can check that this device receives them. It is kept in the
     * list like any other.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->pushSubscriptions()->exists()) {
            return response()->json(['message' => 'Perangkat ini belum berlangganan notifikasi.'], 422);
        }

        $user->notify(new InAppNotice(
            'system',
            'Notifikasi uji',
            'Jika Anda membaca ini di layar perangkat, notifikasi sudah berfungsi.',
            route('notifications.index', absolute: false),
        ));

        return response()->json(['message' => 'Notifikasi uji dikirim.']);
    }
}
