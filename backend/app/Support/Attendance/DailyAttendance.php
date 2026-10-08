<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\AttendanceUnmappedPunch;
use App\Models\CalendarEvent;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns raw punches into one record per employee per day, for the calendar and its side panel.
 *
 * Punches stay raw and append-only; this derives the day from them every time, so a corrected
 * mapping or a late upload simply changes what the derivation sees. Nothing here is stored.
 *
 * A day is the scanner's local day, not the UTC one — a 23:50 scan belongs to that evening.
 */
class DailyAttendance
{
    /**
     * Two scans this close together are one finger pressed twice, not two arrivals.
     */
    public const DUPLICATE_WITHIN_SECONDS = 120;

    public function __construct(
        private DayClassifier $classifier,
        private WorkSchedule $schedule,
    ) {}

    /**
     * @param  array<int, array{joined: string|null, exited: string|null, company: int|null, department: int|null}>  $employees  keyed by employee id
     * @return array{
     *     records: array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>,
     *     dataThrough: string|null,
     *     dates: list<string>
     * }
     */
    public function forRange(CarbonInterface $from, CarbonInterface $to, array $employees, ?CarbonInterface $now = null): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $start = CarbonImmutable::instance($from)->setTimezone($timezone)->startOfDay();
        $end = CarbonImmutable::instance($to)->setTimezone($timezone)->startOfDay();
        $now = CarbonImmutable::instance($now ?? now())->setTimezone($timezone);

        [$byEmployee, $datesWithAnyScan] = $this->collectPunches($start, $end, $timezone);
        $leaveDays = $this->leaveDays($start, $end);
        $scannerIds = $this->scannerIds();
        $eventsByDate = $this->eventsByDate($start, $end);
        $dataThrough = $this->dataThrough($timezone);

        $records = [];
        $dates = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $dates[] = $date;
            $dataCoversDay = $dataThrough !== null && $date <= $dataThrough;
            $stillWorking = $date === $now->toDateString() && $now->format('H:i') < $this->schedule->end;

            foreach ($employees as $employeeId => $employment) {
                $punches = $byEmployee[$employeeId][$date] ?? null;
                $event = $this->eventFor($eventsByDate[$date] ?? [], $employment['company'], $employment['department']);
                $employed = ($employment['joined'] === null || $date >= $employment['joined'])
                    && ($employment['exited'] === null || $date <= $employment['exited']);

                // Before someone joined or after they left there is no working day to judge; a punch
                // on such a day still counts, because it happened.
                $status = ! $employed && $punches === null ? AttendanceDayStatus::Off : $this->classifier->classify(
                    $this->schedule,
                    $this->schedule->isWorkday($day),
                    $dataCoversDay,
                    isset($datesWithAnyScan[$date]),
                    isset($leaveDays[$employeeId][$date]),
                    $punches['first'] ?? null,
                    $punches['last'] ?? null,
                    $punches['count'] ?? 0,
                    $stillWorking,
                    $this->hasScannerId($scannerIds[$employeeId] ?? [], $date),
                    $event !== null,
                );

                $records[$employeeId][$date] = [
                    'status' => $status->value,
                    'in' => $punches['first'] ?? null,
                    'out' => ($punches['count'] ?? 0) > 1 ? $punches['last'] : null,
                    'punches' => $punches['count'] ?? 0,
                    'minutes' => ($punches['count'] ?? 0) > 1 ? $this->minutesBetween($punches['first'], $punches['last']) : null,
                    'duplicate' => ($punches['minGap'] ?? null) !== null && $punches['minGap'] <= self::DUPLICATE_WITHIN_SECONDS,
                    'event' => $event,
                ];
            }
        }

        return ['records' => $records, 'dataThrough' => $dataThrough, 'dates' => $dates];
    }

    /**
     * Holidays and events in the range, expanded to the dates they cover.
     *
     * @return array<string, list<CalendarEvent>>
     */
    private function eventsByDate(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $byDate = [];

        CalendarEvent::query()
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get()
            ->each(function (CalendarEvent $event) use (&$byDate, $start, $end): void {
                // Parsed in the range's own timezone: mixing UTC dates with local bounds drops one-day ranges.
                $from = CarbonImmutable::parse($event->starts_on->toDateString(), $start->getTimezone())->max($start);
                $to = CarbonImmutable::parse($event->ends_on->toDateString(), $start->getTimezone())->min($end);

                for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                    $byDate[$day->toDateString()][] = $event;
                }
            });

        return $byDate;
    }

    /**
     * The title of the first event that covers someone, if any does.
     *
     * @param  list<CalendarEvent>  $events
     */
    private function eventFor(array $events, ?int $companyId, ?int $departmentId): ?string
    {
        foreach ($events as $event) {
            if ($event->appliesTo($companyId, $departmentId)) {
                return $event->title;
            }
        }

        return null;
    }

    /**
     * Whole minutes between two local "HH:MM" times of the same day.
     */
    private function minutesBetween(string $from, string $to): int
    {
        [$fromHours, $fromMinutes] = array_map('intval', explode(':', $from));
        [$toHours, $toMinutes] = array_map('intval', explode(':', $to));

        return max(0, ($toHours * 60 + $toMinutes) - ($fromHours * 60 + $fromMinutes));
    }

    /**
     * Every scanner ID mapping, as the date windows each employee is covered for.
     *
     * @return array<int, list<array{from: string|null, to: string|null}>>
     */
    private function scannerIds(): array
    {
        $windows = [];

        foreach (AttendanceEnrolMap::acrossCompanies()->get(['employee_id', 'valid_from', 'valid_to']) as $map) {
            $windows[$map->employee_id][] = [
                'from' => $map->valid_from?->toDateString(),
                'to' => $map->valid_to?->toDateString(),
            ];
        }

        return $windows;
    }

    /**
     * @param  list<array{from: string|null, to: string|null}>  $windows
     */
    private function hasScannerId(array $windows, string $date): bool
    {
        foreach ($windows as $window) {
            if (($window['from'] === null || $window['from'] <= $date) && ($window['to'] === null || $window['to'] >= $date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The local date the log runs through, counting waiting (unmapped) punches too: they are data
     * that arrived, so the days they cover are not "no data".
     */
    public function dataThrough(?string $timezone = null): ?string
    {
        $timezone ??= (string) config('attendance.device_timezone');

        $latest = collect([
            AttendancePunch::acrossCompanies()->max('scan_at'),
            AttendanceUnmappedPunch::query()->max('scan_at'),
        ])->filter()->map(fn (string $at): CarbonImmutable => CarbonImmutable::parse($at, 'UTC'))->max();

        return $latest?->setTimezone($timezone)->toDateString();
    }

    /**
     * Earliest and latest punch time per employee per local day, plus every date anyone scanned on.
     *
     * Read through the company scope, so the calendar only ever sees the active company.
     *
     * @return array{0: array<int, array<string, array{first: string, last: string, count: int, lastTs: int, minGap: int|null}>>, 1: array<string, true>}
     */
    private function collectPunches(CarbonImmutable $start, CarbonImmutable $end, string $timezone): array
    {
        /** @var array<int, array<string, array{first: string, last: string, count: int, lastTs: int, minGap: int|null}>> $byEmployee */
        $byEmployee = [];
        /** @var array<string, true> $anyScan */
        $anyScan = [];

        AttendancePunch::query()
            ->counted()
            ->whereBetween('scan_at', [$start->utc(), $end->endOfDay()->utc()])
            ->orderBy('scan_at')
            ->select(['employee_id', 'scan_at'])
            ->cursor()
            ->each(function (AttendancePunch $punch) use (&$byEmployee, &$anyScan, $timezone): void {
                $local = $punch->scan_at->setTimezone($timezone);
                $date = $local->toDateString();
                $time = $local->format('H:i');

                $anyScan[$date] = true;

                $before = $byEmployee[$punch->employee_id][$date] ?? null;

                $timestamp = $local->getTimestamp();
                $gap = $before === null ? null : $timestamp - $before['lastTs'];

                $byEmployee[$punch->employee_id][$date] = [
                    'first' => $before['first'] ?? $time,
                    'last' => $time,
                    'count' => ($before['count'] ?? 0) + 1,
                    'lastTs' => $timestamp,
                    'minGap' => $gap === null ? null : min($gap, $before['minGap'] ?? $gap),
                ];
            });

        return [$byEmployee, $anyScan];
    }

    /**
     * Approved leave, expanded to the dates it covers within the range.
     *
     * @return array<int, array<string, true>>
     */
    private function leaveDays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];

        LeaveRequest::query()
            ->whereHas('workflow', fn ($query) => $query->where('current_stage', 'approved'))
            ->whereDate('start_on', '<=', $end->toDateString())
            ->whereDate('end_on', '>=', $start->toDateString())
            ->get(['employee_id', 'start_on', 'end_on'])
            ->each(function (LeaveRequest $leave) use (&$days, $start, $end): void {
                $from = CarbonImmutable::parse($leave->start_on->toDateString(), $start->getTimezone())->max($start);
                $to = CarbonImmutable::parse($leave->end_on->toDateString(), $start->getTimezone())->min($end);

                for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                    $days[$leave->employee_id][$day->toDateString()] = true;
                }
            });

        return $days;
    }

    /**
     * Count each status across a set of employees and a set of dates.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>  $records
     * @param  list<string>  $dates
     * @return array<string, int>
     */
    public function tally(array $records, array $dates): array
    {
        /** @var array<string, int> $counts */
        $counts = array_fill_keys(array_map(fn (AttendanceDayStatus $status): string => $status->value, AttendanceDayStatus::cases()), 0);

        foreach ($records as $byDate) {
            foreach ($dates as $date) {
                if (isset($byDate[$date])) {
                    $counts[$byDate[$date]['status']]++;
                }
            }
        }

        return $counts;
    }
}
