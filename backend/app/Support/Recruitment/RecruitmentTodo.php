<?php

namespace App\Support\Recruitment;

use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Access\Entrance;
use Illuminate\Database\Eloquent\Builder;

/**
 * What in Rekrutmen is waiting on this person (REC-DSH-008): the number on the menu item. One number for their own
 * to-do, never a count of everything that exists.
 *
 *  - a requisition waiting for their approval (Management, then HR);
 *  - for HR: an approved requisition ready to move on to Posting, and one at Posting that is not recorded on any
 *    platform yet;
 *  - for HR and for the new hire's direct superior: an interview that has taken place and they have not yet evaluated.
 */
class RecruitmentTodo
{
    public function __construct(private Entrance $entrance) {}

    public function countFor(User $user): int
    {
        // Recruitment is a private-entrance module: nothing to do on the public one.
        if ($this->entrance->isPublic()) {
            return 0;
        }

        $count = WorkflowStep::waitingOn($user, Requisition::class)->count();

        if ($user->can(Permission::ManageRecruitment->value)) {
            $count += $this->readyToPost($user) + $this->notYetPosted($user);
        }

        return $count + $this->awaitingEvaluation($user);
    }

    /**
     * Interviews that have taken place and this person has not yet evaluated: HR for any candidate of a requisition they
     * see, the direct superior for those of the requisitions they will hire into.
     */
    private function awaitingEvaluation(User $user): int
    {
        $isHr = $user->can(Permission::ManageRecruitment->value);
        $own = Employee::acrossCompanies()->where('user_id', $user->id)->value('id');

        if (! $isHr && $own === null) {
            return 0;
        }

        $requisitions = Requisition::query()->visibleTo($user)
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', [RequisitionStage::Interview->value, RequisitionStage::Offer->value]))
            ->when(! $isHr, fn (Builder $query) => $query->where('manager_employee_id', $own));

        return Candidate::query()
            ->whereIn('requisition_id', $requisitions->select('requisitions.id'))
            ->whereNotNull('interview_at')
            ->where('interview_at', '<', now())
            ->whereDoesntHave('evaluations', fn (Builder $evaluations) => $evaluations->where('user_id', $user->id))
            ->count();
    }

    /**
     * Approved by everyone, waiting for HR's "Lanjut ke Posting".
     */
    private function readyToPost(User $user): int
    {
        return Requisition::query()->visibleTo($user)
            ->whereHas('workflow', fn (Builder $workflow) => $workflow
                ->where('current_stage', RequisitionStage::Submitted->value)
                ->whereHas('steps')
                ->whereDoesntHave('steps', fn (Builder $steps) => $steps->where('status', '!=', 'approved')))
            ->count();
    }

    /**
     * At Posting with nothing recorded yet.
     */
    private function notYetPosted(User $user): int
    {
        return Requisition::query()->visibleTo($user)
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', RequisitionStage::Posting->value))
            ->whereDoesntHave('postings')
            ->count();
    }
}
