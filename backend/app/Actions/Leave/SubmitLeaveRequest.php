<?php

namespace App\Actions\Leave;

use App\Enums\LeaveRequestStage;
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
     * @param  array{leave_type: string, start_on: CarbonInterface, end_on: CarbonInterface, reason: ?string, start_time?: ?string, end_time?: ?string}  $attributes
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
                'start_time' => $attributes['start_time'] ?? null,
                'end_time' => $attributes['end_time'] ?? null,
                'days' => LeaveRequest::workingDays($attributes['start_on'], $attributes['end_on'], $employee),
                'reason' => $attributes['reason'] ?? null,
                'created_by_user_id' => $submittedBy->id,
            ]);

            app(LeaveAttachments::class)->store($request, $files, $submittedBy);

            $this->engine->start($request, $this->definition, $this->chain($employee), $submittedBy);

            return $request;
        });

        // After the transaction, so nobody is told about a request that then failed to save.
        if (! $this->approveIfFiledByAtasan($leave, $submittedBy)) {
            app(Notifier::class)->leaveSubmitted($leave, $submittedBy);
        }

        return $leave;
    }

    /**
     * A request filed for someone by the person who would approve it is approved on the spot, with
     * that person recorded as the approver (REQ-WIZ-015). Only the named Atasan counts: HR filling in
     * for an employee with no Atasan is not approving as one, and nobody approves their own request.
     * The employee and every HR Admin are told, as for any approval.
     */
    public function approveIfFiledByAtasan(LeaveRequest $leave, User $by): bool
    {
        $leave->loadMissing('employee', 'workflow');
        $step = $this->engine->waitingOn($leave->workflow);

        if ($step === null || $step->approver_user_id !== $by->id || $leave->employee->user_id === $by->id) {
            return false;
        }

        $this->engine->approve($step, $by, 'Diajukan dan disetujui oleh atasan');
        $this->engine->transitionTo($leave->workflow->refresh(), LeaveRequestStage::Approved, $by);
        app(Notifier::class)->leaveFiledAndApproved($leave->refresh(), $by);

        return true;
    }

    /**
     * The approval chain: the employee's own Atasan, and nobody else (context.md §9.6).
     *
     * HR Admin is not an approver of leave; they see every request and are told of each one, but
     * the decision is the Atasan's. The step is bound to the person the dossier names, so it cannot
     * drift to another supervisor. When that person is themselves an HR Admin (the employee reports
     * to HR) they decide it as the Atasan, like anyone else.
     *
     * One exception keeps a request from being stuck: an employee with no Atasan on record, or one
     * with no login or a deactivated account, has nobody who could approve, so the request goes to
     * HR Admin instead.
     *
     * @return list<StepBlueprint>
     */
    public function chain(Employee $employee): array
    {
        $supervisor = $employee->manager?->user;

        if ($supervisor !== null && $supervisor->is_active) {
            return [StepBlueprint::forUser('Atasan langsung', $supervisor)];
        }

        return [StepBlueprint::forRole('HR Admin (tanpa atasan)', Role::HrAdmin)];
    }
}
