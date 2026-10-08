<?php

namespace App\Support\Leave;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use App\Models\WorkflowComment;

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

    /**
     * @return array{
     *     attachments: array<int, array{id: int, name: string, sizeBytes: int, downloadUrl: string}>,
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
