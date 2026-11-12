<?php

namespace App\Support\Leave;

use App\Enums\WorkflowStepStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use App\Models\WorkflowComment;
use App\Models\WorkflowStep;
use App\Support\Files\InlineImage;

/**
 * The parts of a leave request that both the queue and the profile show: its files, the history of
 * what approvers and the requester said across rounds, and whether the viewer can correct it.
 *
 * Expects `attachments` and `workflow.comments.author` to be eager loaded.
 */
class LeaveRequestPresenter
{
    private const STAGE_LABELS = [
        'rejected' => 'Ditolak',
        'approved' => 'Disetujui',
        'submitted' => 'Diajukan ulang',
    ];

    /** What `row()` reads, to eager load wherever requests are listed or opened. */
    public const RELATIONS = [
        'employee:id,full_name,employee_number,branch_id,department_id,user_id,company_id',
        'employee.branch:id,name',
        'employee.department:id,name',
        'workflow.steps.decidedBy:id,name',
        'workflow.comments.author:id,name',
        'attachments',
        'type',
    ];

    /**
     * One request as the queue and the decision panel show it. The Pengajuan page lists these, and the dashboard opens
     * the same panel over itself for one of them, so both read it from here.
     *
     * @return array<string, mixed>
     */
    public static function row(LeaveRequest $leave, User $user): array
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
            'typeIcon' => $leave->type?->icon?->value,
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
            ...self::extras($leave, $user),
        ];
    }

    /**
     * @return array{
     *     attachments: array<int, array{id: int, name: string, sizeBytes: int, downloadUrl: string, previewUrl: string|null}>,
     *     history: array<int, array{author: string|null, stage: string|null, stageLabel: string, note: string, at: string|null}>,
     *     canResubmit: bool
     * }
     */
    public static function extras(LeaveRequest $leave, User $user): array
    {
        return [
            'attachments' => $leave->attachments->map(fn (LeaveRequestAttachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'sizeBytes' => $attachment->size_bytes,
                'downloadUrl' => route('leave-attachments.download', $attachment),
                'previewUrl' => InlineImage::previewUrl(route('leave-attachments.download', $attachment), $attachment->original_name),
            ])->values()->all(),
            'history' => ($leave->workflow->comments ?? collect())->map(fn (WorkflowComment $comment): array => [
                'author' => $comment->author?->name,
                'stage' => $comment->stage,
                'stageLabel' => self::STAGE_LABELS[$comment->stage] ?? (string) $comment->stage,
                'note' => $comment->body,
                'at' => $comment->created_at?->toDateString(),
            ])->values()->all(),
            'canResubmit' => $user->can('update', $leave),
        ];
    }
}
