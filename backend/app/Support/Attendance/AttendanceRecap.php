<?php

namespace App\Support\Attendance;

use App\Enums\AbsenceKind;
use App\Enums\AttendanceDayStatus;
use App\Enums\AttendanceSource;
use App\Enums\RekapMatch;
use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Attendance totals for a period, per employee, per Cabang and per department (EMP-ATT-003).
 *
 * Built from the same derived days as the calendar and the profile timesheet, so a total here can
 * always be traced to the days that make it up. Counts are in days (one person on one day); worked
 * time is first scan to last scan, summed over the days that had both.
 *
 * Alpa, Izin, Sakit, Telat and the deduction (potongan) come from one of two places, by the Cabang the
 * employee is filed under. A Cabang with a scanner is counted from the days: Alpa is an absent day, Telat
 * a late one, and approved leave is Izin or Sakit by the kind of request. A Cabang that sends a
 * rekapitulasi is read from its confirmed files that fall wholly inside the range, because a file's counts
 * are for its whole period and cannot be cut; files that only partly overlap are listed, not counted.
 */
class AttendanceRecap
{
    public function __construct(
        private DailyAttendance $attendance,
        private EmployeeRoster $roster,
        private PotonganCalculator $potongan,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     branches: list<array<string, mixed>>,
     *     departments: list<array<string, mixed>>,
     *     periods: list<array<string, mixed>>,
     *     total: array<string, int|float>,
     *     dataThrough: string|null,
     *     rekapNotes: list<array{branch: string|null, from: string, to: string}>
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to, Collection $employees): array
    {
        $result = $this->attendance->forRange($from, $to, $this->roster->windows($employees));
        $rekap = $this->rekapFigures($from, $to, $employees);

        $rows = [];
        $scanners = [];

        foreach ($employees as $employee) {
            $viaRekap = $this->viaRekap($employee);
            $days = $result['records'][$employee->id] ?? [];

            if ($viaRekap) {
                $found = $rekap['figures'][$employee->id] ?? null;
                $counts = array_fill_keys(array_map(fn (AttendanceDayStatus $status): string => $status->value, AttendanceDayStatus::cases()), 0);
                $minutes = 0;
                $split = ['alpa' => $found['alpa'] ?? 0, 'izin' => $found['izin'] ?? 0, 'sakit' => $found['sakit'] ?? 0, 'telat' => $found['telat'] ?? 0, 'otherLeave' => 0];
                $potongan = (int) ($found['potongan'] ?? 0);
            } else {
                $scanners[$employee->id] = $employee;
                $counts = $this->attendance->tally([$employee->id => $days], $result['dates']);
                $minutes = array_sum(array_column($days, 'minutes'));
                $split = $this->splitDays($days, $result['dates']);
                $potongan = $this->deduction($employee, $split);
            }

            $rows[] = [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'number' => $employee->employee_number,
                'branch' => $employee->branch?->name,
                'department' => $employee->department?->name,
                'zona' => $employee->zona->label(),
                'source' => $viaRekap ? AttendanceSource::Rekap->value : AttendanceSource::Scanner->value,
                'rekapMissing' => $viaRekap && ! isset($rekap['figures'][$employee->id]),
                ...$this->figures($counts, $minutes),
                ...$split,
                'potongan' => $potongan,
            ];
        }

        return [
            'rows' => $rows,
            'branches' => $this->groupedBy($rows, 'branch'),
            'departments' => $this->groupedBy($rows, 'department'),
            'periods' => $this->byPeriod(array_intersect_key($result['records'], $scanners), $result['dates'], $scanners),
            'total' => $this->sum($rows),
            'dataThrough' => $result['dataThrough'],
            'rekapNotes' => $rekap['notes'],
        ];
    }

    private function viaRekap(Employee $employee): bool
    {
        return $employee->branch?->attendance_source === AttendanceSource::Rekap;
    }

    /**
     * Alpa, Telat, Izin, Sakit and other leave over some dates, from the derived days.
     *
     * @param  array<string, array<string, mixed>>  $days  one employee's days, keyed by date
     * @param  list<string>  $dates
     * @return array{alpa: int, izin: int, sakit: int, telat: int, otherLeave: int}
     */
    private function splitDays(array $days, array $dates): array
    {
        $split = ['alpa' => 0, 'izin' => 0, 'sakit' => 0, 'telat' => 0, 'otherLeave' => 0];

        foreach ($dates as $date) {
            $day = $days[$date] ?? null;

            if ($day === null) {
                continue;
            }

            match ($day['status']) {
                AttendanceDayStatus::Absent->value => $split['alpa']++,
                AttendanceDayStatus::Late->value => $split['telat']++,
                AttendanceDayStatus::Leave->value => match ($day['leaveKind'] ?? null) {
                    AbsenceKind::Izin->value => $split['izin']++,
                    AbsenceKind::Sakit->value => $split['sakit']++,
                    default => $split['otherLeave']++,
                },
                default => null,
            };
        }

        return $split;
    }

    /**
     * What the employee's counts cost, by their Zona and position.
     *
     * @param  array{alpa: int, izin: int, sakit: int, telat: int, otherLeave: int}  $split
     */
    private function deduction(Employee $employee, array $split): int
    {
        return $this->potongan->forRow($employee->zona, $split['alpa'], $split['izin'], $split['telat'], $employee->position?->name, null);
    }

    /**
     * What the confirmed rekapitulasi say for the employees of Cabang that send one: the sum of the files
     * wholly inside the range, per employee. Also the files that touch the range without being inside it,
     * which are not counted and so are named for the screen to say so.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{figures: array<int, array{alpa: int, izin: int, sakit: int, telat: int, potongan: int}>, notes: list<array{branch: string|null, from: string, to: string}>}
     */
    private function rekapFigures(CarbonInterface $from, CarbonInterface $to, Collection $employees): array
    {
        $viaRekap = $employees->filter(fn (Employee $employee): bool => $this->viaRekap($employee));

        if ($viaRekap->isEmpty()) {
            return ['figures' => [], 'notes' => []];
        }

        $start = $from->toDateString();
        $end = $to->toDateString();
        $figures = [];

        AttendanceRekapRow::query()
            ->whereIn('employee_id', $viaRekap->pluck('id')->all())
            ->where('match_status', RekapMatch::Matched->value)
            ->whereHas('rekap', fn (Builder $rekap) => $rekap
                ->where('status', AttendanceRekap::STATUS_CONFIRMED)
                ->whereDate('period_start', '>=', $start)
                ->whereDate('period_end', '<=', $end))
            ->get()
            ->each(function (AttendanceRekapRow $row) use (&$figures): void {
                $sum = $figures[$row->employee_id] ?? ['alpa' => 0, 'izin' => 0, 'sakit' => 0, 'telat' => 0, 'potongan' => 0];
                $figures[$row->employee_id] = [
                    'alpa' => $sum['alpa'] + $row->alpa,
                    'izin' => $sum['izin'] + $row->izin,
                    'sakit' => $sum['sakit'] + $row->sakit,
                    'telat' => $sum['telat'] + $row->telat,
                    'potongan' => $sum['potongan'] + $row->computed_potongan,
                ];
            });

        $notes = AttendanceRekap::query()
            ->with('branch:id,name')
            ->whereIn('branch_id', $viaRekap->pluck('branch_id')->unique()->all())
            ->where('status', AttendanceRekap::STATUS_CONFIRMED)
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->where(fn (Builder $outside) => $outside->whereDate('period_start', '<', $start)->orWhereDate('period_end', '>', $end))
            ->orderBy('period_start')
            ->get()
            ->map(fn (AttendanceRekap $rekap): array => [
                'branch' => $rekap->branch?->name,
                'from' => $rekap->period_start->toDateString(),
                'to' => $rekap->period_end->toDateString(),
            ])
            ->all();

        return ['figures' => $figures, 'notes' => $notes];
    }

    /**
     * Totals per period within the range, for everyone selected who is counted from a scanner (a
     * rekapitulasi has no days to put in a week): per week for a range of a month or less, per month for a
     * longer one. A period cut off by the range's edge is labelled with the dates it actually covers.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>  $records
     * @param  list<string>  $dates
     * @param  array<int, Employee>  $scanners
     * @return list<array<string, mixed>>
     */
    private function byPeriod(array $records, array $dates, array $scanners): array
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
            $split = ['alpa' => 0, 'izin' => 0, 'sakit' => 0, 'telat' => 0, 'otherLeave' => 0];
            $potongan = 0;

            foreach ($records as $employeeId => $days) {
                foreach ($bucketDates as $date) {
                    $minutes += (int) ($days[$date]['minutes'] ?? 0);
                }

                $mine = $this->splitDays($days, $bucketDates);
                $potongan += $this->deduction($scanners[$employeeId], $mine);

                foreach ($mine as $key => $value) {
                    $split[$key] += $value;
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
                ...$split,
                'potongan' => $potongan,
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
        $keys = [
            'present', 'late', 'earlyLeave', 'absent', 'leave', 'noData', 'attended', 'minutes',
            'alpa', 'izin', 'sakit', 'telat', 'otherLeave', 'potongan',
        ];
        $totals = array_fill_keys($keys, 0);

        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] += (int) $row[$key];
            }
        }

        return $totals;
    }
}
