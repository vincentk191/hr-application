<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Models\CalendarEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Holidays and company events (ATT-WIZ-001). Listed beside the attendance calendar, added and
 * removed from there.
 */
class CalendarEventController extends Controller
{
    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $event = CalendarEvent::create([
            ...$request->safe()->only(['title', 'kind', 'starts_on', 'ends_on', 'company_id', 'department_id']),
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$event->title}\" ditambahkan."]);

        return back();
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can(Permission::ManageCalendarEvents->value), 403);

        // A group-wide event, or one for another company, belongs to someone with a wider reach.
        abort_unless(
            $user->isParentWide() || ($calendarEvent->company_id !== null && $calendarEvent->company_id === $user->company_id),
            403,
        );

        $calendarEvent->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dihapus.']);

        return back();
    }
}
