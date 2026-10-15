<?php

namespace App\Support\Offboarding;

use App\Enums\OffboardingCaseStage;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseFile;
use App\Models\User;
use App\Models\WorkflowStep;

/**
 * An offboarding case as the pages show it: a row for the list, and the whole case for its pipeline.
 */
class OffboardingPresenter
{
    /**
     * The three tabs of the pipeline. Draft and Closed are states, not tabs (OffboardingCaseStage).
     */
    private const TABS = [
        OffboardingCaseStage::Submitted,
        OffboardingCaseStage::Checklist,
        OffboardingCaseStage::SignOff,
    ];

    /**
     * @return array<string, mixed>
     */
    public function row(OffboardingCase $case): array
    {
        $stage = $case->stage();

        return [
            'id' => $case->id,
            'reference' => $case->reference(),
            'employee' => [
                'id' => $case->employee_id,
                'name' => $case->employee?->full_name,
                'number' => $case->employee?->employee_number,
                'position' => $case->employee?->position?->name,
                'department' => $case->employee?->department?->name,
            ],
            'exitType' => $case->exit_type?->label(),
            'lastWorkingOn' => $case->last_working_on?->toDateString(),
            'stage' => $stage->value,
            'stageLabel' => $stage->label(),
            'step' => $this->stepNumber($stage),
            'statusLabel' => $this->statusLabel($case, $stage),
            'isDraft' => $stage === OffboardingCaseStage::Draft,
            'createdBy' => $case->creator?->name,
            'daysOpen' => $stage === OffboardingCaseStage::Closed || $case->created_at === null
                ? null
                : (int) $case->created_at->diffInDays(now()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(OffboardingCase $case, User $viewer): array
    {
        $case->loadMissing(['employee.position', 'employee.department', 'employee.manager', 'creator:id,name', 'files', 'workflow.steps.decidedBy:id,name']);
        $stage = $case->stage();
        $employee = $case->employee;

        return [
            ...$this->row($case),
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'number' => $employee->employee_number,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
                'manager' => $employee->manager?->full_name,
                'joinedOn' => $employee->joined_on?->toDateString(),
                'photoUrl' => $employee->photoUrl(),
            ],
            'exitTypeValue' => $case->exit_type?->value,
            'noticeOn' => $case->notice_on?->toDateString(),
            'reason' => $case->reason,
            'compensationMonths' => $case->compensation_months,
            'compensationReason' => $case->compensation_reason,
            'noticeCheck' => $case->noticeCheck($employee),
            'submittedAt' => $case->submitted_at?->toDateTimeString(),
            'tabs' => array_map(fn (OffboardingCaseStage $tab): array => [
                'stage' => $tab->value,
                'label' => $tab->label(),
                'state' => $this->tabState($stage, $tab),
            ], self::TABS),
            'files' => $case->files->map(fn (OffboardingCaseFile $file): array => [
                'id' => $file->id,
                'kind' => $file->kind->value,
                'kindLabel' => $file->kind->label(),
                'name' => $file->original_name,
                'sizeBytes' => $file->size_bytes,
                'uploadedAt' => $file->created_at?->toDateString(),
                'downloadUrl' => route('offboarding.files.download', $file, absolute: false),
            ])->values()->all(),
            'chain' => $case->workflow?->steps->map(fn (WorkflowStep $step): array => [
                'label' => $step->label,
                'status' => $step->status->value,
                'statusLabel' => $step->status->label(),
                'decidedBy' => $step->decidedBy?->name,
                'decidedAt' => $step->decided_at?->toDateString(),
                'note' => $step->note,
                'waitingDays' => $step->status->value === 'pending' && $case->submitted_at !== null
                    ? (int) $case->submitted_at->diffInDays(now())
                    : null,
            ])->values()->all() ?? [],
            'canEdit' => $viewer->can('update', $case),
            'canDecide' => $viewer->can('decide', $case),
            'canComment' => $viewer->can('comment', $case),
            'canAddFiles' => $viewer->can('addFiles', $case),
        ];
    }

    private function stepNumber(OffboardingCaseStage $stage): int
    {
        return match ($stage) {
            OffboardingCaseStage::Draft => 0,
            OffboardingCaseStage::Submitted => 1,
            OffboardingCaseStage::Checklist => 2,
            OffboardingCaseStage::SignOff, OffboardingCaseStage::Closed => 3,
        };
    }

    private function statusLabel(OffboardingCase $case, OffboardingCaseStage $stage): string
    {
        return match ($stage) {
            OffboardingCaseStage::Draft => 'Draft',
            OffboardingCaseStage::Submitted => 'Menunggu persetujuan Manajemen',
            OffboardingCaseStage::Checklist => 'Checklist berjalan',
            OffboardingCaseStage::SignOff => 'Menunggu sign-off',
            OffboardingCaseStage::Closed => 'Ditutup',
        };
    }

    /**
     * 'done', 'current' or 'next' for a pipeline tab.
     */
    private function tabState(OffboardingCaseStage $stage, OffboardingCaseStage $tab): string
    {
        $order = [OffboardingCaseStage::Draft, OffboardingCaseStage::Submitted, OffboardingCaseStage::Checklist, OffboardingCaseStage::SignOff, OffboardingCaseStage::Closed];
        $now = array_search($stage, $order, true);
        $at = array_search($tab, $order, true);

        return match (true) {
            $now > $at => 'done',
            $now === $at => 'current',
            default => 'next',
        };
    }
}
