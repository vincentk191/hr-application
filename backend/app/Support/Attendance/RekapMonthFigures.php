<?php

namespace App\Support\Attendance;

use App\Enums\RekapMatch;
use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the confirmed rekapitulasi say about each person, placed on the calendar's months (month and year views).
 *
 * A Cabang's rekapitulasi runs over its own cut-off dates (21 Sep - 20 Oct), not a calendar month, and its
 * counts cannot be cut. So a file belongs to the month it ends in: the October file is the one that ends on
 * 20 October. A file longer than {@link MAX_PERIOD_DAYS} (a whole year, say) is not one month's, and is left out.
 *
 * Alpa, Izin, Sakit and Telat are the Cabang's own counts. The working days are those of the file's own period,
 * so the attendance rate follows the scanner's definition: those present over those present or absent, with Izin
 * and Sakit neither for nor against.
 */
class RekapMonthFigures
{
    /** Longest period that still counts as one month's. */
    public const MAX_PERIOD_DAYS = 35;

    public function __construct(private WorkingDays $workingDays) {}

    /**
     * Per month ("YYYY-MM"), per employee: the figures of the files that end in it. Only the people of a Cabang
     * that sends a rekapitulasi are looked at; the others in `$employees` are skipped.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<string, array<int, array{from: string, to: string, workdays: int, alpa: int, izin: int, sakit: int, telat: int, leave: int, expected: int, attended: int}>>
     */
    public function between(CarbonInterface $from, CarbonInterface $to, Collection $employees): array
    {
        $people = $employees->filter(fn (Employee $employee): bool => $employee->attendsViaRekap())->keyBy('id');

        if ($people->isEmpty()) {
            return [];
        }

        /** @var array<string, int> $workdaysOf */
        $workdaysOf = [];
        $months = [];

        AttendanceRekapRow::query()
            ->with('rekap:id,period_start,period_end')
            ->whereIn('employee_id', $people->keys()->all())
            ->where('match_status', RekapMatch::Matched->value)
            ->whereHas('rekap', fn (Builder $rekap) => $rekap
                ->where('status', AttendanceRekap::STATUS_CONFIRMED)
                ->whereDate('period_end', '>=', $from->toDateString())
                ->whereDate('period_end', '<=', $to->toDateString()))
            ->get()
            ->each(function (AttendanceRekapRow $row) use (&$months, &$workdaysOf, $people): void {
                $start = $row->rekap->period_start;
                $end = $row->rekap->period_end;

                if ($row->employee_id === null || $start->diffInDays($end) + 1 > self::MAX_PERIOD_DAYS) {
                    return;
                }

                $employee = $people[$row->employee_id];
                $key = $employee->company_id.'|'.$employee->department_id.'|'.$employee->branch_id.'|'.$start->toDateString().'|'.$end->toDateString();
                $workdaysOf[$key] ??= $this->workingDays->between($start, $end, $employee->company_id, $employee->department_id, $employee->branch_id);

                $before = $months[$end->format('Y-m')][$row->employee_id] ?? null;

                $months[$end->format('Y-m')][$row->employee_id] = $this->figures(
                    $before,
                    $start->toDateString(),
                    $end->toDateString(),
                    $workdaysOf[$key],
                    $row,
                );
            });

        return $months;
    }

    /**
     * The person's figures for the month, with one more file added when they have two ending in it.
     *
     * @param  array{from: string, to: string, workdays: int, alpa: int, izin: int, sakit: int, telat: int, leave: int, expected: int, attended: int}|null  $before
     * @return array{from: string, to: string, workdays: int, alpa: int, izin: int, sakit: int, telat: int, leave: int, expected: int, attended: int}
     */
    private function figures(?array $before, string $from, string $to, int $workdays, AttendanceRekapRow $row): array
    {
        $alpa = ($before['alpa'] ?? 0) + $row->alpa;
        $izin = ($before['izin'] ?? 0) + $row->izin;
        $sakit = ($before['sakit'] ?? 0) + $row->sakit;
        $workdays += $before['workdays'] ?? 0;
        $expected = max(0, $workdays - $izin - $sakit);

        return [
            'from' => $before === null ? $from : min($before['from'], $from),
            'to' => $before === null ? $to : max($before['to'], $to),
            'workdays' => $workdays,
            'alpa' => $alpa,
            'izin' => $izin,
            'sakit' => $sakit,
            'telat' => ($before['telat'] ?? 0) + $row->telat,
            'leave' => $izin + $sakit,
            'expected' => $expected,
            'attended' => max(0, $expected - $alpa),
        ];
    }
}
