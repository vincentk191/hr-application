<?php

namespace App\Policies;

use App\Enums\OfferStage;
use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Recruitment\RequisitionApprovers;
use App\Support\Recruitment\RequisitionDepartments;

/**
 * Who may raise, see, change and decide a requisition (REC-*).
 *
 * A supervisor raises one for their own department, HR for any. Management approves it, then HR. What each
 * person may see is the `visibleTo` scope on the model, so a list and a single requisition never disagree.
 */
class RequisitionPolicy
{
    /**
     * Whether "Rekrutmen" is worth opening: HR, Management, anyone who may raise a requisition, and anyone who
     * raised one before.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageRecruitment->value)
            || $user->isA(Role::Management)
            || app(RequisitionDepartments::class)->for($user)->isNotEmpty()
            || Requisition::query()->where('created_by_user_id', $user->id)->exists()
            || Requisition::query()->visibleTo($user)->exists();
    }

    public function view(User $user, Requisition $requisition): bool
    {
        return $user->canAccessCompany($requisition->company_id)
            && Requisition::query()->visibleTo($user)->whereKey($requisition->id)->exists();
    }

    public function create(User $user): bool
    {
        return app(RequisitionDepartments::class)->for($user)->isNotEmpty();
    }

    /**
     * The requester changes a draft, which is also where a rejected requisition comes back to (REC-WIZ-004). Once
     * sent it can still be corrected until someone has decided on it (REC-WIZ-005); after that a change would go
     * past an approval already given.
     */
    public function update(User $user, Requisition $requisition): bool
    {
        if ($requisition->created_by_user_id !== $user->id || ! $user->canAccessCompany($requisition->company_id)) {
            return false;
        }

        return match ($requisition->stage()) {
            RequisitionStage::Draft => true,
            RequisitionStage::Submitted => ! $requisition->workflow?->steps()->whereIn('status', ['approved', 'rejected'])->exists(),
            default => false,
        };
    }

    /**
     * A draft that was never sent can be thrown away by its requester.
     */
    public function delete(User $user, Requisition $requisition): bool
    {
        return $requisition->created_by_user_id === $user->id && $requisition->isDraft() && $requisition->submitted_at === null;
    }

    /**
     * Deciding the open step: its approver, never the requester, and the HR step only with `manage-recruitment`.
     */
    public function decide(User $user, Requisition $requisition): bool
    {
        $step = $this->openStep($requisition);

        if ($step === null || $requisition->created_by_user_id === $user->id || ! $user->canAccessCompany($requisition->company_id)) {
            return false;
        }

        if ($step->label === RequisitionApprovers::HR_LABEL && ! $user->can(Permission::ManageRecruitment->value)) {
            return false;
        }

        return $step->canBeDecidedBy($user);
    }

    /**
     * Saving the salary expectation without approving yet (REC-APR-007/008): only the Management approver, while
     * their step is the open one.
     */
    public function saveSalary(User $user, Requisition $requisition): bool
    {
        return $this->openStep($requisition)?->label === RequisitionApprovers::MANAGEMENT_LABEL && $this->decide($user, $requisition);
    }

    /**
     * "Lanjut ke Posting" is HR's (REC-APR-003); the guard on the stage keeps it shut until every approval is in.
     */
    public function advance(User $user, Requisition $requisition): bool
    {
        return $requisition->stage() === RequisitionStage::Submitted
            && $user->can(Permission::ManageRecruitment->value)
            && $user->canAccessCompany($requisition->company_id);
    }

    /**
     * Recording where the listing was posted, and moving on to Screening, are HR's while the requisition is at
     * 02 Diposting (REC-PST-002/003).
     */
    public function managePostings(User $user, Requisition $requisition): bool
    {
        return $requisition->stage() === RequisitionStage::Posting
            && $user->can(Permission::ManageRecruitment->value)
            && $user->canAccessCompany($requisition->company_id);
    }

    /**
     * Candidates are personal data: only HR sees who was shortlisted. Everyone else who can see the requisition sees
     * the counts.
     */
    public function viewCandidates(User $user, Requisition $requisition): bool
    {
        return $user->can(Permission::ManageRecruitment->value) && $user->canAccessCompany($requisition->company_id);
    }

    /**
     * Entering the applicant total and shortlisting are HR's at 03 Screening, and stay open at 04 Interview and 05 Offer: screening
     * goes on while people are interviewed, and more candidates are needed if everyone interviewed so far falls through
     * (REC-SCR).
     */
    public function manageScreening(User $user, Requisition $requisition): bool
    {
        return in_array($requisition->stage(), [RequisitionStage::Screening, RequisitionStage::Interview, RequisitionStage::Offer], true)
            && $this->viewCandidates($user, $requisition);
    }

    /**
     * Inviting shortlisted candidates to interview is HR's at 04 Interview, and while an offer is open, where
     * candidates shortlisted late still need inviting (REC-INT).
     */
    public function manageInterview(User $user, Requisition $requisition): bool
    {
        return in_array($requisition->stage(), [RequisitionStage::Interview, RequisitionStage::Offer], true)
            && $this->viewCandidates($user, $requisition);
    }

    /**
     * The new hire's direct superior (Atasan langsung) is an interviewer, and sees the candidates who were invited, and
     * only those, once the requisition reaches Interview. No phone, email or note: they need to prepare, not to contact.
     */
    public function viewInterviewees(User $user, Requisition $requisition): bool
    {
        return in_array($requisition->stage(), [RequisitionStage::Interview, RequisitionStage::Offer], true)
            && $user->canAccessCompany($requisition->company_id)
            && $requisition->manager_employee_id !== null
            && Employee::acrossCompanies()->whereKey($requisition->manager_employee_id)->where('user_id', $user->id)->exists();
    }

    /**
     * Giving feedback after an interview: HR and the direct superior, for a candidate who was invited and whose
     * interview time has passed, while the requisition is at Interview or Offer (REC-INT).
     */
    public function evaluate(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return in_array($requisition->stage(), [RequisitionStage::Interview, RequisitionStage::Offer], true)
            && $candidate->requisition_id === $requisition->id
            && $candidate->interview_at !== null
            && $candidate->interview_at->isPast()
            && ($this->viewCandidates($user, $requisition) || $this->viewInterviewees($user, $requisition));
    }

    /**
     * Naming a different direct superior is HR's, once the requisition is out of approval and until it is filled or called
     * off: before that the requester corrects it in the wizard.
     */
    public function changeSuperior(User $user, Requisition $requisition): bool
    {
        return in_array($requisition->stage(), [RequisitionStage::Posting, RequisitionStage::Screening, RequisitionStage::Interview, RequisitionStage::Offer], true)
            && $user->can(Permission::ManageRecruitment->value)
            && $user->canAccessCompany($requisition->company_id);
    }

    /**
     * Choosing the candidate they prefer is the direct superior's (REC-PIP), for someone invited and already interviewed who
     * is still in the process, at Interview or Offer. HR does not choose for them: the superior is the one who raised the
     * requisition, so they always have an account.
     */
    public function selectCandidate(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return $this->viewInterviewees($user, $requisition)
            && $candidate->requisition_id === $requisition->id
            && ! $candidate->isRejected()
            && $candidate->selected_at === null
            && $candidate->inviteState() !== 'none'
            && $candidate->interview_at !== null
            && $candidate->interview_at->isPast();
    }

    /**
     * Taking the choice back is the direct superior's, for a candidate they chose and who is still in the process, and
     * only while no offer is live for them: once HR has put the candidate to Management it is HR who pulls it back.
     */
    public function unselectCandidate(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return $this->viewInterviewees($user, $requisition)
            && $candidate->requisition_id === $requisition->id
            && $candidate->isSelected()
            && ! $candidate->offers()->live()->exists();
    }

    /**
     * Who sees the offers of a requisition (REC-OFS): HR, the direct superior, and Management. Everyone else sees how many
     * were chosen and nothing more.
     */
    public function viewOffers(User $user, Requisition $requisition): bool
    {
        return $user->canAccessCompany($requisition->company_id)
            && ($this->viewCandidates($user, $requisition) || $this->viewInterviewees($user, $requisition) || $user->isA(Role::Management));
    }

    /**
     * Management sees the candidates who have an offer, and only those: no phone, email or note, but the form they filled
     * in, the CV and the evaluations, which is what an approval rests on.
     */
    public function viewOfferedCandidate(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return $user->isA(Role::Management)
            && $user->canAccessCompany($requisition->company_id)
            && $candidate->requisition_id === $requisition->id
            && $candidate->offers()->exists();
    }

    /**
     * "Ajukan ke Manajemen" is HR's, at 05 Penawaran (REC-OFS-002).
     */
    public function proposeOffers(User $user, Requisition $requisition): bool
    {
        return $requisition->stage() === RequisitionStage::Offer && $this->viewCandidates($user, $requisition);
    }

    /**
     * Deciding an offer is its approver's (REC-OFS-006): the Management step of its own chain, while it is open.
     */
    public function decideOffer(User $user, Requisition $requisition, CandidateOffer $offer): bool
    {
        $step = $offer->workflow?->steps()->where('status', 'pending')->first();

        return $offer->requisition_id === $requisition->id
            && $user->canAccessCompany($requisition->company_id)
            && $step instanceof WorkflowStep
            && $step->canBeDecidedBy($user);
    }

    /**
     * Pulling an offer back is HR's, until it is sent.
     */
    public function withdrawOffer(User $user, Requisition $requisition, CandidateOffer $offer): bool
    {
        return $offer->requisition_id === $requisition->id
            && in_array($offer->stage(), [OfferStage::Proposed, OfferStage::Approved], true)
            && $this->viewCandidates($user, $requisition);
    }

    /**
     * The start date and probation are HR's to fill in once Management has approved, and to change until the offer is sent.
     */
    public function setOfferTerms(User $user, Requisition $requisition, CandidateOffer $offer): bool
    {
        return $offer->requisition_id === $requisition->id
            && $offer->stage() === OfferStage::Approved
            && $this->viewCandidates($user, $requisition);
    }

    /**
     * The letter is HR's to print once the terms are set, before and after it is sent.
     */
    public function viewOfferLetter(User $user, Requisition $requisition, CandidateOffer $offer): bool
    {
        return $offer->requisition_id === $requisition->id
            && in_array($offer->stage(), [OfferStage::Approved, OfferStage::Sent], true)
            && $offer->hasTerms()
            && $this->viewCandidates($user, $requisition);
    }

    /**
     * Marking an offer as sent is HR's, for an approved offer whose terms are complete.
     */
    public function sendOffer(User $user, Requisition $requisition, CandidateOffer $offer): bool
    {
        return $offer->requisition_id === $requisition->id
            && $offer->stage() === OfferStage::Approved
            && $offer->hasTerms()
            && $this->viewCandidates($user, $requisition);
    }

    /**
     * "Lanjut ke Penawaran" is HR's, once, from 04 Interview; the stage's guard keeps it shut until a candidate is chosen.
     */
    public function advanceToOffer(User $user, Requisition $requisition): bool
    {
        return $requisition->stage() === RequisitionStage::Interview && $this->viewCandidates($user, $requisition);
    }

    /**
     * Turning a candidate down is HR's, from Screening to Offer, for someone still in the process (REC-CAN-003).
     */
    public function rejectCandidate(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return $this->manageScreening($user, $requisition)
            && $candidate->requisition_id === $requisition->id
            && ! $candidate->isRejected();
    }

    /**
     * Taking a rejection back is HR's, while the requisition is still at a stage where candidates are handled.
     */
    public function restoreCandidate(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return $this->manageScreening($user, $requisition)
            && $candidate->requisition_id === $requisition->id
            && $candidate->isRejected();
    }

    /**
     * A new link to a candidate's form, for one who asks for it again: HR's, for a candidate who was invited and has not
     * filled the form in, while the interview day has not ended (after that the link would be dead on arrival).
     */
    public function renewFormLink(User $user, Requisition $requisition, Candidate $candidate): bool
    {
        return $this->manageInterview($user, $requisition)
            && ! $candidate->isRejected()
            && $candidate->requisition_id === $requisition->id
            && $candidate->invited_at !== null
            && $candidate->form_submitted_at === null
            && $candidate->formClosesAt()?->isFuture() === true;
    }

    /**
     * "Lanjut ke Interview" is HR's, once, from 03 Screening.
     */
    public function advanceToInterview(User $user, Requisition $requisition): bool
    {
        return $requisition->stage() === RequisitionStage::Screening && $this->viewCandidates($user, $requisition);
    }

    /**
     * Calling a requisition off is HR's, once it is out of approval and until it is filled (REC-PST-004). Before
     * that a rejection already sends it back to its requester.
     */
    public function cancel(User $user, Requisition $requisition): bool
    {
        return in_array($requisition->stage(), [RequisitionStage::Posting, RequisitionStage::Screening, RequisitionStage::Interview, RequisitionStage::Offer], true)
            && $user->can(Permission::ManageRecruitment->value)
            && $user->canAccessCompany($requisition->company_id);
    }

    /**
     * The thread between the requester, Management and HR (REC-APR-009), from the first time it is sent.
     */
    public function comment(User $user, Requisition $requisition): bool
    {
        return $requisition->submitted_at !== null && $this->view($user, $requisition);
    }

    private function openStep(Requisition $requisition): ?WorkflowStep
    {
        if ($requisition->stage() !== RequisitionStage::Submitted) {
            return null;
        }

        $step = $requisition->workflow?->steps()->where('status', 'pending')->first();

        return $step instanceof WorkflowStep ? $step : null;
    }
}
