<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Attendance totals for a period, per employee and per department (EMP-ATT-003).
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
     *     departments: list<array<string, mixed>>,
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
                'department' => $employee->department?->name,
                ...$this->figures($counts, $minutes),
            ];
        }

        return [
            'rows' => $rows,
            'departments' => $this->byDepartment($rows),
            'total' => $this->sum($rows),
            'dataThrough' => $result['dataThrough'],
        ];
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
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byDepartment(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) ($row['department'] ?? '')][] = $row;
        }

        ksort($groups);

        $departments = [];

        foreach ($groups as $name => $members) {
            $departments[] = [
                'name' => $name === '' ? null : $name,
                'employees' => count($members),
                ...$this->sum($members),
            ];
        }

        return $departments;
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
