<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceIssueType;
use App\Enums\Permission;
use App\Http\Requests\ResolveAttendanceIssuesRequest;
use App\Models\AttendanceIssueResolution;
use App\Models\Employee;
use App\Support\Attendance\CaptureStatus;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\DateRangeInput;
use App\Support\Attendance\EmployeeRoster;
use App\Support\Attendance\IssueDetector;
use App\Support\OrganisationNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Attendance exceptions to follow up (ATT-ISS): who was late, who left early, who never checked
 * out, who was absent without approved leave, whose finger was read twice.
 *
 * Issues are detected from the punches every time and never stored; what is stored is a decision
 * to excuse one, with a reason, which is also what the audit trail shows (ATT-ISS-006). Like the
 * calendar, it follows the company switcher.
 */
class AttendanceIssueController extends Controller
{
    /**
     * Longest range read at once. An issue list for a whole year is not something anyone can act
     * on, and it would mean deriving every employee-day of it.
     */
    private const MAX_DAYS = 62;

    private const MAX_ROWS = 500;

    public function index(Request $request, IssueDetector $detector, EmployeeRoster $roster, DailyAttendance $attendance, CaptureStatus $capture): Response
    {
        abort_unless($request->user()->can(Permission::ViewAttendance->value), 403);

        $timezone = (string) config('attendance.device_timezone');
        [$from, $to] = DateRangeInput::fromRequest($request, $attendance->dataThrough($timezone), $timezone, self::MAX_DAYS);

        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'types' => array_values(array_intersect(
                (array) $request->input('types', []),
                array_map(fn (AttendanceIssueType $type): string => $type->value, AttendanceIssueType::cases()),
            )),
            'departments' => OrganisationNames::fromInput($request->input('departments')),
            'state' => in_array($request->string('state')->toString(), ['open', 'resolved', 'all'], true)
                ? $request->string('state')->toString()
                : 'open',
        ];

        $employees = $roster->between($from, $to, $filters['departments']);
        $found = $detector->detect($from, $to, $employees);

        $inState = array_values(array_filter($found, fn (array $issue): bool => match ($filters['state']) {
            'open' => $issue['resolution'] === null,
            'resolved' => $issue['resolution'] !== null,
            default => true,
        }));

        $counts = array_fill_keys(array_map(fn (AttendanceIssueType $type): string => $type->value, AttendanceIssueType::cases()), 0);

        foreach ($inState as $issue) {
            $counts[$issue['type']]++;
        }

        $shown = $filters['types'] === []
            ? $inState
            : array_values(array_filter($inState, fn (array $issue): bool => in_array($issue['type'], $filters['types'], true)));

        return Inertia::render('attendance/issues', [
            'issues' => array_slice($shown, 0, self::MAX_ROWS),
            'truncated' => count($shown) > self::MAX_ROWS,
            'total' => count($shown),
            'counts' => $counts,
            'filters' => $filters,
            'options' => [
                'types' => array_map(fn (AttendanceIssueType $type): array => ['value' => $type->value, 'label' => $type->label()], AttendanceIssueType::cases()),
                'departments' => OrganisationNames::departments(),
            ],
            'dataThrough' => $attendance->dataThrough($timezone),
            'capture' => $capture->resolve(),
            'canResolve' => $request->user()->can(Permission::CorrectAttendance->value),
        ]);
    }

    /**
     * Excuse issues, with one reason for the whole batch (ATT-ISS-004, ATT-ISS-005).
     */
    public function resolve(ResolveAttendanceIssuesRequest $request): RedirectResponse
    {
        $employees = $this->employeesFor($request);

        DB::transaction(function () use ($request, $employees): void {
            foreach ($request->validated('items') as $item) {
                /** @var Employee $employee */
                $employee = $employees[$item['employee_id']];

                $resolution = AttendanceIssueResolution::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $item['date'], 'type' => $item['type']],
                    [
                        'company_id' => $employee->company_id,
                        'resolution' => AttendanceIssueResolution::EXCUSED,
                        'reason' => $request->validated('reason'),
                        'resolved_by_user_id' => $request->user()->id,
                    ],
                );

                activity('attendance')
                    ->performedOn($resolution)
                    ->causedBy($request->user())
                    ->withProperties([
                        'employee_id' => $employee->id,
                        'date' => $item['date'],
                        'type' => $item['type'],
                        'reason' => $request->validated('reason'),
                    ])
                    ->log('Issue excused');
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => count($request->validated('items')).' masalah ditandai dimaklumi.']);

        return back();
    }

    /**
     * Put excused issues back among the open ones, for a decision made in error.
     */
    public function reopen(ResolveAttendanceIssuesRequest $request): RedirectResponse
    {
        $employees = $this->employeesFor($request);

        DB::transaction(function () use ($request, $employees): void {
            foreach ($request->validated('items') as $item) {
                $resolution = AttendanceIssueResolution::query()
                    ->where('employee_id', $employees[$item['employee_id']]->id)
                    ->whereDate('date', $item['date'])
                    ->where('type', $item['type'])
                    ->first();

                if ($resolution === null) {
                    continue;
                }

                activity('attendance')
                    ->performedOn($resolution)
                    ->causedBy($request->user())
                    ->withProperties([
                        'employee_id' => $resolution->employee_id,
                        'date' => $item['date'],
                        'type' => $item['type'],
                        'previous_reason' => $resolution->reason,
                        'reason' => $request->validated('reason'),
                    ])
                    ->log('Issue reopened');

                $resolution->delete();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Masalah dibuka kembali.']);

        return back();
    }

    /**
     * The employees a batch refers to, each one checked: the whole batch is refused if the user
     * may not correct any single one of them, rather than half-applying it.
     *
     * @return array<int, Employee>
     */
    private function employeesFor(ResolveAttendanceIssuesRequest $request): array
    {
        /** @var list<array{employee_id: int}> $items */
        $items = $request->validated('items');
        $ids = array_values(array_unique(array_column($items, 'employee_id')));
        $employees = Employee::query()->whereIn('id', $ids)->get()->keyBy('id');

        abort_if($employees->count() !== count($ids), 404);

        foreach ($employees as $employee) {
            abort_unless($request->user()->can('correctAttendance', $employee), 403);
        }

        return $employees->all();
    }
}
