<?php

namespace App\Http\Controllers;

use App\Actions\Leave\ResubmitLeaveRequest;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\WorkflowStepStatus;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Access\Entrance;
use App\Support\CsvDownload;
use App\Support\Leave\LeaveLimits;
use App\Support\Leave\LeaveRequestPresenter;
use App\Support\OrganisationNames;
use App\Support\ReportingLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    /**
     * The requests queue (REQ-DSH).
     *
     * Every leave request in the active company for roles that can see employees; CompanyScope
     * does the company filtering, so there is no company clause here to forget. Anyone else — an
     * ordinary employee — sees only their own requests and the ones they are asked to approve.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();
        $filters = $this->filters($request);

        $requests = $this->filtered($user, $filters);

        return Inertia::render('leave-requests/index', [
            'requests' => $requests,
            'filters' => $filters,
            'options' => [
                'types' => RequestType::query()->ordered()->get(['code', 'label'])
                    ->map(fn (RequestType $type): array => ['value' => $type->code, 'label' => $type->label])->values(),
                'branches' => OrganisationNames::branches($request->user()),
                'departments' => OrganisationNames::departments($request->user()),
            ],
            // What is waiting on this person, and what was approved this month (REQ-DSH-001).
            'waitingOnMe' => WorkflowStep::waitingOn($user)->count(),
            'approvedThisMonth' => LeaveRequest::query()->visibleTo($user)
                ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', 'approved')
                    ->where('completed_at', '>=', now()->startOfMonth()))
                ->count(),
        ]);
    }

    /**
     * The queue as a CSV (REQ-DSH-006): the same filters, the same rows, as on screen.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $rows = collect($this->filtered($request->user(), $this->filters($request)))->map(fn (array $row): array => [
            $row['reference'], $row['employee'], $row['branch'], $row['department'], $row['type'], $row['startOn'], $row['endOn'],
            $row['timeframe'], $row['days'], $row['stageLabel'], $row['waitingOn'], $row['submittedAt'],
        ]);

        return CsvDownload::stream('pengajuan-'.now()->format('Y-m-d').'.csv', [
            ['Pengajuan', 'Karyawan', 'Cabang', 'Departemen', 'Jenis', 'Dari', 'Sampai', 'Jam', 'Hari kerja', 'Status', 'Menunggu', 'Diajukan'],
            ...$rows,
        ]);
    }

    /**
     * What the queue is narrowed by, read from the query string. Anything unrecognised is dropped, so
     * a stale or hand-edited link just shows the whole queue.
     *
     * @return array{status: string, type: string|null, branch: string|null, department: string|null, month: string|null}
     */
    private function filters(Request $request): array
    {
        $type = $request->string('type')->toString();
        $month = $request->string('month')->toString();
        $branch = $request->string('branch')->toString();
        $department = $request->string('department')->toString();

        return [
            'status' => in_array($request->string('status')->toString(), ['mine', 'waiting', 'approved', 'rejected'], true)
                ? $request->string('status')->toString()
                : 'all',
            'type' => $type !== '' && RequestType::query()->where('code', $type)->exists() ? $type : null,
            'branch' => in_array($branch, OrganisationNames::branches(), true) ? $branch : null,
            'department' => in_array($department, OrganisationNames::departments(), true) ? $department : null,
            'month' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : null,
        ];
    }

    /**
     * @param  array{status: string, type: string|null, branch: string|null, department: string|null, month: string|null}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filtered(User $user, array $filters): array
    {
        $monthStart = $filters['month'] === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $filters['month'].'-01');

        return LeaveRequest::query()
            ->visibleTo($user)
            ->with(['employee:id,full_name,employee_number,branch_id,department_id,user_id,company_id', 'employee.branch:id,name', 'employee.department:id,name', 'workflow.steps.decidedBy:id,name', 'workflow.comments.author:id,name', 'attachments', 'type'])
            ->when($filters['type'] !== null, fn (Builder $query) => $query->where('leave_type', $filters['type']))
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->whereHas('employee', fn (Builder $employee) => $employee->whereHas('branch', fn (Builder $branch) => $branch->where('name', $filters['branch']))))
            ->when($filters['department'] !== null, fn (Builder $query) => $query->whereHas('employee', fn (Builder $employee) => $employee->whereHas('department', fn (Builder $department) => $department->where('name', $filters['department']))))
            // A month catches any request whose days touch it.
            ->when($monthStart !== null, fn (Builder $query) => $query
                ->whereDate('start_on', '<=', $monthStart->endOfMonth()->toDateString())
                ->whereDate('end_on', '>=', $monthStart->toDateString()))
            ->when(in_array($filters['status'], ['waiting', 'approved', 'rejected'], true), fn (Builder $query) => $query->whereHas(
                'workflow',
                fn (Builder $workflow) => $workflow->where('current_stage', $filters['status'] === 'waiting' ? 'submitted' : $filters['status']),
            ))
            ->latest('id')
            ->get()
            ->map(fn (LeaveRequest $leave): array => $this->present($leave, $user))
            ->when($filters['status'] === 'mine', fn (Collection $rows) => $rows->filter(fn (array $row): bool => $row['canDecide'])->values())
            ->values()
            ->all();
    }

    /**
     * The new-request form (REQ-WIZ).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', LeaveRequest::class);

        $user = $request->user();
        $canFileForOthers = $user->can('createOnBehalf', LeaveRequest::class);

        $date = $request->string('date')->toString();

        return Inertia::render('leave-requests/create', [
            // Opened from an employee's profile or an issue, the form comes filled in: who, and which day.
            'prefill' => [
                'employeeId' => $request->integer('employee') ?: null,
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d') ? $date : null,
            ],
            'leaveTypes' => app(LeaveLimits::class)->options(),
            'canFileForOthers' => $canFileForOthers,
            'canBackdate' => $user->can('backdate', LeaveRequest::class),
            'employees' => Employee::query()
                ->active()
                ->where(fn ($query) => $this->fileableBy($user, $query))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'label' => "{$employee->full_name} ({$employee->employee_number})",
                ]),
        ]);
    }

    /**
     * Narrow a query to the employees this person may file for: HR Admin, everyone in the company;
     * Management, themselves and their downline; anyone else, only themselves.
     *
     * @param  Builder<Employee>  $query
     */
    private function fileableBy(User $user, Builder $query): void
    {
        // On the public entrance everyone files for themselves only (CORE-ACC-004).
        if (app(Entrance::class)->isPublic()) {
            $query->where('user_id', $user->id);

            return;
        }

        if ($user->can(Permission::CreateEmployees->value)) {
            return;
        }

        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        if ($user->isA(Role::Management) && $own !== null) {
            $query->whereIn('id', app(ReportingLine::class)->scopeIds($own));

            return;
        }

        $query->where('user_id', $user->id);
    }

    public function store(StoreLeaveRequestRequest $request, SubmitLeaveRequest $submit): RedirectResponse
    {
        $leave = $submit->handle(
            $request->employee(),
            [
                'leave_type' => $request->validated()['leave_type'],
                'start_on' => $request->date('start_on'),
                'end_on' => $request->date('end_on'),
                'reason' => $request->validated()['reason'] ?? null,
                ...$request->timeframe(),
            ],
            $request->user(),
            array_values((array) $request->file('attachments', [])),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengajuan {$leave->reference()} terkirim.",
        ]);

        return to_route('leave-requests.index');
    }

    /**
     * The form for correcting a rejected request, filled with what was sent and showing what the
     * approvers said.
     */
    public function edit(Request $request, LeaveRequest $leaveRequest): Response
    {
        $this->authorize('update', $leaveRequest);

        $leaveRequest->load(['employee:id,full_name,employee_number,user_id,company_id', 'workflow.steps.decidedBy:id,name', 'workflow.comments.author:id,name', 'attachments']);

        return Inertia::render('leave-requests/edit', [
            'leaveTypes' => app(LeaveLimits::class)->options($leaveRequest->leave_type),
            'request' => [
                ...$this->present($leaveRequest, $request->user()),
                'leaveType' => $leaveRequest->leave_type,
                'startTime' => $leaveRequest->start_time,
                'endTime' => $leaveRequest->end_time,
            ],
        ]);
    }

    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest, ResubmitLeaveRequest $resubmit): RedirectResponse
    {
        $resubmit->handle(
            $leaveRequest,
            [
                'leave_type' => $request->validated()['leave_type'],
                'start_on' => $request->date('start_on'),
                'end_on' => $request->date('end_on'),
                'reason' => $request->validated()['reason'] ?? null,
                ...$request->timeframe(),
            ],
            $request->validated('note'),
            array_values((array) $request->file('attachments', [])),
            array_map('intval', (array) $request->input('remove_attachments', [])),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengajuan {$leaveRequest->reference()} diajukan ulang dan menunggu persetujuan.",
        ]);

        return to_route('leave-requests.index');
    }

    /**
     * Shape one request for the queue and the decision dialog.
     *
     * @return array<string, mixed>
     */
    private function present(LeaveRequest $leave, User $user): array
    {
        $instance = $leave->workflow;
        $openStep = $instance?->steps->firstWhere('status', WorkflowStepStatus::Pending);

        return [
            'id' => $leave->id,
            'reference' => $leave->reference(),
            'employee' => $leave->employee?->full_name,
            'branch' => $leave->employee?->branch?->name,
            'department' => $leave->employee?->department?->name,
            'type' => $leave->typeLabel(),
            'timeframe' => $leave->timeframe(),
            'startOn' => $leave->start_on->toDateString(),
            'endOn' => $leave->end_on->toDateString(),
            'days' => $leave->days,
            'reason' => $leave->reason,
            'submittedAt' => $leave->created_at?->toDateString(),
            'stage' => $instance?->stage()->value,
            'stageLabel' => $instance?->stage()->label(),
            // Who the request is waiting on, straight from the engine (CORE-WFL-008).
            'waitingOn' => $openStep?->label,
            'canDecide' => $openStep !== null && $openStep->canBeDecidedBy($user),
            'steps' => $instance?->steps->map(fn (WorkflowStep $step): array => [
                'label' => $step->label,
                'status' => $step->status->value,
                'statusLabel' => $step->status->label(),
                'note' => $step->note,
                'decidedBy' => $step->decidedBy?->name,
                'decidedAt' => $step->decided_at?->toDateString(),
            ])->values() ?? [],
            ...LeaveRequestPresenter::extras($leave, $user),
        ];
    }
}
