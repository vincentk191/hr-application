<?php

namespace App\Support\Recruitment;

use App\Actions\Recruitment\MarkRequisitionFilled;
use App\Enums\CandidateSource;
use App\Enums\EmploymentStatus;
use App\Enums\EvaluationRecommendation;
use App\Enums\JobPlatform;
use App\Enums\OfferStage;
use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Http\Requests\SaveRequisitionRequest;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\CandidateOffer;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Support\Files\InlineImage;
use App\Workflows\WorkflowEngine;
use Closure;

/**
 * A requisition as the pages show it: a row for the dashboard (REC-DSH-002) and the whole requisition for its
 * pipeline (REC-PIP).
 */
class RequisitionPresenter
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @param  int  $unreadComments  comments on the requisition's thread that are new to the person looking
     * @return array<string, mixed>
     */
    public function row(Requisition $requisition, int $unreadComments = 0): array
    {
        $stage = $requisition->stage();

        return [
            'id' => $requisition->id,
            'reference' => $requisition->reference(),
            'positionName' => $requisition->position_name,
            'department' => $requisition->department?->name,
            'branch' => $requisition->branch?->name,
            'quota' => $requisition->quota,
            // The accepted offers (REC-FIL-001). Candidates are the shortlist, from stage 03 on.
            'filled' => $this->filledOf($requisition),
            'candidates' => $this->reachedScreening($stage) ? $requisition->candidates->count() : null,
            'stage' => $stage->value,
            'stageLabel' => $stage->label(),
            'step' => $this->stepNumber($stage),
            'statusLabel' => $this->statusLabel($requisition, $stage),
            'isDraft' => $stage === RequisitionStage::Draft,
            'isUrgent' => $requisition->is_urgent,
            'urgentReason' => $requisition->is_urgent ? $requisition->urgent_reason : null,
            'createdBy' => $requisition->creator?->name,
            'unreadComments' => $unreadComments,
            // The date the latest stage was reached; a draft has none ("Belum diajukan", REC-DSH-004).
            'lastUpdate' => $stage === RequisitionStage::Draft ? null : $requisition->workflow?->stage_entered_at->toDateString(),
        ];
    }

    /**
     * How many candidates have accepted: counted from the offers already loaded, so a list of requisitions costs no query
     * per row.
     */
    private function filledOf(Requisition $requisition): int
    {
        $requisition->loadMissing('offers.workflow');

        return $requisition->offers->filter(fn (CandidateOffer $offer): bool => $offer->stage() === OfferStage::Accepted)->count();
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
                'takenDownOn' => $posting->taken_down_at?->copy()->setTimezone((string) config('attendance.device_timezone'))->toDateString(),
            ])->values()->all(),
            'applicantsCount' => $requisition->applicants_count,
            'shortlistedCount' => $requisition->candidates->count(),
            // Personal data: only HR sees who was shortlisted; everyone else sees the counts.
            'shortlist' => $this->shortlist($requisition, $viewer),
            'sourceOptions' => array_map(fn (CandidateSource $source): array => ['value' => $source->value, 'label' => $source->label()], CandidateSource::cases()),
            'interviewBlockers' => $stage === RequisitionStage::Screening
                ? $this->engine->blockingReasons($requisition->workflow, RequisitionStage::Interview)
                : [],
            'canManageScreening' => $viewer->can('manageScreening', $requisition),
            'canAdvanceToInterview' => $viewer->can('advanceToInterview', $requisition),
            'canAdvanceToOffer' => $viewer->can('advanceToOffer', $requisition),
            'offerBlockers' => $stage === RequisitionStage::Interview
                ? $this->engine->blockingReasons($requisition->workflow, RequisitionStage::Offer)
                : [],
            // How many the direct superior has chosen: a count anyone who sees the requisition may see.
            'selectedCount' => $requisition->candidates->filter(fn (Candidate $candidate): bool => $candidate->isSelected())->count(),
            'canManageInterview' => $viewer->can('manageInterview', $requisition),
            // Who the invitation's "Peserta" list offers: HR and Manajemen of the company, for those who may invite.
            'interviewerOptions' => $viewer->can('manageInterview', $requisition) ? app(InterviewParticipants::class)->options($requisition) : [],
            'canProposeOffers' => $viewer->can('proposeOffers', $requisition),
            // The offers to chosen candidates: HR, the direct superior and Management only (REC-OFS).
            'offers' => $this->offers($requisition, $viewer),
            // "Tandai Selesai" (REC-FIL-001): open to HR at Penawaran, shut by the stage's guard until the quota is filled.
            'canMarkFilled' => $viewer->can('markFilled', $requisition),
            'filledBlockers' => $stage === RequisitionStage::Offer
                ? $this->engine->blockingReasons($requisition->workflow, RequisitionStage::Filled)
                : [],
            // Interviewed candidates who could be told by email that the position is filled.
            'emailableOthers' => $viewer->can('markFilled', $requisition) ? app(MarkRequisitionFilled::class)->emailable($requisition) : 0,
            'canCloseFilled' => $viewer->can('closeFilled', $requisition),
            'canChangeSuperior' => $viewer->can('changeSuperior', $requisition),
            'superiorChoices' => $viewer->can('changeSuperior', $requisition) ? $this->superiorChoices($requisition) : [],
            // Who is out when, for placing interviews: the Interview tab's calendar, for those who see the candidates.
            'availability' => in_array($stage, [RequisitionStage::Interview, RequisitionStage::Offer], true)
                && ($viewer->can('viewCandidates', $requisition) || $viewer->can('viewInterviewees', $requisition))
                ? app(InterviewAvailability::class)->for($requisition, $viewer)
                : null,
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
    private function candidates(Requisition $requisition, User $viewer, ?Closure $only = null): array
    {
        $requisition->loadMissing('candidates.addedBy:id,name', 'candidates.evaluations.author:id,name', 'candidates.rejectedBy:id,name', 'candidates.selectedBy:id,name', 'candidates.interviewers:id,name');

        return array_values($requisition->candidates
            ->filter(fn (Candidate $candidate): bool => $only === null || $only($candidate))
            ->map(fn (Candidate $candidate): array => $only !== null
                ? [...$this->candidate($requisition, $candidate, $viewer), 'phone' => null, 'email' => null, 'note' => null]
                : $this->candidate($requisition, $candidate, $viewer))
            ->all());
    }

    /**
     * The shortlist as this person may see it: all of it for HR; for the new hire's direct superior only those invited
     * to interview, and for a Manajemen user only those they were chosen to sit in on, without phone, email or note;
     * nothing for anyone else.
     *
     * @return list<array<string, mixed>>|null
     */
    private function shortlist(Requisition $requisition, User $viewer): ?array
    {
        return match (true) {
            $viewer->can('viewCandidates', $requisition) => $this->candidates($requisition, $viewer),
            $viewer->can('viewInterviewees', $requisition) => $this->candidates($requisition, $viewer, fn (Candidate $candidate): bool => $candidate->inviteState() !== 'none'),
            $viewer->can('sitsInOnAny', $requisition) => $this->candidates($requisition, $viewer, fn (Candidate $candidate): bool => $viewer->can('sitsIn', [$requisition, $candidate])),
            default => null,
        };
    }

    /**
     * One shortlisted candidate: who they are, their CV, where the interview stands and what they answered. The shortlist
     * and the candidate's own page are drawn from the same array, so they cannot disagree.
     *
     * @return array<string, mixed>
     */
    public function candidate(Requisition $requisition, Candidate $candidate, ?User $viewer = null): array
    {
        $candidate->loadMissing('addedBy:id,name', 'evaluations.author:id,name', 'rejectedBy:id,name', 'selectedBy:id,name', 'interviewers:id,name');
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
            // Who HR chose to sit in on the interview, besides the direct superior; the ids prefill a re-invite.
            'interviewers' => $candidate->interviewers->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->values()->all(),
            'canExportEvent' => $viewer !== null && $viewer->can('exportInterviewEvent', [$requisition, $candidate]),
            ...$this->evaluationFields($requisition, $candidate, $viewer),
            'canRenewFormLink' => $viewer !== null && $viewer->can('renewFormLink', [$requisition, $candidate]),
            // What HR wrote to the candidate in the invitation: HR's own, kept so a re-send repeats it.
            'invitationNote' => $viewer !== null && $viewer->can('viewCandidates', $requisition) ? $candidate->invitation_note : null,
            ...$this->rejectionFields($requisition, $candidate, $viewer),
            ...$this->selectionFields($requisition, $candidate, $viewer),
        ];
    }

    /**
     * What the interviewers wrote after the interview, and whether this person may add theirs.
     *
     * @return array<string, mixed>
     */
    private function evaluationFields(Requisition $requisition, Candidate $candidate, ?User $viewer): array
    {
        $timezone = (string) config('attendance.device_timezone');

        return [
            'evaluations' => $candidate->evaluations->map(fn (CandidateEvaluation $evaluation): array => [
                'id' => $evaluation->id,
                'author' => $evaluation->author?->name,
                'rating' => $evaluation->rating,
                'recommendation' => $evaluation->recommendation->value,
                'recommendationLabel' => $evaluation->recommendation->label(),
                'note' => $evaluation->note,
                'writtenOn' => $evaluation->updated_at?->copy()->setTimezone($timezone)->toDateString(),
                'isMine' => $viewer !== null && $evaluation->user_id === $viewer->id,
            ])->values()->all(),
            'canEvaluate' => $viewer !== null && $viewer->can('evaluate', [$requisition, $candidate]),
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
            'interviewMinutes' => $candidate->interview_minutes ?: InterviewCalendar::DEFAULT_MINUTES,
            'invitation' => $candidate->invitation_message === null ? null : [
                'subject' => $candidate->invitation_subject,
                'message' => $candidate->invitation_message,
                'emailed' => $candidate->invitation_emailed,
                'sentOn' => $candidate->invited_at?->copy()->setTimezone($timezone)->toDateString(),
                'sentAt' => $candidate->invited_at?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
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

    /**
     * Whether a candidate was turned down (REC-CAN-003). Everyone who sees the candidate sees that; the reason and who gave it
     * are HR's.
     *
     * @return array<string, mixed>
     */
    private function rejectionFields(Requisition $requisition, Candidate $candidate, ?User $viewer): array
    {
        $isHr = $viewer !== null && $viewer->can('viewCandidates', $requisition);
        $timezone = (string) config('attendance.device_timezone');

        return [
            'isRejected' => $candidate->isRejected(),
            'rejectedOn' => $candidate->rejected_at?->copy()->setTimezone($timezone)->toDateString(),
            'rejectedReason' => $isHr ? $candidate->rejected_reason : null,
            'rejectedBy' => $isHr ? $candidate->rejectedBy?->name : null,
            'canReject' => $viewer !== null && $viewer->can('rejectCandidate', [$requisition, $candidate]),
            'canRestore' => $viewer !== null && $viewer->can('restoreCandidate', [$requisition, $candidate]),
            'canRemove' => $viewer !== null && $viewer->can('removeCandidate', [$requisition, $candidate]),
        ];
    }

    /**
     * Whether the direct superior chose this candidate (REC-PIP), and whether this person may choose or take the choice back.
     *
     * @return array<string, mixed>
     */
    private function selectionFields(Requisition $requisition, Candidate $candidate, ?User $viewer): array
    {
        return [
            'isSelected' => $candidate->isSelected(),
            'selectedOn' => $candidate->isSelected() ? $candidate->selected_at?->copy()->setTimezone((string) config('attendance.device_timezone'))->toDateString() : null,
            'selectedBy' => $candidate->isSelected() ? $candidate->selectedBy?->name : null,
            'canSelect' => $viewer !== null && $viewer->can('selectCandidate', [$requisition, $candidate]),
            'canUnselect' => $viewer !== null && $viewer->can('unselectCandidate', [$requisition, $candidate]),
        ];
    }

    /**
     * The offers of a requisition for those who may see them, oldest first; null for everyone else.
     *
     * @return list<array<string, mixed>>|null
     */
    private function offers(Requisition $requisition, User $viewer): ?array
    {
        if (! $viewer->can('viewOffers', $requisition)) {
            return null;
        }

        $requisition->loadMissing('offers.workflow.steps.decidedBy:id,name', 'offers.candidate.evaluations.author:id,name', 'offers.proposedBy:id,name', 'offers.sentBy:id,name');

        return array_values($requisition->offers->map(fn (CandidateOffer $offer): array => $this->offer($requisition, $offer, $viewer))->all());
    }

    /**
     * One offer: who it is for, where it stands, what Management decided, and the candidate as this person may see them.
     * Only HR sees the candidate's phone, email and note.
     *
     * @return array<string, mixed>
     */
    private function offer(Requisition $requisition, CandidateOffer $offer, User $viewer): array
    {
        $candidate = $offer->candidate;
        $stage = $offer->stage();
        $step = $offer->workflow?->steps->first();
        $timezone = (string) config('attendance.device_timezone');
        $evaluations = $candidate->evaluations;
        $data = $this->candidate($requisition, $candidate, $viewer);

        if (! $viewer->can('viewCandidates', $requisition)) {
            $data = [...$data, 'phone' => null, 'email' => null, 'note' => null];
        }

        return [
            'id' => $offer->id,
            'candidateId' => $offer->candidate_id,
            'name' => $candidate->name,
            'stage' => $stage->value,
            'stageLabel' => $stage->label(),
            'isLive' => $offer->isLive(),
            'salary' => $offer->salary,
            'proposedOn' => $offer->proposed_at?->copy()->setTimezone($timezone)->toDateString(),
            'proposedBy' => $offer->proposedBy?->name,
            // "Direkomendasikan oleh n pewawancara" (REC-OFS-001).
            'recommendedBy' => $evaluations->filter(fn (CandidateEvaluation $evaluation): bool => $evaluation->recommendation === EvaluationRecommendation::Continue)->count(),
            'evaluators' => $evaluations->count(),
            'decidedBy' => $step?->status->isDecided() ? $step->decidedBy?->name : null,
            'decidedOn' => $step?->status->isDecided() ? $step->decided_at?->copy()->setTimezone($timezone)->toDateString() : null,
            'decisionNote' => $step?->status->isDecided() ? $step->note : null,
            'startOn' => $offer->start_on?->toDateString(),
            'probationMonths' => $offer->probation_months,
            'sentOn' => $offer->sent_at?->copy()->setTimezone($timezone)->toDateString(),
            'sentBy' => $offer->sentBy?->name,
            'emailed' => $offer->emailed_at !== null,
            'respondedOn' => $offer->responded_at?->copy()->setTimezone($timezone)->toDateString(),
            // Why the candidate said no: HR's, like their phone and email.
            'declineReason' => $viewer->can('viewCandidates', $requisition) ? $offer->decline_reason : null,
            'canRespond' => $viewer->can('respondToOffer', [$requisition, $offer]),
            'canDecide' => $viewer->can('decideOffer', [$requisition, $offer]),
            'canWithdraw' => $viewer->can('withdrawOffer', [$requisition, $offer]),
            'canSetTerms' => $viewer->can('setOfferTerms', [$requisition, $offer]),
            'canSend' => $viewer->can('sendOffer', [$requisition, $offer]),
            'canViewLetter' => $viewer->can('viewOfferLetter', [$requisition, $offer]),
            'candidate' => $data,
        ];
    }

    /**
     * What the offer letter is built from (REC-OFR): the company, the candidate, the role and the terms, as the letter reads
     * them. The letter is dated the day it was sent, or today while it is still a draft.
     *
     * @return array<string, mixed>
     */
    public function offerLetter(Requisition $requisition, CandidateOffer $offer): array
    {
        $requisition->loadMissing('company', 'department', 'branch', 'manager');
        $offer->loadMissing('candidate', 'sentBy:id,name');

        return [
            'company' => $requisition->company->name,
            'date' => InvitationMessage::dayLabel($offer->sent_at ?? now(), false),
            'candidate' => $offer->candidate->name,
            'position' => $requisition->position_name,
            'department' => $requisition->department?->name,
            'branch' => $requisition->branch?->name,
            'manager' => $requisition->manager?->full_name,
            'contract' => $requisition->contractLabel(),
            'startOn' => $offer->start_on === null ? null : InvitationMessage::dayLabel($offer->start_on, false),
            'probation' => $offer->probation_months === null ? null : InvitationMessage::probationLabel($offer->probation_months),
            'salary' => $offer->salary === null ? null : InvitationMessage::rupiah($offer->salary),
            'sent' => $offer->sent_at !== null,
        ];
    }

    /**
     * Who HR may name as the direct superior: active employees of the requisition's company who have an active account (a
     * superior without one could never choose), other than the one already named.
     *
     * @return list<array{id: int, name: string, number: string|null}>
     */
    private function superiorChoices(Requisition $requisition): array
    {
        return array_values(Employee::acrossCompanies()
            ->where('company_id', $requisition->company_id)
            ->where('employment_status', EmploymentStatus::Active)
            ->whereKeyNot($requisition->manager_employee_id ?? 0)
            ->whereHas('user', fn ($user) => $user->where('is_active', true))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->full_name, 'number' => $employee->employee_number])
            ->all());
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
                RequisitionStage::Offer => $this->filledOf($requisition).' dari '.($requisition->quota ?? '—').' diterima',
                default => $stage->label(),
            };
        }

        $open = $requisition->workflow?->steps->firstWhere('status', 'pending');

        return $open instanceof WorkflowStep
            ? "Menunggu persetujuan {$open->label}"
            : 'Disetujui · siap lanjut ke Posting';
    }
}
