<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Attendance totals for a period, per employee, per Cabang and per department (EMP-ATT-003).
 *
 * Built from the same derived days as the calendar and the profile timesheet, so a total here can
 * always be traced to the days that make it up. Counts are in days (one person on one day); worked
 * time is first scan to last scan, summed over the days that had both.
 */
class AttendanceRecap
{
    public function __construct(private DailyAttendance $attendance, private EmployeeRoster $roster) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     branches: list<array<string, mixed>>,
     *     departments: list<array<string, mixed>>,
     *     periods: list<array<string, mixed>>,
     *     total: array<string, int|float>,
     *     dataThrough: string|null
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to, Collection $employees): array
    {
        $result = $this->attendance->forRange($from, $to, $this->roster->windows($employees));

        $rows = [];

        foreach ($employees as $employee) {
            $days = $result['records'][$employee->id] ?? [];
            $counts = $this->attendance->tally([$employee->id => $days], $result['dates']);
            $minutes = array_sum(array_column($days, 'minutes'));

            $rows[] = [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'number' => $employee->employee_number,
                'branch' => $employee->branch?->name,
                'department' => $employee->department?->name,
                ...$this->figures($counts, $minutes),
            ];
        }

        return [
            'rows' => $rows,
            'branches' => $this->groupedBy($rows, 'branch'),
            'departments' => $this->groupedBy($rows, 'department'),
            'periods' => $this->byPeriod($result['records'], $result['dates']),
            'total' => $this->sum($rows),
            'dataThrough' => $result['dataThrough'],
        ];
    }

    /**
     * Totals per period within the range, for everyone selected: per week for a range of a month or
     * less, per month for a longer one. A period cut off by the range's edge is labelled with the
     * dates it actually covers.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>  $records
     * @param  list<string>  $dates
     * @return list<array<string, mixed>>
     */
    private function byPeriod(array $records, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $weekly = count($dates) <= 31;
        $buckets = [];

        foreach ($dates as $date) {
            $day = CarbonImmutable::parse($date);
            $key = $weekly ? $day->startOfWeek()->toDateString() : $day->format('Y-m');
            $buckets[$key][] = $date;
        }

        $periods = [];

        foreach ($buckets as $bucketDates) {
            $first = CarbonImmutable::parse($bucketDates[0]);
            $last = CarbonImmutable::parse($bucketDates[count($bucketDates) - 1]);
            $counts = $this->attendance->tally($records, $bucketDates);
            $minutes = 0;

            foreach ($records as $days) {
                foreach ($bucketDates as $date) {
                    $minutes += (int) ($days[$date]['minutes'] ?? 0);
                }
            }

            $label = $weekly
                ? $this->indonesian($first, 'j M').' – '.$this->indonesian($last, 'j M')
                : $this->indonesian($first, 'F Y');

            $periods[] = [
                'label' => $label,
                'from' => $first->toDateString(),
                'to' => $last->toDateString(),
                ...$this->figures($counts, $minutes),
            ];
        }

        return $periods;
    }

    private function indonesian(CarbonImmutable $date, string $format): string
    {
        return $date->settings(['locale' => 'id'])->translatedFormat($format);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function figures(array $counts, int $minutes): array
    {
        $attended = $counts[AttendanceDayStatus::Present->value]
            + $counts[AttendanceDayStatus::Late->value]
            + $counts[AttendanceDayStatus::EarlyLeave->value];

        return [
            'present' => $counts[AttendanceDayStatus::Present->value],
            'late' => $counts[AttendanceDayStatus::Late->value],
            'earlyLeave' => $counts[AttendanceDayStatus::EarlyLeave->value],
            'absent' => $counts[AttendanceDayStatus::Absent->value],
            'leave' => $counts[AttendanceDayStatus::Leave->value],
            'noData' => $counts[AttendanceDayStatus::NoData->value],
            'attended' => $attended,
            'minutes' => $minutes,
        ];
    }

    /**
     * Totals per value of a row's field, such as its Cabang or department; people with none are
     * grouped under a null name.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupedBy(array $rows, string $field): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) ($row[$field] ?? '')][] = $row;
        }

        ksort($groups);

        $totals = [];

        foreach ($groups as $name => $members) {
            $totals[] = [
                'name' => $name === '' ? null : $name,
                'employees' => count($members),
                ...$this->sum($members),
            ];
        }

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function sum(array $rows): array
    {
        $keys = ['present', 'late', 'earlyLeave', 'absent', 'leave', 'noData', 'attended', 'minutes'];
        $totals = array_fill_keys($keys, 0);

        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] += (int) $row[$key];
            }
        }

        return $totals;
    }
}
