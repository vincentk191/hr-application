<?php

namespace App\Support\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The recruitment funnel on the dashboard (REC-DSH): how far the people of the requisitions this person may see have got.
 * Drafts and cancelled requisitions are left out. Each figure counts people who got that far, whatever happened to them
 * later, so a candidate turned down after the interview is still in Interview.
 *
 * Shortlist is the candidates on a requisition's shortlist; Interview those given an interview; Ditawari those whose offer was sent; Terisi those who accepted.
 */
class RecruitmentFunnel
{
    /**
     * @return array{shortlisted: int, interviewed: int, offered: int, filled: int}
     */
    public function for(User $user): array
    {
        $requisitions = Requisition::query()->visibleTo($user)
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->whereNotIn('current_stage', [
                RequisitionStage::Draft->value, RequisitionStage::Cancelled->value,
            ]));

        $ids = (clone $requisitions)->pluck('id');

        return [
            'shortlisted' => Candidate::query()->whereIn('requisition_id', $ids)->count(),
            'interviewed' => Candidate::query()->whereIn('requisition_id', $ids)->whereNotNull('interview_at')->count(),
            'offered' => $this->offers($ids)->whereNotNull('sent_at')->distinct('candidate_id')->count('candidate_id'),
            'filled' => $this->offers($ids)->accepted()->distinct('candidate_id')->count('candidate_id'),
        ];
    }

    /**
     * @param  iterable<int, int>  $requisitionIds
     * @return Builder<CandidateOffer>
     */
    private function offers(iterable $requisitionIds): Builder
    {
        return CandidateOffer::query()->whereIn('requisition_id', $requisitionIds);
    }
}
