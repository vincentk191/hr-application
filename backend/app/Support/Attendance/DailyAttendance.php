<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\AttendanceUnmappedPunch;
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
    public function __construct(
        private DayClassifier $classifier,
        private WorkSchedule $schedule,
    ) {}

    /**
     * @param  array<int, array{joined: string|null, exited: string|null}>  $employees  keyed by employee id
     * @return array{
     *     records: array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int}>>,
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
                );

                $records[$employeeId][$date] = [
                    'status' => $status->value,
                    'in' => $punches['first'] ?? null,
                    'out' => ($punches['count'] ?? 0) > 1 ? $punches['last'] : null,
                    'punches' => $punches['count'] ?? 0,
                ];
            }
        }

        return ['records' => $records, 'dataThrough' => $dataThrough, 'dates' => $dates];
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
     * @return array{0: array<int, array<string, array{first: string, last: string, count: int}>>, 1: array<string, true>}
     */
    private function collectPunches(CarbonImmutable $start, CarbonImmutable $end, string $timezone): array
    {
        /** @var array<int, array<string, array{first: string, last: string, count: int}>> $byEmployee */
        $byEmployee = [];
        /** @var array<string, true> $anyScan */
        $anyScan = [];

        AttendancePunch::query()
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

                $byEmployee[$punch->employee_id][$date] = [
                    'first' => $before['first'] ?? $time,
                    'last' => $time,
                    'count' => ($before['count'] ?? 0) + 1,
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
                $from = CarbonImmutable::parse($leave->start_on->toDateString())->max($start);
                $to = CarbonImmutable::parse($leave->end_on->toDateString())->min($end);

                for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                    $days[$leave->employee_id][$day->toDateString()] = true;
                }
            });

        return $days;
    }

    /**
     * Count each status across a set of employees and a set of dates.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int}>>  $records
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
