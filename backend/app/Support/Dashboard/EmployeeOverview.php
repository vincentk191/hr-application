<?php

namespace App\Support\Dashboard;

use App\Enums\AbsenceKind;
use App\Enums\AttendanceDayStatus;
use App\Enums\LeaveRequestStage;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Models\WorkflowStep;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\WorkingDays;
use App\Support\Attendance\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * What the dashboard shows one employee about themselves: today's attendance, the requests still being decided,
 * this month's attendance rate and the leave they have taken this year.
 *
 * All of it is derived from the same sources as the pages it links to (the attendance calendar, the leave
 * queue), so the dashboard cannot disagree with them.
 */
class EmployeeOverview
{
    /** How many open requests the dashboard lists before pointing at the full list. */
    public const OPEN_REQUEST_LIMIT = 5;

    /** The request type the Cuti shortcut opens the form on. */
    public const ANNUAL_TYPE_CODE = 'annual';

    /**
     * What the badge says for each status of today, and the tone the page paints it in.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const STATUS_BADGES = [
        'present' => ['Tepat waktu', 'good'],
        'late' => ['Terlambat', 'warn'],
        'early_leave' => ['Pulang cepat', 'warn'],
        'absent' => ['Belum absen', 'bad'],
        'leave' => ['Cuti', 'info'],
        'off' => ['Libur', 'muted'],
        'no_data' => ['Belum ada data', 'muted'],
    ];

    public function __construct(private DailyAttendance $attendance, private WorkingDays $workingDays) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Employee $employee, ?CarbonImmutable $now = null, bool $withAttendance = true): array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone());

        return [
            'attendance' => $withAttendance ? $this->today($employee, $now) : null,
            // No daily attendance, so no rate, for a Cabang that sends a rekapitulasi.
            'month' => $withAttendance && ! $employee->attendsViaRekap() ? $this->month($employee, $now) : null,
            'leaveTaken' => ['days' => $this->leaveTakenThisYear($employee, $now), 'year' => $now->year],
            'sickDays' => $this->sickDays($employee, $now),
            'openRequests' => $this->openRequests($employee),
            'quickActions' => $this->quickActions(),
        ];
    }

    /**
     * The greeting at the top of the dashboard, for anyone signed in, with or without an employee record: their
     * first name, the day, and the scanner's time of day for the clock.
     *
     * @return array{name: string, greeting: string, dateLabel: string, year: int, nowMinutes: int}
     */
    public function intro(string $fullName, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone());

        return [
            'name' => Str::before(trim($fullName), ' '),
            'greeting' => $this->greeting($now),
            'dateLabel' => $now->locale('id')->translatedFormat('l, j F'),
            'year' => $now->year,
            'nowMinutes' => $now->hour * 60 + $now->minute,
        ];
    }

    private function timezone(): string
    {
        return (string) config('attendance.device_timezone');
    }

    /**
     * "Selamat pagi" until 11:00, siang until 15:00, sore until 18:00, malam after.
     */
    private function greeting(CarbonImmutable $now): string
    {
        return 'Selamat '.match (true) {
            $now->hour < 11 => 'pagi',
            $now->hour < 15 => 'siang',
            $now->hour < 18 => 'sore',
            default => 'malam',
        };
    }

    /**
     * Today's punches and where the working day stands. The page ticks the clock itself from `nowMinutes`, so the
     * duration and the bar move without reloading.
     *
     * @return array<string, mixed>
     */
    private function today(Employee $employee, CarbonImmutable $now): array
    {
        $schedule = WorkSchedule::current();

        // A Cabang that sends a rekapitulasi has no scanner: there is no first or last scan to show.
        if ($employee->attendsViaRekap()) {
            return [
                'viaRekap' => true,
                'status' => 'rekap',
                'statusLabel' => 'Rekapitulasi',
                'tone' => 'muted',
                'in' => null,
                'out' => null,
                'minutes' => null,
                'workStart' => $schedule->start,
                'workEnd' => $schedule->endFor($now),
                'isWorkday' => $schedule->isWorkday($now),
                'nowMinutes' => $now->hour * 60 + $now->minute,
            ];
        }

        $result = $this->attendance->forRange($now, $now, [$employee->id => $this->employment($employee)], $now);
        $record = $result['records'][$employee->id][$now->toDateString()];
        [$label, $tone] = self::STATUS_BADGES[$record['status']];

        return [
            'viaRekap' => false,
            'status' => $record['status'],
            'statusLabel' => $label,
            'tone' => $tone,
            'in' => $record['in'],
            'out' => $record['out'],
            'minutes' => $record['minutes'],
            'workStart' => $schedule->start,
            'workEnd' => $schedule->endFor($now),
            'isWorkday' => $schedule->isWorkday($now),
            'nowMinutes' => $now->hour * 60 + $now->minute,
        ];
    }

    /**
     * The attendance rate so far this month, as on the calendar: attended over attended plus absent, so leave counts
     * neither for nor against, and a month with neither has no rate.
     *
     * @return array{label: string, rate: float|null, attended: int, expected: int}
     */
    private function month(Employee $employee, CarbonImmutable $now): array
    {
        $result = $this->attendance->forRange($now->startOfMonth(), $now->startOfDay(), [$employee->id => $this->employment($employee)], $now);
        $counts = $this->attendance->tally([$employee->id => $result['records'][$employee->id] ?? []], $result['dates']);

        $attended = $counts[AttendanceDayStatus::Present->value] + $counts[AttendanceDayStatus::Late->value] + $counts[AttendanceDayStatus::EarlyLeave->value];
        $expected = $attended + $counts[AttendanceDayStatus::Absent->value];

        return [
            'label' => $now->locale('id')->translatedFormat('F'),
            'rate' => $expected === 0 ? null : round($attended / $expected * 100),
            'attended' => $attended,
            'expected' => $expected,
        ];
    }

    /**
     * @return array{joined: string|null, exited: string|null, company: int|null, department: int|null, branch: int|null}
     */
    private function employment(Employee $employee): array
    {
        return [
            'joined' => $employee->joined_on?->toDateString(),
            'exited' => $employee->exited_on?->toDateString(),
            'company' => $employee->company_id,
            'department' => $employee->department_id,
            'branch' => $employee->branch_id,
        ];
    }

    /**
     * The working days of approved leave that start this year, of every type: the same count as "leave taken this year"
     * on the employee's profile. No entitlement is modelled anywhere, so there is no maximum and no balance to show.
     */
    private function leaveTakenThisYear(Employee $employee, CarbonImmutable $now): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereYear('start_on', $now->year)
            ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', LeaveRequestStage::Approved->value))
            ->sum('days');
    }

    /**
     * The days of confirmed sick leave, for the calendar: every working day covered by an approved request of a type
     * that counts as sakit, this year and last (the calendar can step back into last year). A weekend or holiday in
     * the middle of a range is not a sick day, so it is left out, as it is from the request's own day count.
     *
     * @return array{dates: list<string>, year: int, thisYear: int}
     */
    private function sickDays(Employee $employee, CarbonImmutable $now): array
    {
        $from = $now->subYear()->startOfYear();
        $to = $now->endOfYear();
        $dates = [];

        LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereHas('type', fn (Builder $query) => $query->where('absence_kind', AbsenceKind::Sakit->value))
            ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', LeaveRequestStage::Approved->value))
            ->whereDate('start_on', '<=', $to->toDateString())
            ->whereDate('end_on', '>=', $from->toDateString())
            ->get(['id', 'employee_id', 'leave_type', 'start_on', 'end_on'])
            ->each(function (LeaveRequest $leave) use (&$dates, $employee, $from, $to): void {
                $first = CarbonImmutable::parse($leave->start_on->toDateString())->max($from);
                $last = CarbonImmutable::parse($leave->end_on->toDateString())->min($to);

                for ($day = $first; $day->lte($last); $day = $day->addDay()) {
                    if ($this->workingDays->isWorkingDay($day, $employee->company_id, $employee->department_id, $employee->branch_id)) {
                        $dates[$day->toDateString()] = true;
                    }
                }
            });

        $dates = array_keys($dates);
        sort($dates);

        return [
            'dates' => $dates,
            'year' => $now->year,
            'thisYear' => count(array_filter($dates, fn (string $date): bool => str_starts_with($date, (string) $now->year))),
        ];
    }

    /**
     * The employee's own requests that are still waiting for a decision, newest first, each with the steps it goes
     * through: "Diajukan" (always done), then every approval step, done, open or still to come.
     *
     * @return list<array<string, mixed>>
     */
    private function openRequests(Employee $employee): array
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', LeaveRequestStage::Submitted->value))
            ->with(['workflow.steps', 'type'])
            ->latest('id')
            ->limit(self::OPEN_REQUEST_LIMIT)
            ->get()
            ->map(fn (LeaveRequest $leave): array => $this->presentRequest($leave))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRequest(LeaveRequest $leave): array
    {
        $steps = $leave->workflow->steps->sortBy('position')->values();
        $open = $steps->first(fn (WorkflowStep $step): bool => $step->status->value === 'pending');

        return [
            'id' => $leave->id,
            'reference' => $leave->reference(),
            'type' => $leave->typeLabel(),
            'dates' => $this->dateRange($leave),
            'size' => $this->size($leave),
            'submittedOn' => $leave->created_at?->locale('id')->translatedFormat('d M'),
            'waitingOn' => $open === null ? null : $this->stepName($open),
            'steps' => [
                ['label' => 'Diajukan', 'state' => 'done'],
                ...$steps->map(fn (WorkflowStep $step): array => [
                    'label' => $this->stepName($step),
                    'state' => match ($step->status->value) {
                        'approved' => 'done',
                        'pending' => 'open',
                        default => 'todo',
                    },
                ])->all(),
            ],
        ];
    }

    /**
     * "Atasan" for a step an ordinary approver decides, "HR" for one HR does.
     */
    private function stepName(WorkflowStep $step): string
    {
        return $step->approver_role === Role::HrAdmin->value ? 'HR' : 'Atasan';
    }

    /**
     * "03–07 Nov" within a month, "30 Okt – 02 Nov" across two, "19 Okt" for one day.
     */
    private function dateRange(LeaveRequest $leave): string
    {
        $start = CarbonImmutable::instance($leave->start_on)->locale('id');
        $end = CarbonImmutable::instance($leave->end_on)->locale('id');

        return match (true) {
            $start->isSameDay($end) => $start->translatedFormat('d M'),
            $start->isSameMonth($end) => $start->format('d').'–'.$end->translatedFormat('d M'),
            default => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M'),
        };
    }

    /**
     * How big the request is: hours when it has a time of day ("4 jam"), otherwise working days ("5 hari").
     */
    private function size(LeaveRequest $leave): string
    {
        if ($leave->start_time !== null && $leave->end_time !== null) {
            $hours = max(0, (strtotime($leave->end_time) - strtotime($leave->start_time)) / 3600);

            return rtrim(rtrim(number_format($hours, 1, ',', ''), '0'), ',').' jam';
        }

        return $leave->days.' hari';
    }

    /**
     * The four shortcuts to the new-request form, each opening it on a type when this installation has one that
     * fits: annual leave for Cuti, the first kind of Izin, anything called Lembur. A shortcut with no matching type
     * opens the form with none chosen.
     *
     * @return list<array{key: string, label: string, hint: string, type: string|null}>
     */
    private function quickActions(): array
    {
        $types = RequestType::query()->where('is_active', true)->ordered()->get();

        return [
            [
                'key' => 'cuti',
                'label' => 'Cuti',
                'hint' => 'Tahunan, duka, dan lainnya',
                'type' => $types->firstWhere('code', self::ANNUAL_TYPE_CODE)?->code,
            ],
            [
                'key' => 'izin',
                'label' => 'Izin',
                'hint' => 'Sakit, keperluan pribadi',
                'type' => $types->first(fn (RequestType $type): bool => $type->absence_kind === AbsenceKind::Izin)?->code,
            ],
            [
                'key' => 'lembur',
                'label' => 'Lembur',
                'hint' => 'Jam di luar jadwal',
                'type' => $types->first(fn (RequestType $type): bool => Str::contains(Str::lower($type->label), 'lembur'))?->code,
            ],
            ['key' => 'lainnya', 'label' => 'Lainnya', 'hint' => 'Koreksi absen, dinas', 'type' => null],
        ];
    }
}
