<?php

namespace App\Actions\Offboarding;

use App\Actions\Profile\SubmitProfileChange;
use App\Enums\LeaveRequestStage;
use App\Models\AttendanceEnrolMap;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OffboardingCase;
use App\Models\ProfileChangeRequest;
use App\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The exit itself, once, on the day after the last working day (OFF-CHK-010): the person's account is
 * switched off and their sessions end (roles are kept), their exit date is set, the scanner mapping
 * ends, and requests of theirs still waiting are cleared. Running it again does nothing.
 *
 * The employee record stays "aktif" until the case is closed; closing makes it inactive and locks it.
 */
class ApplyOffboardingExit
{
    public function __construct(private WorkflowEngine $engine, private SubmitProfileChange $profileChanges) {}

    public function handle(OffboardingCase $case): void
    {
        if ($case->exitApplied() || $case->last_working_on === null) {
            return;
        }

        $day = $case->last_working_on;

        DB::transaction(function () use ($case, $day): void {
            $employee = Employee::acrossCompanies()->with('user')->findOrFail($case->employee_id);

            $employee->update(['exited_on' => $day->toDateString()]);

            // Roles stay, so the history of who they were is intact and a return is simple; a deactivated
            // account cannot sign in. Saving it ends its sessions and tokens.
            if ($employee->user !== null && $employee->user->is_active) {
                $employee->user->forceFill(['is_active' => false])->save();
            }

            // Scanners reuse numbers, so the mapping is closed rather than deleted: old punches stay theirs.
            AttendanceEnrolMap::acrossCompanies()->where('employee_id', $employee->id)
                ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $day->toDateString()))
                ->update(['valid_to' => $day->toDateString()]);

            $this->clearLeave($employee, $day->toDateString());
            $this->clearProfileChanges($employee);

            $case->update(['exit_applied_at' => now()]);
        });
    }

    /**
     * Requests still waiting are rejected with a note saying why, so nothing is left waiting on someone
     * who has gone.
     */
    private function clearLeave(Employee $employee, string $day): void
    {
        LeaveRequest::acrossCompanies()->where('employee_id', $employee->id)
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', LeaveRequestStage::Submitted->value))
            ->with('workflow')
            ->get()
            ->each(function (LeaveRequest $leave) use ($day): void {
                $leave->workflow->steps()->whereIn('status', ['pending', 'locked'])->update([
                    'status' => 'rejected',
                    'decided_at' => now(),
                    'note' => "Dibatalkan otomatis: karyawan keluar setelah {$day}.",
                ]);

                $this->engine->transitionTo($leave->workflow->refresh(), LeaveRequestStage::Rejected);
            });
    }

    private function clearProfileChanges(Employee $employee): void
    {
        ProfileChangeRequest::acrossCompanies()->pending()->where('employee_id', $employee->id)->get()
            ->each(fn (ProfileChangeRequest $request) => $this->profileChanges->withdraw($request));
    }
}
