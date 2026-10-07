<?php

namespace App\Http\Controllers;

use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\LeaveType;
use App\Enums\WorkflowStepStatus;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    /**
     * The requests queue (REQ-DSH).
     *
     * Every leave request in the active company; CompanyScope does the filtering, so there is
     * no company clause here to forget.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();

        $requests = LeaveRequest::query()
            ->with(['employee:id,full_name,employee_number,department_id', 'employee.department:id,name', 'workflow.steps'])
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
            'leaveTypes' => LeaveType::options(),
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
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengajuan {$leave->reference()} terkirim.",
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
            'type' => $leave->leave_type->label(),
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
            ])->values() ?? [],
        ];
    }
}
