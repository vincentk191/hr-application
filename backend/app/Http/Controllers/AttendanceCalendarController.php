<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceDayStatus;
use App\Enums\Permission;
use App\Models\AttendanceEnrolMap;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Support\Attendance\CaptureStatus;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\EmployeeRoster;
use App\Support\Attendance\WorkSchedule;
use App\Support\CompanyContext;
use App\Support\CsvDownload;
use App\Support\OrganisationNames;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * The leave list in the side panel is cut off here and says how many more there are: a year
     * can hold more leave than a panel can show.
     */
    private const ON_LEAVE_LIMIT = 50;

    public function index(Request $request, DailyAttendance $attendance, WorkSchedule $schedule, CaptureStatus $capture, EmployeeRoster $roster): Response
    {
        $data = $this->gather($request, $attendance, $roster);

        return Inertia::render('attendance/calendar', [
            'view' => $data['view'],
            'anchor' => $data['anchor']->toDateString(),
            'range' => ['from' => $data['from']->toDateString(), 'to' => $data['to']->toDateString()],
            'nav' => $this->navigation($data['view'], $data['anchor'], $data['timezone']),
            'dates' => $data['view'] === 'year' ? [] : $this->dateHeaders($data['result']['dates'], $schedule),
            'rows' => $data['view'] === 'year' ? [] : array_slice($data['rows'], 0, self::MAX_ROWS),
            'truncated' => count($data['rows']) > self::MAX_ROWS,
            'daily' => in_array($data['view'], ['week', 'month'], true)
                ? $this->daily($data['records'], $data['result']['dates'], $data['result']['dataThrough'], $schedule)
                : [],
            'months' => $data['view'] === 'year' ? $this->months($data['records'], $data['result']['dates'], $data['result']['dataThrough'], $schedule, $attendance) : [],
            'summary' => $this->summary($attendance->tally($data['records'], $data['result']['dates']), count($data['rows'])),
            'onLeave' => $this->onLeave($data['rows'], $data['from'], $data['to'], $data['filters']['leaveType']),
            'events' => $this->events($request, $data['from'], $data['to']),
            'eventForm' => $this->eventForm($request),
            'filters' => $data['filters'],
            'options' => [
                'branches' => OrganisationNames::branches($request->user()),
                'departments' => OrganisationNames::departments($request->user()),
                'teams' => $roster->teamLeads($request->user()),
                'leaveTypes' => RequestType::query()->ordered()->get()
                    ->map(fn (RequestType $type): array => ['value' => $type->code, 'label' => $type->label])
                    ->values()
                    ->all(),
                'statuses' => array_map(fn (AttendanceDayStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'code' => $status->code(),
                ], AttendanceDayStatus::cases()),
            ],
            'dataThrough' => $data['result']['dataThrough'],
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
     * The view on screen, as a CSV (ATT-CAL-005). The same range and filters, run through the same
     * derivation as the page, so the file is exactly what was being looked at. PDF is not offered:
     * the browser's own "print to PDF" covers it without another dependency.
     */
    public function export(Request $request, DailyAttendance $attendance, EmployeeRoster $roster, WorkSchedule $schedule): StreamedResponse
    {
        $data = $this->gather($request, $attendance, $roster);
        $label = $this->navigation($data['view'], $data['anchor'], $data['timezone'])['label'];

        /** @var list<array<int, string|int|float|null>> $lines */
        $lines = [
            ['Kalender', $label, 'Data sampai '.($data['result']['dataThrough'] ?? '-')],
            [],
        ];

        if ($data['view'] === 'year') {
            $lines[] = ['Bulan', 'Hadir', 'Terlambat', 'Pulang cepat', 'Tidak hadir', 'Cuti', 'Tingkat kehadiran (%)', 'Hari kerja'];

            foreach ($this->months($data['records'], $data['result']['dates'], $data['result']['dataThrough'], $schedule, $attendance) as $month) {
                $counts = $month['counts'];
                $lines[] = [$month['label'], $counts['present'], $counts['late'], $counts['early_leave'], $counts['absent'], $counts['leave'], $month['rate'], $month['workdays']];
            }
        } else {
            $lines[] = ['Karyawan', 'ID', 'Cabang', 'Departemen', ...$data['result']['dates'], 'Hadir', 'Terlambat', 'Pulang cepat', 'Tidak hadir', 'Cuti'];

            foreach ($data['rows'] as $row) {
                $counts = $attendance->tally([$row['id'] => $row['days']], $data['result']['dates']);

                $lines[] = [
                    $row['name'],
                    $row['number'],
                    $row['branch'] ?? '',
                    $row['department'] ?? '',
                    ...array_map(fn (string $date): string => $this->cell($row['days'][$date] ?? null), $data['result']['dates']),
                    $counts['present'], $counts['late'], $counts['early_leave'], $counts['absent'], $counts['leave'],
                ];
            }

            $lines[] = [];
            $lines[] = ['Keterangan: H hadir, T terlambat, P pulang cepat, A tidak hadir, C cuti, · libur / tidak ada scan, ? belum ada data'];
        }

        return CsvDownload::stream('kalender-'.$data['from']->toDateString().'-'.$data['to']->toDateString().'.csv', $lines);
    }

    /**
     * One cell of the grid as text: the status letter, then the times when there are any.
     *
     * @param  array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}|null  $day
     */
    private function cell(?array $day): string
    {
        if ($day === null) {
            return '';
        }

        $code = AttendanceDayStatus::from($day['status'])->code();

        if ($day['in'] === null) {
            return $day['event'] !== null ? "{$code} {$day['event']}" : $code;
        }

        return $day['out'] === null ? "{$code} {$day['in']}" : "{$code} {$day['in']}-{$day['out']}";
    }

    /**
     * Everything the calendar and its export both need: the range, the filters, the people, and
     * each person's days. Kept in one place so the two can never drift apart.
     *
     * @return array{
     *     view: string, timezone: string, anchor: CarbonImmutable, from: CarbonImmutable, to: CarbonImmutable,
     *     filters: array<string, mixed>, result: array<string, mixed>, rows: list<array<string, mixed>>,
     *     records: array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>
     * }
     */
    private function gather(Request $request, DailyAttendance $attendance, EmployeeRoster $roster): array
    {
        abort_unless($request->user()->can(Permission::ViewAttendance->value), 403);

        $timezone = (string) config('attendance.device_timezone');
        $view = in_array($request->string('view')->toString(), self::VIEWS, true) ? $request->string('view')->toString() : 'month';

        $anchor = $this->anchor($request, $attendance->dataThrough($timezone), $timezone);
        [$from, $to] = $this->range($view, $anchor);

        $leaveType = RequestType::query()->where('code', $request->string('leave_type')->toString())->value('code');

        $filters = [
            'branches' => OrganisationNames::fromInput($request->input('branches')),
            'departments' => OrganisationNames::fromInput($request->input('departments')),
            'statuses' => array_values(array_intersect(
                (array) $request->input('statuses', []),
                array_column(AttendanceDayStatus::cases(), 'value'),
            )),
            'search' => trim((string) $request->string('search')),
            'team' => $request->integer('team') ?: null,
            'leaveType' => $leaveType,
        ];

        $employees = $roster->between($from, $to, $filters['departments'], $filters['search'], $filters['team'], $filters['leaveType'], $request->user(), $filters['branches']);

        $result = $attendance->forRange($from, $to, $roster->windows($employees));
        $withScannerId = AttendanceEnrolMap::acrossCompanies()->pluck('employee_id')->flip();
        $rows = $this->rowsWithStatus($employees, $result['records'], $filters['statuses'], $withScannerId);

        return [
            'view' => $view,
            'timezone' => $timezone,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'result' => $result,
            'rows' => $rows,
            'records' => collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['days']])->all(),
        ];
    }

    /**
     * Holidays and events inside the range, among those this user may see: group-wide ones, and
     * those for the company being viewed (or their own, if they are bound to one).
     *
     * @return array<int, array<string, mixed>>
     */
    private function events(Request $request, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $user = $request->user();
        $activeCompany = app(CompanyContext::class)->companyId();

        $sizes = [];

        $events = CalendarEvent::query()
            ->with(['company:id,code', 'department:id,name'])
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->when($activeCompany !== null, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('company_id')->orWhere('company_id', $activeCompany),
            ))
            ->orderBy('starts_on')
            ->get();

        // How many times each repeating event happens in all, not only inside this range.
        $seriesIds = $events->pluck('series_id')->filter()->unique()->all();

        if ($seriesIds !== []) {
            $sizes = CalendarEvent::query()->whereIn('series_id', $seriesIds)
                ->selectRaw('series_id, count(*) as total')->groupBy('series_id')->pluck('total', 'series_id')->all();
        }

        return $events
            ->map(fn (CalendarEvent $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'kind' => $event->kind,
                'from' => $event->starts_on->toDateString(),
                'to' => $event->ends_on->toDateString(),
                // Set for an event that is one of a series: how it repeats and how many there are.
                'repeat' => $event->repeat === null ? null : (CalendarEvent::REPEATS[$event->repeat] ?? null),
                'seriesSize' => $event->series_id === null ? 1 : (int) ($sizes[$event->series_id] ?? 1),
                'scope' => match (true) {
                    $event->company_id === null => 'Semua perusahaan',
                    $event->department_id === null => $event->company->code,
                    default => $event->department->name.' · '.$event->company->code,
                },
                'canDelete' => $user->can(Permission::ManageCalendarEvents->value)
                    && ($user->isParentWide() || ($event->company_id !== null && $event->company_id === $user->company_id)),
            ])
            ->all();
    }

    /**
     * What the add-event form may offer this user: every company and group-wide for a parent-wide
     * account, only their own company for a bound one.
     *
     * @return array<string, mixed>|null
     */
    private function eventForm(Request $request): ?array
    {
        $user = $request->user();

        if (! $user->can(Permission::ManageCalendarEvents->value)) {
            return null;
        }

        $companies = Company::query()
            ->active()
            ->when(! $user->isParentWide(), fn (Builder $query) => $query->whereKey($user->company_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return [
            'canBeGroupWide' => $user->isParentWide(),
            'companies' => $companies,
            'departments' => Department::acrossCompanies()
                ->whereIn('company_id', $companies->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'company_id', 'name']),
        ];
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
     * Shape the grid rows, keeping only employees who have one of the chosen statuses in the
     * range when a status filter is on.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>  $records
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
                'branch' => $employee->branch?->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'manager' => $employee->manager?->full_name,
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
     * One summary per date for the week and month views: how many people checked in, how many of
     * those were late, and who was away, for the people the filters select.
     *
     * A holiday is a day every selected person has an event on, so a department's own event does
     * not turn the whole company's day into a holiday.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>  $records
     * @param  list<string>  $dates
     * @return list<array<string, mixed>>
     */
    private function daily(array $records, array $dates, ?string $dataThrough, WorkSchedule $schedule): array
    {
        $summaries = [];

        foreach ($dates as $date) {
            $day = CarbonImmutable::parse($date);
            $counts = array_fill_keys(array_map(fn (AttendanceDayStatus $status): string => $status->value, AttendanceDayStatus::cases()), 0);
            $withEvent = 0;
            $eventTitle = null;
            $people = 0;

            foreach ($records as $byDate) {
                if (! isset($byDate[$date])) {
                    continue;
                }

                $people++;
                $counts[$byDate[$date]['status']]++;

                if ($byDate[$date]['event'] !== null) {
                    $withEvent++;
                    $eventTitle ??= $byDate[$date]['event'];
                }
            }

            $summaries[] = [
                'date' => $date,
                'day' => $day->day,
                'weekday' => $this->indonesian($day, 'D'),
                'isWorkday' => $schedule->isWorkday($day),
                'covered' => $dataThrough !== null && $date <= $dataThrough,
                'attended' => $counts['present'] + $counts['late'] + $counts['early_leave'],
                'late' => $counts['late'],
                'earlyLeave' => $counts['early_leave'],
                'absent' => $counts['absent'],
                'leave' => $counts['leave'],
                'holiday' => $people > 0 && $withEvent === $people ? $eventTitle : null,
            ];
        }

        return $summaries;
    }

    /**
     * Company totals per month, for the year view: the attendance rate, working days, leave days,
     * late arrivals and unexplained absences, as person-days.
     *
     * The rate is attended over attended + absent, the same definition as the side panel. Approved leave
     * is not an expectation to be present, so it counts neither for nor against.
     * A month with none of those has no data yet and no rate, rather than a 0% that reads as a
     * disaster.
     *
     * @param  array<int, array<string, array{status: string, in: string|null, out: string|null, punches: int, minutes: int|null, duplicate: bool, event: string|null}>>  $records
     * @param  list<string>  $dates
     * @return list<array<string, mixed>>
     */
    private function months(array $records, array $dates, ?string $dataThrough, WorkSchedule $schedule, DailyAttendance $attendance): array
    {
        $daily = collect($this->daily($records, $dates, $dataThrough, $schedule));
        $months = [];

        foreach ($daily->groupBy(fn (array $day): int => (int) substr($day['date'], 5, 2)) as $month => $days) {
            $attended = $days->sum('attended');
            $absent = $days->sum('absent');
            $leave = $days->sum('leave');
            $expected = $attended + $absent;

            $months[] = [
                'month' => $month,
                'label' => $this->indonesian(CarbonImmutable::create(2000, $month, 1), 'F'),
                'firstDate' => $days->first()['date'],
                'counts' => $attendance->tally($records, array_values(array_map(strval(...), $days->pluck('date')->all()))),
                'attended' => $attended,
                'expected' => $expected,
                'rate' => $expected === 0 ? null : round($attended / $expected * 100, 1),
                'workdays' => $days->filter(fn (array $day): bool => $day['isWorkday'] && $day['holiday'] === null)->count(),
                'leaveDays' => $leave,
                'late' => $days->sum('late'),
                'absent' => $absent,
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
            'expected' => $attended + $counts['absent'],
            'employees' => $employees,
        ];
    }

    /**
     * Who is on leave in the range on screen (ATT-SID-002): anyone with at least one day of approved
     * leave inside it. It follows the view, so on the Day view it is that day, on Week that week, and
     * so on, and it is drawn from the same people as the grid, so every filter narrows it too. A
     * chosen leave kind narrows the leave itself, not only the people.
     *
     * @param  list<array<string, mixed>>  $rows  the people the grid shows
     * @return array{people: int, entries: array<int, array<string, mixed>>, more: int}
     */
    private function onLeave(array $rows, CarbonImmutable $from, CarbonImmutable $to, ?string $leaveType): array
    {
        $people = collect($rows)->keyBy('id');

        $leaves = LeaveRequest::query()
            ->with('type')
            ->whereIn('employee_id', $people->keys()->all())
            ->when($leaveType !== null, fn (Builder $query) => $query->where('leave_type', $leaveType))
            ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', 'approved'))
            ->whereDate('start_on', '<=', $to->toDateString())
            ->whereDate('end_on', '>=', $from->toDateString())
            ->orderBy('start_on')
            ->orderBy('id')
            ->get();

        return [
            'people' => $leaves->pluck('employee_id')->unique()->count(),
            'entries' => $leaves->take(self::ON_LEAVE_LIMIT)->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'employeeId' => $leave->employee_id,
                'name' => $people[$leave->employee_id]['name'],
                'department' => $people[$leave->employee_id]['department'],
                'type' => $leave->typeLabel(),
                'from' => $leave->start_on->toDateString(),
                'to' => $leave->end_on->toDateString(),
                'timeframe' => $leave->timeframe(),
            ])->values()->all(),
            'more' => max(0, $leaves->count() - self::ON_LEAVE_LIMIT),
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
