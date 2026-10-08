<?php

namespace App\Actions\Leave;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Leave\LeaveAttachments;
use App\Support\Notifications\Notifier;
use App\Workflows\Definitions\LeaveRequestWorkflow;
use App\Workflows\StepBlueprint;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creates a leave request and starts its approval workflow (REQ-WIZ-009).
 *
 * Kept out of the controller because building the chain is the interesting part and it is
 * worth testing without going through HTTP.
 */
class SubmitLeaveRequest
{
    public function __construct(
        private WorkflowEngine $engine,
        private LeaveRequestWorkflow $definition,
    ) {}

    /**
     * @param  array{leave_type: string, start_on: CarbonInterface, end_on: CarbonInterface, reason: ?string}  $attributes
     * @param  list<UploadedFile>  $files  supporting documents, optional
     */
    public function handle(Employee $employee, array $attributes, User $submittedBy, array $files = []): LeaveRequest
    {
        $leave = DB::transaction(function () use ($employee, $attributes, $submittedBy, $files): LeaveRequest {
            $request = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type' => $attributes['leave_type'],
                'start_on' => $attributes['start_on'],
                'end_on' => $attributes['end_on'],
                'days' => LeaveRequest::workingDays($attributes['start_on'], $attributes['end_on'], $employee),
                'reason' => $attributes['reason'] ?? null,
                'created_by_user_id' => $submittedBy->id,
            ]);

            app(LeaveAttachments::class)->store($request, $files, $submittedBy);

            $this->engine->start($request, $this->definition, $this->chain($employee), $submittedBy);

            return $request;
        });

        // After the transaction, so nobody is told about a request that then failed to save.
        app(Notifier::class)->leaveAwaitingApproval($leave, $submittedBy);

        return $leave;
    }

    /**
     * The approval chain: the employee's own supervisor first, then HR (context.md §9.6).
     *
     * The supervisor step is bound to the person the dossier names, so the decision cannot
     * drift to another supervisor; the HR step is left open to whoever holds the role, since
     * any HR Admin may clear it. An employee with no supervisor on record goes straight to HR
     * rather than becoming unapprovable.
     *
     * @return list<StepBlueprint>
     */
    public function chain(Employee $employee): array
    {
        $steps = [];

        $supervisorUserId = $employee->manager?->user_id;

        if ($supervisorUserId !== null) {
            $steps[] = StepBlueprint::forUserAsRole(
                'Atasan langsung',
                $supervisorUserId,
                Role::Supervisor,
            );
        }

        $steps[] = StepBlueprint::forRole('HR Admin', Role::HrAdmin);

        return $steps;
    }
}
