<?php

namespace App\Support\Offboarding;

use App\Actions\Offboarding\RefreshChecklist;
use App\Enums\EmploymentStatus;
use App\Enums\OffboardingCaseStage;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseFile;
use App\Models\OffboardingCaseItem;
use App\Models\OffboardingItem;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

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
        $case->loadMissing(['employee.position', 'employee.department', 'employee.manager', 'creator:id,name', 'files', 'workflow.steps.decidedBy:id,name', 'workflow.steps.approver:id,name']);
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
                'approver' => $step->approver?->name,
                'status' => $step->status->value,
                'statusLabel' => $step->status->label(),
                'decidedBy' => $step->decidedBy?->name,
                'decidedAt' => $step->decided_at?->toDateString(),
                'note' => $step->note,
                'waitingDays' => $step->status->value === 'pending' && $case->submitted_at !== null
                    ? (int) $case->submitted_at->diffInDays(now())
                    : null,
            ])->values()->all() ?? [],
            'checklist' => in_array($stage, [OffboardingCaseStage::Checklist, OffboardingCaseStage::SignOff, OffboardingCaseStage::Closed], true)
                ? $this->checklist($case, $viewer)
                : null,
            'signoff' => in_array($stage, [OffboardingCaseStage::SignOff, OffboardingCaseStage::Closed], true) ? $this->signoff($case, $viewer) : null,
            'impact' => in_array($stage, [OffboardingCaseStage::Checklist, OffboardingCaseStage::SignOff], true) && $viewer->can('reassign', $case)
                ? $this->impact($case)
                : null,
            'exitApplied' => $case->exitApplied(),
            'closedAt' => $case->closed_at?->toDateString(),
            'cancelledAt' => $case->cancelled_at?->toDateString(),
            'cancelReason' => $case->cancel_reason,
            'canChangeLastDay' => $viewer->can('changeLastDay', $case),
            'canCancel' => $viewer->can('cancel', $case),
            'canClose' => $viewer->can('close', $case),
            'canSendBack' => $viewer->can('sendBack', $case),
            'canBeritaAcara' => $viewer->can('beritaAcara', $case),
            'canProceed' => $viewer->can('proceed', $case),
            'canEdit' => $viewer->can('update', $case),
            'canDecide' => $viewer->can('decide', $case),
            'canComment' => $viewer->can('comment', $case),
            'canAddFiles' => $viewer->can('addFiles', $case),
        ];
    }

    /**
     * Stage 3: the exit interview, a plain form with no chain of steps, and what is still in the way of closing.
     *
     * @return array<string, mixed>
     */
    private function signoff(OffboardingCase $case, User $viewer): array
    {
        $open = $case->stage() === OffboardingCaseStage::SignOff;

        return [
            'exitInterview' => [
                'recorded' => $case->exit_interview_at !== null,
                'skipped' => $case->exit_interview_skipped_reason !== null,
                'skippedReason' => $case->exit_interview_skipped_reason,
                'reason' => $case->exit_interview_reason,
                'rehire' => $case->exit_interview_rehire,
                'notes' => $case->exit_interview_notes,
                'at' => $case->exit_interview_at?->toDateString(),
                'canRecord' => $viewer->can('recordExitInterview', $case),
            ],
            'closeBlockers' => $open ? app(WorkflowEngine::class)->blockingReasons($case->workflow, OffboardingCaseStage::Closed) : [],
        ];
    }

    /**
     * One step of the chain shown across the top of a phase, in the shape the approval chain component takes.
     *
     * @return array<string, mixed>
     */
    private function chainStep(string $label, ?string $who, string $status, string $approvedLabel = 'Selesai', ?string $by = null, ?string $at = null): array
    {
        return [
            'label' => $label,
            'approver' => $who,
            'status' => $status,
            'statusLabel' => match ($status) {
                'approved' => $approvedLabel,
                'pending' => 'Menunggu',
                default => 'Terkunci',
            },
            'decidedBy' => $by,
            'decidedAt' => $at,
            'note' => null,
            'waitingDays' => null,
        ];
    }

    /**
     * What HR has to settle before this person can leave: the people who report to them and the departments
     * they head (OFF-SIG-008, and the warning when the case is started).
     *
     * @return array<string, mixed>
     */
    private function impact(OffboardingCase $case): array
    {
        $reports = Employee::query()->where('manager_id', $case->employee_id)->where('employment_status', EmploymentStatus::Active)->orderBy('full_name')->get(['id', 'full_name', 'employee_number']);
        $leaver = Employee::acrossCompanies()->find($case->employee_id);

        return [
            'reports' => $reports->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->full_name, 'number' => $employee->employee_number])->values()->all(),
            'defaultManagerId' => $leaver?->manager_id,
            'options' => Employee::query()->where('employment_status', EmploymentStatus::Active)->whereKeyNot($case->employee_id)->orderBy('full_name')->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'label' => "{$employee->full_name} ({$employee->employee_number})"])->values()->all(),
            'headOf' => Department::query()->where('supervisor_employee_id', $case->employee_id)->pluck('name')->all(),
        ];
    }

    /**
     * The checklist: a group per owner with who they are, their items, and what the viewer may do in it
     * (OFF-CHK-002). For the owner of items in another company, only the groups that are theirs.
     *
     * @return array<string, mixed>
     */
    private function checklist(OffboardingCase $case, User $viewer, bool $ownedOnly = false): array
    {
        $groups = $case->checklistDepartments()->with(['owner:id,name', 'items.doneBy:id,name'])->get()
            ->when($ownedOnly, fn ($all) => $all->filter(fn (OffboardingCaseDepartment $group): bool => $group->owner_user_id === $viewer->id)->values());

        $open = $case->stage() === OffboardingCaseStage::Checklist;
        $master = OffboardingItem::query()->active()->orderBy('sort_order')->get()->keyBy('id');
        $canReassign = ! $ownedOnly && $open && $viewer->can('manage-offboarding');

        $payload = $groups->map(function (OffboardingCaseDepartment $group) use ($viewer, $master, $open, $canReassign): array {
            $items = $group->items;
            $chosen = $items->pluck('offboarding_item_id')->filter()->all();
            $canWork = $viewer->can('workGroup', $group);
            $pending = $items->reject->isDone()->count();
            $ownerGone = $group->owner_user_id !== null && ($group->owner === null || ! User::query()->whereKey($group->owner_user_id)->where('is_active', true)->exists());

            return [
                'id' => $group->id,
                'name' => $group->name,
                'owner' => $group->owner?->name,
                'ownerIsHr' => $group->owner_user_id === null || $ownerGone,
                // Job title and department of the owner as they were when the checklist began.
                'ownerTitle' => collect([$group->owner_title, $group->owner_department])->filter()->implode(' · ') ?: null,
                'ownerId' => $group->owner_user_id,
                'ownerUnavailable' => $ownerGone,
                'status' => match (true) {
                    $group->not_involved => 'not_involved',
                    $group->confirmed_at === null => 'choosing',
                    $pending > 0 => 'running',
                    default => 'done',
                },
                'statusLabel' => match (true) {
                    $group->not_involved => 'Tidak terkait',
                    $group->confirmed_at === null => 'Menunggu daftar',
                    $pending > 0 => 'Berjalan',
                    default => 'Selesai',
                },
                'confirmed' => $group->confirmed_at !== null,
                'notInvolved' => $group->not_involved,
                'done' => $items->filter->isDone()->count(),
                'total' => $items->count(),
                'canWork' => $canWork,
                'canAddItem' => $open && $viewer->can('addItem', $group),
                'canSetAside' => $open && ! $group->confirmed_at && $items->isEmpty() && $viewer->can('setAside', $group),
                'canReassign' => $canReassign,
                'canSelect' => $canWork && ! $group->isSettled(),
                'items' => $items->map(fn (OffboardingCaseItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'kind' => $item->kind->label(),
                    'isAdhoc' => $item->is_adhoc,
                    'done' => $item->isDone(),
                    'doneAt' => $item->done_at?->toDateString(),
                    'doneBy' => $item->doneBy?->name,
                    'note' => $item->note,
                    'canTick' => $open && $group->confirmed_at !== null && $canWork,
                    'canRemove' => $open && ! $item->isDone() && ($item->is_adhoc || ! $group->isSettled()) && $viewer->can('removeItem', $item),
                ])->values()->all(),
                'available' => $canWork && ! $group->isSettled()
                    ? collect($group->item_ids ?? [])
                        ->reject(fn (int $id): bool => in_array($id, $chosen, true))
                        ->map(fn (int $id) => $master->get($id))
                        ->filter()
                        ->map(fn (OffboardingItem $item): array => ['id' => $item->id, 'name' => $item->name, 'kind' => $item->kind->label()])
                        ->values()->all()
                    : [],
            ];
        })->values();

        if ($ownedOnly) {
            $items = $payload->sum('total');
            $done = $payload->sum('done');
            $progress = [
                'items' => $items, 'done' => $done, 'groups' => $payload->count(),
                'settled' => $payload->filter(fn (array $group): bool => $group['confirmed'] || $group['notInvolved'])->count(),
                'ready' => false, 'blockers' => [],
            ];
        } else {
            $progress = app(ChecklistProgress::class)->for($case);
        }

        $moved = $case->stage() !== OffboardingCaseStage::Checklist;
        $ownersDone = $moved || $progress['ready'];
        $approved = $moved || $case->checklist_approved_at !== null;
        $approver = $case->checklist_approved_by_user_id === null ? null : User::query()->whereKey($case->checklist_approved_by_user_id)->value('name');

        return [
            'progress' => $progress,
            // The phase's own chain, two checks before the case may go on: every owner has signed off their part,
            // then HR approves.
            'chain' => $ownedOnly ? [] : [
                $this->chainStep(
                    'Pemilik item sign-off',
                    "{$progress['settled']} dari {$progress['groups']} pemilik · {$progress['done']}/{$progress['items']} item",
                    $ownersDone ? 'approved' : 'pending',
                ),
                $this->chainStep('Persetujuan HR', null, $approved ? 'approved' : ($ownersDone ? 'pending' : 'locked'), 'Disetujui', $approver, $case->checklist_approved_at?->toDateString()),
            ],
            'approved' => $approved,
            'canApprove' => ! $ownedOnly && $open && ! $approved && $progress['ready'] && $viewer->can('approveChecklist', $case),
            'groups' => $payload->all(),
            // Who HR can hand a group to: anyone active with an account, in any company.
            'ownerOptions' => $canReassign ? $this->ownerOptions() : [],
            // Items added to the master list since this checklist opened (OFF-ITM-008).
            'canRefresh' => ! $ownedOnly && $viewer->can('refreshChecklist', $case),
            'newItems' => ! $ownedOnly && $open ? count(app(RefreshChecklist::class)->missingItems($case)) : 0,
        ];
    }

    /**
     * Everyone with a login, for handing a group to someone else: name, job title and company.
     *
     * @return list<array{id: int, label: string}>
     */
    private function ownerOptions(): array
    {
        $options = [];

        $rows = DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('positions', 'positions.id', '=', 'employees.position_id')
            ->leftJoin('companies', 'companies.id', '=', 'employees.company_id')
            ->where('users.is_active', true)
            ->orderBy('employees.full_name')
            ->get(['users.id as user_id', 'employees.full_name', 'positions.name as position', 'companies.code as company']);

        foreach ($rows as $row) {
            $options[] = [
                'id' => (int) $row->user_id,
                'label' => collect([$row->full_name, $row->position, $row->company])->filter()->implode(' · '),
            ];
        }

        return $options;
    }

    /**
     * A case in the list for someone who only owns part of its checklist: no reason, exit type, requester
     * or compensation (OFF-ITM-007). The leaver may belong to another company, so they are read across companies.
     *
     * @return array<string, mixed>
     */
    public function limitedRow(OffboardingCase $case): array
    {
        $leaver = Employee::acrossCompanies()->find($case->employee_id);
        $stage = $case->stage();

        return [
            'id' => $case->id,
            'reference' => $case->reference(),
            'employee' => [
                'id' => $case->employee_id,
                'name' => $leaver?->full_name,
                'number' => $leaver?->employee_number,
                'position' => $leaver?->position_id === null ? null : Position::acrossCompanies()->whereKey($leaver->position_id)->value('name'),
                'department' => $leaver?->department_id === null ? null : Department::acrossCompanies()->whereKey($leaver->department_id)->value('name'),
            ],
            'exitType' => null,
            'lastWorkingOn' => $case->last_working_on?->toDateString(),
            'stage' => $stage->value,
            'stageLabel' => $stage->label(),
            'step' => $this->stepNumber($stage),
            'statusLabel' => $this->statusLabel($case, $stage),
            'isDraft' => false,
            'createdBy' => null,
            'daysOpen' => null,
            'limited' => true,
        ];
    }

    /**
     * What the owner of checklist items in another company sees of a case: who is leaving, from which
     * department, until when, and their own groups (OFF-ITM-007).
     *
     * @return array<string, mixed>
     */
    public function ownerView(OffboardingCase $case, User $viewer): array
    {
        return [
            ...$this->limitedRow($case),
            'checklist' => $this->checklist($case, $viewer, ownedOnly: true),
        ];
    }

    private function stepNumber(OffboardingCaseStage $stage): int
    {
        return match ($stage) {
            OffboardingCaseStage::Draft, OffboardingCaseStage::Cancelled => 0,
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
            OffboardingCaseStage::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * 'done', 'current' or 'next' for a pipeline tab.
     */
    private function tabState(OffboardingCaseStage $stage, OffboardingCaseStage $tab): string
    {
        // A cancelled case never got further than it did: show every tab as not reached.
        if ($stage === OffboardingCaseStage::Cancelled) {
            return 'next';
        }

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
