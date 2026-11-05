<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    /**
     * Show the person's notification settings: turning Web Push on or off for the phone or browser in front of them.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notifications', [
            // The devices this person has turned push on for; the page compares them with the one in front of it,
            // which may still hold the subscription of whoever used it before.
            'pushEndpoints' => $request->user()->pushSubscriptions()->pluck('endpoint')->all(),
        ]);
    }
}
