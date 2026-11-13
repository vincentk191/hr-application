<?php

namespace App\Actions\Leave;

use App\Enums\LeaveRequestStage;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Leave\LeaveAttachments;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sends a rejected leave request round again after its requester corrected it.
 *
 * The request is updated, a fresh approval chain is built and the stage goes back to "submitted",
 * so it appears in the approvers' queue as waiting. What the approvers said the first time is not
 * lost: the engine copies each step's note into the request's comment thread before the chain is
 * rebuilt. What the requester changed is not asked for: the request as it was is compared with the request as it is now,
 * field by field, and what was changed, added or removed (the files too) is added to the same thread.
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
        array $newFiles,
        array $removeAttachmentIds,
        User $by,
    ): LeaveRequest {
        $result = DB::transaction(function () use ($leave, $attributes, $newFiles, $removeAttachmentIds, $by): LeaveRequest {
            $before = $this->snapshot($leave);
            $filesBefore = $this->files($leave);

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

            $this->engine->comment($instance, $by, $this->changes($before, $this->snapshot($leave->refresh()), $filesBefore, $this->files($leave)));

            return $leave;
        });

        if (! $this->submit->approveIfFiledByAtasan($result->refresh(), $by)) {
            app(Notifier::class)->leaveSubmitted($result, $by, resubmitted: true);
        }

        return $result;
    }

    /**
     * The fields the requester can change, as they read, with null for one that is empty.
     *
     * @return array<string, string|null>
     */
    private function snapshot(LeaveRequest $leave): array
    {
        $day = fn (CarbonInterface $date): string => CarbonImmutable::instance($date)->settings(['locale' => 'id'])->translatedFormat('j M Y');
        $reason = trim((string) $leave->reason);

        return [
            'Jenis' => $leave->typeLabel(),
            'Hari pertama' => $day($leave->start_on),
            'Hari terakhir' => $day($leave->end_on),
            'Hari kerja' => $leave->days.' hari',
            'Jam' => $leave->timeframe(),
            'Catatan' => $reason === '' ? null : $reason,
        ];
    }

    /**
     * The request's files, by id, so a file replaced by one of the same name still shows as removed and added.
     *
     * @return array<int, string>
     */
    private function files(LeaveRequest $leave): array
    {
        return $leave->attachments()->pluck('original_name', 'id')->all();
    }

    /**
     * What differs between the request as it was and as it is: a line per field that was changed, added or removed, and one
     * for the files added and the files removed.
     *
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @param  array<int, string>  $filesBefore
     * @param  array<int, string>  $filesAfter
     */
    private function changes(array $before, array $after, array $filesBefore, array $filesAfter): string
    {
        $show = fn (string $label, string $value): string => $label === 'Catatan' ? '“'.Str::limit($value, 120).'”' : $value;
        $lines = [];

        foreach ($after as $label => $new) {
            $old = $before[$label];

            if ($old === $new) {
                continue;
            }

            $lines[] = match (true) {
                $old === null => "{$label}: ditambahkan {$show($label, (string) $new)}",
                $new === null => "{$label}: dihapus ({$show($label, $old)})",
                default => "{$label}: {$show($label, $old)} → {$show($label, $new)}",
            };
        }

        $added = array_values(array_diff_key($filesAfter, $filesBefore));
        $removed = array_values(array_diff_key($filesBefore, $filesAfter));

        if ($added !== []) {
            $lines[] = 'Lampiran ditambahkan: '.implode(', ', $added);
        }

        if ($removed !== []) {
            $lines[] = 'Lampiran dihapus: '.implode(', ', $removed);
        }

        return $lines === [] ? 'Tidak ada perubahan pada data pengajuan.' : implode("\n", $lines);
    }
}
