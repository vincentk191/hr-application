<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Enums\AttendanceIssueType;
use App\Models\AttendanceIssueResolution;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Finds attendance exceptions in a date range (ATT-ISS-001..003).
 *
 * Nothing is stored: issues are read off the same derived days the calendar shows, so a
 * correction or an approved leave makes the issue vanish without anyone closing it. The one
 * thing remembered is a human decision to excuse one, which is attached here when found.
 *
 * Only days the log covers can raise anything, because DailyAttendance marks the rest "no data":
 * a day that has not been captured yet is never an absence.
 */
class IssueDetector
{
    public function __construct(
        private DailyAttendance $attendance,
        private EmployeeRoster $roster,
        private WorkSchedule $schedule,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{
     *     key: string, employeeId: int, name: string, number: string, branch: string|null, department: string|null,
     *     date: string, type: string, typeLabel: string, detail: string,
     *     resolution: array{by: string|null, at: string|null, reason: string}|null
     * }>
     */
    public function detect(CarbonInterface $from, CarbonInterface $to, Collection $employees, ?CarbonInterface $now = null): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $now = CarbonImmutable::instance($now ?? now())->setTimezone($timezone);
        $result = $this->attendance->forRange($from, $to, $this->roster->windows($employees), $now);
        $resolutions = $this->resolutions($from, $to);

        $issues = [];

        foreach ($employees as $employee) {
            foreach ($result['records'][$employee->id] ?? [] as $date => $record) {
                foreach ($this->typesFor($record, $date, $now) as [$type, $detail]) {
                    $key = "{$employee->id}|{$date}|{$type->value}";

                    $issues[] = [
                        'key' => $key,
                        'employeeId' => $employee->id,
                        'name' => $employee->full_name,
                        'number' => $employee->employee_number,
                        'branch' => $employee->branch?->name,
                        'department' => $employee->department?->name,
                        'date' => $date,
                        'type' => $type->value,
                        'typeLabel' => $type->label(),
                        'detail' => $detail,
                        'resolution' => $resolutions[$key] ?? null,
                    ];
                }
            }
        }

        usort($issues, fn (array $a, array $b): int => [$b['date'], $a['name']] <=> [$a['date'], $b['name']]);

        return $issues;
    }

    /**
     * What is wrong with one day, if anything.
     *
     * @param  array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}  $record
     * @return list<array{0: AttendanceIssueType, 1: string}>
     */
    private function typesFor(array $record, string $date, CarbonImmutable $now): array
    {
        $found = [];

        // Approved leave wins over a scan, so the day is leave; what is left to say is that someone
        // scanned anyway. Nothing else (late, a lone scan) means anything on a day off.
        if ($record['status'] === AttendanceDayStatus::Leave->value) {
            if ($record['punches'] > 0) {
                $times = $record['out'] === null ? $record['in'] : "{$record['in']}–{$record['out']}";
                $found[] = [AttendanceIssueType::ScanOnLeave, "Ada scan ({$times}) pada hari cuti yang disetujui"];
            }

            return $found;
        }

        if ($record['status'] === AttendanceDayStatus::Absent->value) {
            $found[] = [AttendanceIssueType::UnapprovedAbsence, 'Hari kerja tanpa scan dan tanpa cuti yang disetujui'];
        }

        if ($record['status'] === AttendanceDayStatus::Late->value) {
            $found[] = [AttendanceIssueType::Late, "Masuk {$record['in']}, batas {$this->schedule->latestOnTime()}"];
        }

        if ($record['status'] === AttendanceDayStatus::EarlyLeave->value) {
            $found[] = [AttendanceIssueType::EarlyLeave, "Pulang {$record['out']}, jam pulang {$this->schedule->end}"];
        }

        if ($record['punches'] === 1 && $this->dayIsOver($date, $now)) {
            $found[] = [AttendanceIssueType::MissingCheckout, "Hanya satu scan ({$record['in']}), tidak ada scan pulang"];
        }

        if ($record['duplicate']) {
            $found[] = [AttendanceIssueType::DuplicatePunch, 'Dua scan berjarak kurang dari 2 menit'];
        }

        return $found;
    }

    /**
     * A single scan is only a missing check-out once the person could have left: a past day, or
     * today after the end of the working day.
     */
    private function dayIsOver(string $date, CarbonImmutable $now): bool
    {
        $today = $now->toDateString();

        return $date < $today || ($date === $today && $now->format('H:i') >= $this->schedule->end);
    }

    /**
     * @return array<string, array{by: string|null, at: string|null, reason: string}>
     */
    private function resolutions(CarbonInterface $from, CarbonInterface $to): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $byKey = [];

        AttendanceIssueResolution::query()
            ->with('resolvedBy:id,name')
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->each(function (AttendanceIssueResolution $resolution) use (&$byKey, $timezone): void {
                $byKey["{$resolution->employee_id}|{$resolution->date->toDateString()}|{$resolution->type}"] = [
                    'by' => $resolution->resolvedBy?->name,
                    'at' => $resolution->updated_at?->setTimezone($timezone)->toIso8601String(),
                    'reason' => $resolution->reason,
                ];
            });

        return $byKey;
    }
}
