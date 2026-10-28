<?php

namespace App\Support\Recruitment;

use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Workflows\WorkflowEngine;

/**
 * A requisition as the pages show it: a row for the dashboard (REC-DSH-002) and the whole requisition for its
 * pipeline (REC-PIP).
 */
class RequisitionPresenter
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @return array<string, mixed>
     */
    public function row(Requisition $requisition): array
    {
        $stage = $requisition->stage();

        return [
            'id' => $requisition->id,
            'reference' => $requisition->reference(),
            'positionName' => $requisition->position_name,
            'department' => $requisition->department?->name,
            'branch' => $requisition->branch?->name,
            'quota' => $requisition->quota,
            // Nobody is hired yet: candidates arrive with stage 03, offers with stage 05.
            'filled' => 0,
            'candidates' => null,
            'stage' => $stage->value,
            'stageLabel' => $stage->label(),
            'step' => $this->stepNumber($stage),
            'statusLabel' => $this->statusLabel($requisition, $stage),
            'isDraft' => $stage === RequisitionStage::Draft,
            'createdBy' => $requisition->creator?->name,
            // The date the latest stage was reached; a draft has none ("Belum diajukan", REC-DSH-004).
            'lastUpdate' => $stage === RequisitionStage::Draft ? null : $requisition->workflow?->stage_entered_at->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Requisition $requisition, User $viewer): array
    {
        $requisition->loadMissing(['department', 'branch', 'manager:id,full_name', 'creator:id,name', 'workflow.steps.decidedBy:id,name', 'workflow.steps.approver:id,name', 'workflow.transitions']);
        $stage = $requisition->stage();
        $open = $stage === RequisitionStage::Submitted ? $requisition->workflow?->steps->firstWhere('status', 'pending') : null;
        $seesSalary = $viewer->can(Permission::ManageRecruitment->value) || $viewer->isA(Role::Management);

        return [
            ...$this->row($requisition),
            'departmentId' => $requisition->department_id,
            'branchId' => $requisition->branch_id,
            'reason' => $requisition->reason,
            'targetStartOn' => $requisition->target_start_on?->toDateString(),
            'employmentType' => $requisition->employment_type?->value,
            'contractMonths' => $requisition->contract_months,
            'contractLabel' => $requisition->contractLabel(),
            'managerId' => $requisition->manager_employee_id,
            'manager' => $requisition->manager?->full_name,
            'about' => $requisition->about,
            'responsibilities' => $requisition->responsibilities ?? [],
            'benefits' => $requisition->benefits ?? [],
            'checklist' => $this->checklist($requisition),
            'draftStep' => $requisition->draft_step,
            'submittedAt' => $requisition->submitted_at?->toDateString(),
            'tabs' => $this->tabs($requisition, $stage),
            'chain' => $requisition->workflow?->steps->map(fn (WorkflowStep $step): array => [
                'label' => $step->label,
                'approver' => $step->approver?->name,
                'status' => $step->status->value,
                'statusLabel' => $step->status->label(),
                'decidedBy' => $step->decidedBy?->name,
                'decidedAt' => $step->decided_at?->toDateString(),
                'note' => $step->note,
                'waitingDays' => $step->status->value === 'pending' && $requisition->submitted_at !== null
                    ? (int) $requisition->submitted_at->diffInDays(now())
                    : null,
            ])->values()->all() ?? [],
            // Shown to the people who decide on pay: Management and HR, not the requester (REC-APR-008).
            'salary' => $seesSalary ? [
                'min' => $requisition->salary_min,
                'max' => $requisition->salary_max,
                'set' => $requisition->hasSalaryExpectation(),
            ] : null,
            'awaitingSalary' => $open instanceof WorkflowStep && $open->label === RequisitionApprovers::MANAGEMENT_LABEL,
            'advanceBlockers' => $stage === RequisitionStage::Submitted
                ? $this->engine->blockingReasons($requisition->workflow, RequisitionStage::Posting)
                : [],
            'canEdit' => $viewer->can('update', $requisition),
            'canDelete' => $viewer->can('delete', $requisition),
            'canDecide' => $viewer->can('decide', $requisition),
            'canSaveSalary' => $viewer->can('saveSalary', $requisition),
            'canAdvance' => $viewer->can('advance', $requisition),
            'canComment' => $viewer->can('comment', $requisition),
        ];
    }

    /**
     * The six stages with the date each was reached (REC-PIP-002), and whether it is done, the current one, or
     * locked. A locked stage cannot be opened (REC-PIP-003).
     *
     * @return list<array{stage: string, label: string, number: string, state: string, reachedAt: string|null}>
     */
    private function tabs(Requisition $requisition, RequisitionStage $stage): array
    {
        $pipeline = RequisitionStage::pipeline();
        $now = array_search($stage, $pipeline, true);
        $transitions = $requisition->workflow === null ? collect() : $requisition->workflow->transitions;

        return array_map(function (RequisitionStage $tab, int $index) use ($now, $transitions): array {
            $reached = $transitions->filter(fn (WorkflowTransition $transition): bool => $transition->to_stage === $tab->value)->last();

            return [
                'stage' => $tab->value,
                'label' => $tab->label(),
                'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'state' => match (true) {
                    $now === false => 'locked',
                    $index < $now => 'done',
                    $index === $now => 'current',
                    default => 'locked',
                },
                'reachedAt' => $reached?->created_at?->toDateString(),
            ];
        }, $pipeline, array_keys($pipeline));
    }

    /**
     * The candidate checklist with every fixed subtitle present, so the page never has to guess a missing key.
     *
     * @return array<string, list<string>>
     */
    private function checklist(Requisition $requisition): array
    {
        $saved = $requisition->checklist ?? [];
        $checklist = [];

        foreach (array_keys(RequisitionTemplate::CHECKLIST) as $key) {
            $checklist[$key] = $saved[$key] ?? [];
        }

        return $checklist;
    }

    private function stepNumber(RequisitionStage $stage): int
    {
        $index = array_search($stage, RequisitionStage::pipeline(), true);

        return $index === false ? 0 : $index + 1;
    }

    /**
     * The one-line status beside the stage name (REC-PIP-006): where in the stage the requisition actually is.
     */
    private function statusLabel(Requisition $requisition, RequisitionStage $stage): string
    {
        if ($stage !== RequisitionStage::Submitted) {
            return match ($stage) {
                RequisitionStage::Draft => $requisition->submitted_at === null ? 'Draft' : 'Dikembalikan ke pemohon',
                RequisitionStage::Posting => 'Siap diposting',
                default => $stage->label(),
            };
        }

        $open = $requisition->workflow?->steps->firstWhere('status', 'pending');

        return $open instanceof WorkflowStep
            ? "Menunggu persetujuan {$open->label}"
            : 'Disetujui · siap lanjut ke Posting';
    }
}
