<?php

namespace App\Support\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Access\Entrance;

/**
 * What in Rekrutmen is waiting on this person (REC-DSH-008): the number on the menu item. It is the requisition line of
 * the dashboard's "Menunggu keputusan" card and nothing more: the approvals assigned to them, by name or by the role
 * they hold. Never a count of everything that exists.
 *
 * What HR should merely know about (a requisition sent, one ready to post, an interview today) is told through
 * notifications instead (Notifier), whoever it is assigned to.
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

        return WorkflowStep::waitingOn($user, Requisition::class)->count()
            + WorkflowStep::waitingOn($user, CandidateOffer::class)->count();
    }
}
