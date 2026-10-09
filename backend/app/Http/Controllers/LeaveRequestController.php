<?php

namespace App\Http\Controllers;

use App\Actions\Leave\ResubmitLeaveRequest;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\WorkflowStepStatus;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Leave\LeaveLimits;
use App\Support\Leave\LeaveRequestPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        $requests = LeaveRequest::query()
            ->visibleTo($user)
            ->with(['employee:id,full_name,employee_number,department_id,user_id', 'employee.department:id,name', 'workflow.steps.decidedBy:id,name', 'workflow.comments.author:id,name', 'attachments', 'type'])
            ->latest('id')
            ->get()
            ->map(fn (LeaveRequest $leave): array => $this->present($leave, $user));

        return Inertia::render('leave-requests/index', [
            'requests' => $requests,
            // The nav badge counts what is waiting on this person (REQ-DSH-007).
            'waitingOnMe' => WorkflowStep::waitingOn($user)->count(),
        ]);
    }

    /**
     * The new-request form (REQ-WIZ).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', LeaveRequest::class);

        $user = $request->user();
        $canFileForOthers = $user->can('createOnBehalf', LeaveRequest::class);

        return Inertia::render('leave-requests/create', [
            'leaveTypes' => app(LeaveLimits::class)->options(),
            'canFileForOthers' => $canFileForOthers,
            'employees' => Employee::query()
                ->active()
                ->when(! $canFileForOthers, fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'label' => "{$employee->full_name} ({$employee->employee_number})",
                ]),
        ]);
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

        $leaveRequest->load(['employee:id,full_name,employee_number,user_id', 'workflow.steps.decidedBy:id,name', 'workflow.comments.author:id,name', 'attachments']);

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
