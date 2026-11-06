<?php

namespace App\Support\Recruitment;

use App\Enums\CandidateSource;
use App\Enums\JobPlatform;
use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Http\Requests\SaveRequisitionRequest;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Support\Files\InlineImage;
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
            // Nobody is hired yet: offers come with stage 05. Candidates are the shortlist, from stage 03 on.
            'filled' => 0,
            'candidates' => $this->reachedScreening($stage) ? $requisition->candidates->count() : null,
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
        $requisition->loadMissing(['department', 'branch', 'manager:id,full_name', 'creator:id,name', 'workflow.steps.decidedBy:id,name', 'workflow.steps.approver:id,name', 'workflow.transitions.actor:id,name', 'postings.postedBy:id,name']);
        $stage = $requisition->stage();
        $cancelled = $stage === RequisitionStage::Cancelled
            ? $requisition->workflow?->transitions->filter(fn (WorkflowTransition $transition): bool => $transition->to_stage === RequisitionStage::Cancelled->value)->last()
            : null;
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
            'postings' => $requisition->postings->map(fn (RequisitionPosting $posting): array => [
                'id' => $posting->id,
                'platform' => $posting->platform->value,
                'customName' => $posting->platform_name,
                'label' => $posting->platformLabel(),
                'url' => $posting->url,
                'postedOn' => $posting->posted_on->toDateString(),
                'postedBy' => $posting->postedBy?->name,
            ])->values()->all(),
            'applicantsCount' => $requisition->applicants_count,
            'shortlistedCount' => $requisition->candidates->count(),
            // Personal data: only HR sees who was shortlisted; everyone else sees the counts.
            'shortlist' => $viewer->can('viewCandidates', $requisition) ? $this->candidates($requisition) : null,
            'sourceOptions' => array_map(fn (CandidateSource $source): array => ['value' => $source->value, 'label' => $source->label()], CandidateSource::cases()),
            'interviewBlockers' => $stage === RequisitionStage::Screening
                ? $this->engine->blockingReasons($requisition->workflow, RequisitionStage::Interview)
                : [],
            'canManageScreening' => $viewer->can('manageScreening', $requisition),
            'canAdvanceToInterview' => $viewer->can('advanceToInterview', $requisition),
            'canManageInterview' => $viewer->can('manageInterview', $requisition),
            // The company's today, which the posting date may not pass.
            'today' => SaveRequisitionRequest::today()->toDateString(),
            'platformOptions' => array_map(fn (JobPlatform $platform): array => ['value' => $platform->value, 'label' => $platform->label()], JobPlatform::cases()),
            'screeningBlockers' => $stage === RequisitionStage::Posting
                ? $this->engine->blockingReasons($requisition->workflow, RequisitionStage::Screening)
                : [],
            'cancelledReason' => $requisition->cancel_reason,
            'cancelledAt' => $cancelled?->created_at->toDateString(),
            'cancelledBy' => $cancelled?->actor?->name,
            'canEdit' => $viewer->can('update', $requisition),
            'canDelete' => $viewer->can('delete', $requisition),
            'canDecide' => $viewer->can('decide', $requisition),
            'canSaveSalary' => $viewer->can('saveSalary', $requisition),
            'canAdvance' => $viewer->can('advance', $requisition),
            'canManagePostings' => $viewer->can('managePostings', $requisition),
            'canCancel' => $viewer->can('cancel', $requisition),
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

        return array_map(function (RequisitionStage $tab, int $index) use ($now, $stage, $transitions): array {
            $reached = $transitions->filter(fn (WorkflowTransition $transition): bool => $transition->to_stage === $tab->value)->last();

            return [
                'stage' => $tab->value,
                'label' => $tab->label(),
                'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                // A cancelled requisition sits on no tab: those it reached stay open to look at.
                'state' => match (true) {
                    $now === false => $reached !== null && $stage === RequisitionStage::Cancelled ? 'done' : 'locked',
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
     * The shortlist, oldest first, with where each CV can be opened.
     *
     * @return list<array<string, mixed>>
     */
    private function candidates(Requisition $requisition): array
    {
        $requisition->loadMissing('candidates.addedBy:id,name');

        return array_values($requisition->candidates->map(fn (Candidate $candidate): array => $this->candidate($requisition, $candidate))->all());
    }

    /**
     * One shortlisted candidate: who they are, their CV, where the interview stands and what they answered. The shortlist
     * and the candidate's own page are drawn from the same array, so they cannot disagree.
     *
     * @return array<string, mixed>
     */
    public function candidate(Requisition $requisition, Candidate $candidate): array
    {
        $candidate->loadMissing('addedBy:id,name');
        $cvUrl = $candidate->cv_path === null ? null : route('recruitment.candidates.cv', [$requisition, $candidate]);

        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'phone' => $candidate->phone,
            'email' => $candidate->email,
            'source' => $candidate->source->value,
            'sourceLabel' => $candidate->sourceLabel(),
            'note' => $candidate->note,
            'addedOn' => $candidate->created_at?->toDateString(),
            'addedBy' => $candidate->addedBy?->name,
            'cvName' => $candidate->cv_name,
            'cvSize' => $candidate->cv_size,
            'cvUrl' => $cvUrl,
            'cvPreviewUrl' => $cvUrl === null ? null : InlineImage::previewUrl($cvUrl, (string) $candidate->cv_name),
            ...$this->interviewFields($candidate),
        ];
    }

    /**
     * Where a candidate stands on the interview: invited or not, when and where, and what they answered on the form.
     *
     * @return array<string, mixed>
     */
    private function interviewFields(Candidate $candidate): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $when = $candidate->interview_at?->copy()->setTimezone($timezone);
        $form = $candidate->form_data;
        $answers = is_array($form['answers'] ?? null) ? $form['answers'] : [];

        return [
            'inviteState' => $candidate->inviteState(),
            'invitedOn' => $candidate->invited_at?->copy()->setTimezone($timezone)->toDateString(),
            'inviteExpiresOn' => $candidate->invite_expires_at?->copy()->setTimezone($timezone)->toDateString(),
            'interviewAtLabel' => $when === null ? null : InvitationMessage::whenLabel($when),
            'interviewAtInput' => $when?->format('Y-m-d\TH:i'),
            'interviewMode' => $candidate->interview_mode,
            'interviewPlace' => $candidate->interview_place,
            'invitation' => $candidate->invitation_message === null ? null : [
                'subject' => $candidate->invitation_subject,
                'message' => $candidate->invitation_message,
                'emailed' => $candidate->invitation_emailed,
                'sentOn' => $candidate->invited_at?->copy()->setTimezone($timezone)->toDateString(),
            ],
            'formSubmittedOn' => $candidate->form_submitted_at?->copy()->setTimezone($timezone)->toDateString(),
            'form' => $candidate->form_submitted_at === null ? null : [
                'answers' => $answers,
                'yes' => count(array_filter($answers, fn (mixed $answer): bool => is_array($answer) && ($answer['answer'] ?? false) === true)),
                'expectedSalary' => $form['expected_salary'] ?? null,
                'availableFrom' => $form['available_from'] ?? null,
                'message' => $form['message'] ?? null,
            ],
        ];
    }

    private function reachedScreening(RequisitionStage $stage): bool
    {
        return in_array($stage, [RequisitionStage::Screening, RequisitionStage::Interview, RequisitionStage::Offer, RequisitionStage::Filled, RequisitionStage::Cancelled], true);
    }

    /**
     * Where posting stands: not posted anywhere yet, or the platforms it is on.
     */
    private function postingStatus(Requisition $requisition): string
    {
        $platforms = $requisition->postings->map(fn (RequisitionPosting $posting): string => $posting->platformLabel())->unique();

        return $platforms->isEmpty() ? 'Belum diposting' : 'Diposting di '.$platforms->implode(', ');
    }

    /**
     * The one-line status beside the stage name (REC-PIP-006): where in the stage the requisition actually is.
     */
    private function statusLabel(Requisition $requisition, RequisitionStage $stage): string
    {
        if ($stage !== RequisitionStage::Submitted) {
            return match ($stage) {
                RequisitionStage::Draft => $requisition->submitted_at === null ? 'Draft' : 'Dikembalikan ke pemohon',
                RequisitionStage::Posting => $this->postingStatus($requisition),
                RequisitionStage::Screening => ($requisition->applicants_count === null ? '—' : $requisition->applicants_count).' pelamar · '.$requisition->candidates->count().' shortlist',
                default => $stage->label(),
            };
        }

        $open = $requisition->workflow?->steps->firstWhere('status', 'pending');

        return $open instanceof WorkflowStep
            ? "Menunggu persetujuan {$open->label}"
            : 'Disetujui · siap lanjut ke Posting';
    }
}
