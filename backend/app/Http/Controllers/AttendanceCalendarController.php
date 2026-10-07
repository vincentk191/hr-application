<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceDayStatus;
use App\Enums\Permission;
use App\Models\AttendanceEnrolMap;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\WorkflowStep;
use App\Support\Attendance\CaptureStatus;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The attendance calendar and its side panel (ATT-CAL, ATT-SID).
 *
 * Day, week and month show a grid of employees by date; year shows company totals per month. The
 * side panel's figures are computed from exactly the same employees, dates and filters as the
 * grid, so the two cannot disagree (ATT-SID-003).
 *
 * Read through the company scope: it shows the active company, or all of them in the combined view.
 */
class AttendanceCalendarController extends Controller
{
    private const VIEWS = ['day', 'week', 'month', 'year'];

    /**
     * Rows beyond this are cut off and the page says so, rather than building a grid nobody can
     * read.
     */
    private const MAX_ROWS = 500;

    private const UPCOMING_DAYS = 14;

    public function index(Request $request, DailyAttendance $attendance, WorkSchedule $schedule, CaptureStatus $capture): Response
    {
        abort_unless($request->user()->can(Permission::ViewAttendance->value), 403);

        $timezone = (string) config('attendance.device_timezone');
        $view = in_array($request->string('view')->toString(), self::VIEWS, true) ? $request->string('view')->toString() : 'month';

        $dataThrough = $attendance->dataThrough($timezone);
        $anchor = $this->anchor($request, $dataThrough, $timezone);
        [$from, $to] = $this->range($view, $anchor);

        $filters = [
            'departments' => array_values(array_filter(array_map('intval', (array) $request->input('departments', [])))),
            'statuses' => array_values(array_intersect(
                (array) $request->input('statuses', []),
                array_column(AttendanceDayStatus::cases(), 'value'),
            )),
            'search' => trim((string) $request->string('search')),
        ];

        $employees = $this->employees($from, $to, $filters);
        $window = $employees->mapWithKeys(fn (Employee $employee): array => [
            $employee->id => [
                'joined' => $employee->joined_on?->toDateString(),
                'exited' => $employee->exited_on?->toDateString(),
            ],
        ])->all();

        $result = $attendance->forRange($from, $to, $window);
        $withScannerId = AttendanceEnrolMap::acrossCompanies()->pluck('employee_id')->flip();
        $rows = $this->rowsWithStatus($employees, $result['records'], $filters['statuses'], $withScannerId);
        $records = collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['days']])->all();

        return Inertia::render('attendance/calendar', [
            'view' => $view,
            'anchor' => $anchor->toDateString(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'nav' => $this->navigation($view, $anchor, $timezone),
            'dates' => $view === 'year' ? [] : $this->dateHeaders($result['dates'], $schedule),
            'rows' => $view === 'year' ? [] : array_slice($rows, 0, self::MAX_ROWS),
            'truncated' => count($rows) > self::MAX_ROWS,
            'months' => $view === 'year' ? $this->months($records, $result['dates'], $attendance) : [],
            'summary' => $this->summary($attendance->tally($records, $result['dates']), count($rows)),
            'side' => $this->sidePanel($request, $timezone),
            'filters' => $filters,
            'options' => [
                'departments' => Department::orderBy('name')->get(['id', 'name']),
                'statuses' => array_map(fn (AttendanceDayStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'code' => $status->code(),
                ], AttendanceDayStatus::cases()),
            ],
            'dataThrough' => $result['dataThrough'],
            'capture' => $capture->resolve(),
            'schedule' => [
                'start' => $schedule->start,
                'end' => $schedule->end,
                'lateAfter' => $schedule->latestOnTime(),
            ],
            'canManageAttendance' => $request->user()->can('manage-attendance-device'),
        ]);
    }

    /**
     * The date the calendar is centred on: the one asked for, otherwise the last day that has
     * data, otherwise today. Opening on a day with nothing in it would show an empty page for the
     * whole of a manual-capture week.
     */
    private function anchor(Request $request, ?string $dataThrough, string $timezone): CarbonImmutable
    {
        $asked = $request->string('date')->toString();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $asked) === 1 && CarbonImmutable::canBeCreatedFromFormat($asked, 'Y-m-d')) {
            return CarbonImmutable::parse($asked, $timezone)->startOfDay();
        }

        return CarbonImmutable::parse($dataThrough ?? 'today', $timezone)->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $view, CarbonImmutable $anchor): array
    {
        return match ($view) {
            'day' => [$anchor, $anchor],
            'week' => [$anchor->startOfWeek(), $anchor->endOfWeek()->startOfDay()],
            'year' => [$anchor->startOfYear(), $anchor->endOfYear()->startOfDay()],
            default => [$anchor->startOfMonth(), $anchor->endOfMonth()->startOfDay()],
        };
    }

    /**
     * @return array{prev: string, next: string, today: string, label: string}
     */
    private function navigation(string $view, CarbonImmutable $anchor, string $timezone): array
    {
        [$prev, $next, $label] = match ($view) {
            'day' => [$anchor->subDay(), $anchor->addDay(), $this->indonesian($anchor, 'l, j F Y')],
            'week' => [$anchor->subWeek(), $anchor->addWeek(), $this->indonesian($anchor->startOfWeek(), 'j M').' – '.$this->indonesian($anchor->endOfWeek(), 'j M Y')],
            'year' => [$anchor->subYear(), $anchor->addYear(), $anchor->format('Y')],
            default => [$anchor->startOfMonth()->subMonth(), $anchor->startOfMonth()->addMonth(), $this->indonesian($anchor, 'F Y')],
        };

        return [
            'prev' => $prev->toDateString(),
            'next' => $next->toDateString(),
            'today' => CarbonImmutable::now($timezone)->toDateString(),
            'label' => $label,
        ];
    }

    /**
     * Employees to show, who were employed at any point in the range and match the filters.
     *
     * @param  array{departments: list<int>, statuses: list<string>, search: string}  $filters
     * @return Collection<int, Employee>
     */
    private function employees(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        return Employee::query()
            ->with('department:id,name')
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $to->toDateString()))
            ->where(fn (Builder $query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $from->toDateString()))
            ->when($filters['departments'] !== [], fn (Builder $query) => $query->whereIn('department_id', $filters['departments']))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('full_name', 'like', "%{$filters['search']}%")
                    ->orWhere('employee_number', 'like', "%{$filters['search']}%"),
            ))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number', 'department_id', 'joined_on', 'exited_on']);
    }

    /**
     * Shape the grid rows, keeping only employees who have one of the chosen statuses in the
     * range when a status filter is on.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int}>>  $records
     * @param  list<string>  $statuses
     * @param  Collection<int, int>  $withScannerId  employee ids that have any scanner ID mapped
     * @return list<array<string, mixed>>
     */
    private function rowsWithStatus($employees, array $records, array $statuses, $withScannerId): array
    {
        $rows = [];

        foreach ($employees as $employee) {
            $days = $records[$employee->id] ?? [];

            if ($statuses !== [] && ! collect($days)->contains(fn (array $day): bool => in_array($day['status'], $statuses, true))) {
                continue;
            }

            $rows[] = [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'hasScannerId' => $withScannerId->has($employee->id),
                'days' => $days,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $dates
     * @return list<array{date: string, day: int, weekday: string, isWorkday: bool}>
     */
    private function dateHeaders(array $dates, WorkSchedule $schedule): array
    {
        return array_map(function (string $date) use ($schedule): array {
            $day = CarbonImmutable::parse($date);

            return [
                'date' => $date,
                'day' => $day->day,
                'weekday' => $this->indonesian($day, 'D'),
                'isWorkday' => $schedule->isWorkday($day),
            ];
        }, $dates);
    }

    /**
     * Company totals per month, for the year view.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int}>>  $records
     * @param  list<string>  $dates
     * @return list<array<string, mixed>>
     */
    private function months(array $records, array $dates, DailyAttendance $attendance): array
    {
        $byMonth = [];

        foreach ($dates as $date) {
            $byMonth[(int) substr($date, 5, 2)][] = $date;
        }

        $months = [];

        foreach ($byMonth as $month => $monthDates) {
            $months[] = [
                'month' => $month,
                'label' => $this->indonesian(CarbonImmutable::create(2000, $month, 1), 'F'),
                'firstDate' => $monthDates[0],
                'counts' => $attendance->tally($records, $monthDates),
            ];
        }

        return $months;
    }

    /**
     * The headcount summary (ATT-SID-001), from the same records as the grid.
     *
     * @param  array<string, int>  $counts
     * @return array{counts: array<string, int>, attended: int, expected: int, employees: int}
     */
    private function summary(array $counts, int $employees): array
    {
        $attended = $counts['present'] + $counts['late'] + $counts['early_leave'];

        return [
            'counts' => $counts,
            'attended' => $attended,
            'expected' => $attended + $counts['absent'] + $counts['leave'],
            'employees' => $employees,
        ];
    }

    /**
     * Out today, coming up, and what is waiting on this person (ATT-SID-002).
     *
     * @return array{outToday: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>, pendingApprovals: int}
     */
    private function sidePanel(Request $request, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->toDateString();
        $horizon = CarbonImmutable::now($timezone)->addDays(self::UPCOMING_DAYS)->toDateString();

        $approved = fn () => LeaveRequest::query()
            ->with('employee:id,full_name')
            ->whereHas('workflow', fn ($query) => $query->where('current_stage', 'approved'));

        $present = fn (LeaveRequest $leave): array => [
            'id' => $leave->id,
            'employeeId' => $leave->employee_id,
            'name' => $leave->employee?->full_name,
            'type' => $leave->leave_type->label(),
            'from' => $leave->start_on->toDateString(),
            'to' => $leave->end_on->toDateString(),
        ];

        return [
            'outToday' => $approved()
                ->whereDate('start_on', '<=', $today)->whereDate('end_on', '>=', $today)
                ->orderBy('end_on')->limit(10)->get()->map($present)->all(),
            'upcoming' => $approved()
                ->whereDate('start_on', '>', $today)->whereDate('start_on', '<=', $horizon)
                ->orderBy('start_on')->limit(10)->get()->map($present)->all(),
            'pendingApprovals' => WorkflowStep::waitingOn($request->user())->count(),
        ];
    }

    /**
     * A date formatted in Indonesian ("Senin", "Oktober"), whatever the application locale is.
     */
    private function indonesian(CarbonImmutable $date, string $format): string
    {
        return $date->settings(['locale' => 'id'])->translatedFormat($format);
    }
}
