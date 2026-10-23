<?php

namespace App\Support\Attendance;

use App\Models\AttendancePunch;
use App\Models\AttendanceUnmappedPunch;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * How current the attendance data is, for the people who capture it (ATT-DEV-001).
 *
 * While logs are captured by hand the data is only ever as fresh as the last capture, and anything
 * built on it — the calendar, "who is absent" — must not treat the gap after it as absence. This
 * answers one question: has the log been captured as often as it should have been?
 *
 * The rhythm is a capture at the end of each working day, so on any morning the data should run
 * through the previous working day (Monday morning: through Friday). A holiday or event that
 * covers the whole group is not a working day either, because the scanner is quiet that day; one
 * for a single company or department is not, since the others still scan.
 */
class CaptureStatus
{
    public const NEVER = 'never';

    public const CURRENT = 'current';

    public const LATE = 'late';

    /**
     * @return array{
     *     state: string,
     *     lastScanAt: string|null,
     *     expectedThrough: string,
     *     unmapped: int
     * }
     */
    public function resolve(?CarbonInterface $now = null): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $today = CarbonImmutable::instance($now ?? now())->setTimezone($timezone)->startOfDay();
        $expectedThrough = $this->previousWorkingDay($today);

        $last = AttendancePunch::acrossCompanies()->max('scan_at');
        $lastScanAt = $last === null ? null : CarbonImmutable::parse($last, 'UTC')->setTimezone($timezone);

        // Unmapped punches count too: a log that was captured but not yet mapped is still data
        // that arrived, and "never" would mislead.
        $lastUnmapped = AttendanceUnmappedPunch::query()->max('scan_at');

        if ($lastUnmapped !== null) {
            $unmappedAt = CarbonImmutable::parse($lastUnmapped, 'UTC')->setTimezone($timezone);
            $lastScanAt = $lastScanAt === null || $unmappedAt->gt($lastScanAt) ? $unmappedAt : $lastScanAt;
        }

        $state = match (true) {
            $lastScanAt === null => self::NEVER,
            $lastScanAt->startOfDay()->gte($expectedThrough) => self::CURRENT,
            default => self::LATE,
        };

        return [
            'state' => $state,
            'lastScanAt' => $lastScanAt?->toIso8601String(),
            'expectedThrough' => $expectedThrough->toDateString(),
            'unmapped' => AttendanceUnmappedPunch::query()->count(),
        ];
    }

    /**
     * The last Monday-to-Friday day strictly before the given one that is not a group-wide holiday
     * or event (one narrowed to a company, department or Cabang leaves the others scanning). The look-back is bounded: no event runs longer than CalendarEvent::MAX_DAYS, and a
     * run of back-to-back ones cannot send this around forever.
     */
    private function previousWorkingDay(CarbonImmutable $day): CarbonImmutable
    {
        $closures = CalendarEvent::query()
            ->whereNull('company_id')
            ->whereNull('department_id')
            ->whereNull('branch_id')
            ->whereDate('ends_on', '<', $day->toDateString())
            ->whereDate('ends_on', '>=', $day->subDays(120)->toDateString())
            ->get(['starts_on', 'ends_on']);

        $previous = $day->subDay();

        for ($guard = 0; $guard < 120 && $this->isClosed($previous, $closures); $guard++) {
            $previous = $previous->subDay();
        }

        return $previous;
    }

    /**
     * @param  Collection<int, CalendarEvent>  $closures
     */
    private function isClosed(CarbonImmutable $day, Collection $closures): bool
    {
        $date = $day->toDateString();

        return $day->isWeekend() || $closures->contains(
            fn (CalendarEvent $event): bool => $event->starts_on->toDateString() <= $date && $event->ends_on->toDateString() >= $date,
        );
    }
}
