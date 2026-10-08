<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Reads a from/to date range off a request, for screens that show a span of days.
 *
 * Forgiving where it can be: a missing or malformed date falls back to something sensible rather
 * than failing the page, a reversed range is swapped, and the span is clamped to what the screen
 * can reasonably derive in one go.
 */
class DateRangeInput
{
    /**
     * The default is the month of the last day that has data, ending on that day, so the page
     * opens on something rather than on a week of "no data".
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function fromRequest(Request $request, ?string $dataThrough, string $timezone, int $maxDays): array
    {
        $valid = fn (string $value): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && CarbonImmutable::canBeCreatedFromFormat($value, 'Y-m-d');

        $latest = CarbonImmutable::parse($dataThrough ?? 'today', $timezone)->startOfDay();

        $to = $valid($request->string('to')->toString())
            ? CarbonImmutable::parse($request->string('to')->toString(), $timezone)->startOfDay()
            : $latest;
        $from = $valid($request->string('from')->toString())
            ? CarbonImmutable::parse($request->string('from')->toString(), $timezone)->startOfDay()
            : $to->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > $maxDays - 1) {
            $from = $to->subDays($maxDays - 1);
        }

        return [$from, $to];
    }
}
