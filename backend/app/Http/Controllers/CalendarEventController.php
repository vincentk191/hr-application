<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Holidays and company events (ATT-WIZ-001). Listed beside the attendance calendar, added and
 * removed from there.
 */
class CalendarEventController extends Controller
{
    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $repeat = $request->validated('repeat');

        $occurrences = CalendarEvent::occurrences(
            CarbonImmutable::parse($request->validated('starts_on')),
            CarbonImmutable::parse($request->validated('ends_on')),
            $repeat,
            $repeat === null ? null : CarbonImmutable::parse($request->validated('repeat_until')),
        );
        $series = $repeat === null ? null : (string) Str::uuid();

        // One ordinary row per occurrence, made together: either the whole series exists or none of it.
        $events = DB::transaction(fn (): array => array_map(
            fn (array $days): CalendarEvent => CalendarEvent::create([
                ...$request->safe()->only(['title', 'kind', 'company_id', 'department_id', 'branch_id']),
                'starts_on' => $days[0]->toDateString(),
                'ends_on' => $days[1]->toDateString(),
                'series_id' => $series,
                'repeat' => $repeat,
                'created_by_user_id' => $request->user()->id,
            ]),
            $occurrences,
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($events) > 1
                ? "\"{$events[0]->title}\" ditambahkan, berulang {$this->times(count($events))}."
                : "\"{$events[0]->title}\" ditambahkan.",
        ]);

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

        // "Hapus seri" takes every occurrence made together; otherwise only this one goes.
        if ($request->boolean('series') && $calendarEvent->series_id !== null) {
            $count = CalendarEvent::query()->where('series_id', $calendarEvent->series_id)->delete();

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$this->times($count)} dihapus."]);

            return back();
        }

        $calendarEvent->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dihapus.']);

        return back();
    }

    private function times(int $count): string
    {
        return "{$count} kali";
    }
}
