<?php

namespace App\Actions\Leave;

use App\Enums\LeaveRequestStage;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Leave\LeaveAttachments;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Sends a rejected leave request round again after its requester corrected it.
 *
 * The request is updated, a fresh approval chain is built and the stage goes back to "submitted",
 * so it appears in the approvers' queue as waiting. What the approvers said the first time is not
 * lost: the engine copies each step's note into the request's comment thread before the chain is
 * rebuilt, and the requester's own note about what they changed is added to the same thread.
 */
class ResubmitLeaveRequest
{
    public function __construct(
        private WorkflowEngine $engine,
        private SubmitLeaveRequest $submit,
        private LeaveAttachments $attachments,
    ) {}

    /**
     * @param  array{leave_type: string, start_on: CarbonInterface, end_on: CarbonInterface, reason: ?string, start_time?: ?string, end_time?: ?string}  $attributes
     * @param  list<UploadedFile>  $newFiles
     * @param  array<int, int>  $removeAttachmentIds
     */
    public function handle(
        LeaveRequest $leave,
        array $attributes,
        ?string $note,
        array $newFiles,
        array $removeAttachmentIds,
        User $by,
    ): LeaveRequest {
        $result = DB::transaction(function () use ($leave, $attributes, $note, $newFiles, $removeAttachmentIds, $by): LeaveRequest {
            $before = $this->summary($leave);

            $leave->update([
                'leave_type' => $attributes['leave_type'],
                'start_on' => $attributes['start_on'],
                'end_on' => $attributes['end_on'],
                'start_time' => $attributes['start_time'] ?? null,
                'end_time' => $attributes['end_time'] ?? null,
                'days' => LeaveRequest::workingDays($attributes['start_on'], $attributes['end_on'], $leave->employee),
                'reason' => $attributes['reason'] ?? null,
            ]);

            $this->attachments->remove($leave, $removeAttachmentIds);
            $this->attachments->store($leave, $newFiles, $by);

            $leave->load('employee.manager');

            $instance = $this->engine->reopen(
                $leave->workflow,
                $this->submit->chain($leave->employee, $leave->leave_type),
                LeaveRequestStage::Submitted,
                $by,
            );

            $changes = $before === ($after = $this->summary($leave->refresh()))
                ? 'Tidak ada perubahan pada data pengajuan.'
                : "Diubah dari {$before} menjadi {$after}.";

            $this->engine->comment($instance, $by, trim((string) $note) === '' ? $changes : trim((string) $note)."\n\n".$changes);

            return $leave;
        });

        if (! $this->submit->approveIfFiledByAtasan($result->refresh(), $by)) {
            app(Notifier::class)->leaveSubmitted($result, $by, resubmitted: true);
        }

        return $result;
    }

    private function summary(LeaveRequest $leave): string
    {
        $time = $leave->timeframe() === null ? '' : ", jam {$leave->timeframe()}";

        return "{$leave->typeLabel()}, {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()}{$time} ({$leave->days} hari)";
    }
}
