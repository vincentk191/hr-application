<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Models\AttendancePunch;
use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * One employee's month of attendance (EMP-ATT-003): a status and worked time per day, the totals,
 * and every scan behind them — including voided ones, struck through, so a correction can be
 * audited from the same screen that made it.
 *
 * It reuses DailyAttendance for the status of each day, so this view and the calendar can never
 * disagree about the same person on the same day.
 */
class EmployeeTimesheet
{
    public function __construct(private DailyAttendance $attendance) {}

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     prev: string,
     *     next: string,
     *     dataThrough: string|null,
     *     days: list<array<string, mixed>>,
     *     totals: array<string, int>
     * }
     */
    public function forMonth(Employee $employee, CarbonImmutable $month): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();

        $result = $this->attendance->forRange($from, $to, [
            $employee->id => [
                'joined' => $employee->joined_on?->toDateString(),
                'exited' => $employee->exited_on?->toDateString(),
                'company' => $employee->company_id,
                'department' => $employee->department_id,
            ],
        ]);

        $records = $result['records'][$employee->id] ?? [];
        $scans = $this->scansByDate($employee, $from, $to, $timezone);

        $days = [];
        $minutes = 0;

        foreach ($result['dates'] as $date) {
            $record = $records[$date];
            $minutes += $record['minutes'] ?? 0;

            $days[] = [
                'date' => $date,
                'weekday' => CarbonImmutable::parse($date)->settings(['locale' => 'id'])->translatedFormat('D'),
                'status' => $record['status'],
                'event' => $record['event'],
                'in' => $record['in'],
                'out' => $record['out'],
                'minutes' => $record['minutes'],
                'scans' => $scans[$date] ?? [],
            ];
        }

        $counts = $this->attendance->tally([$employee->id => $records], $result['dates']);

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->settings(['locale' => 'id'])->translatedFormat('F Y'),
            'prev' => $month->startOfMonth()->subMonth()->format('Y-m'),
            'next' => $month->startOfMonth()->addMonth()->format('Y-m'),
            'dataThrough' => $result['dataThrough'],
            'days' => $days,
            'totals' => [
                'present' => $counts[AttendanceDayStatus::Present->value],
                'late' => $counts[AttendanceDayStatus::Late->value],
                'early_leave' => $counts[AttendanceDayStatus::EarlyLeave->value],
                'absent' => $counts[AttendanceDayStatus::Absent->value],
                'leave' => $counts[AttendanceDayStatus::Leave->value],
                'minutes' => $minutes,
            ],
        ];
    }

    /**
     * Every scan in the month, by local date, voided ones included.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function scansByDate(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $byDate = [];

        AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('scan_at', [$from->utc(), $to->endOfDay()->utc()])
            ->orderBy('scan_at')
            ->get()
            ->each(function (AttendancePunch $punch) use (&$byDate, $timezone): void {
                $local = $punch->scan_at->setTimezone($timezone);

                $byDate[$local->toDateString()][] = [
                    'id' => $punch->id,
                    'time' => $local->format('H:i'),
                    'source' => $punch->source,
                    'isManual' => $punch->isManual(),
                    'reason' => $punch->reason,
                    'voided' => $punch->voided_at !== null,
                    'voidReason' => $punch->void_reason,
                ];
            });

        return $byDate;
    }
}
