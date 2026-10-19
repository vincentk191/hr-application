<?php

namespace App\Workflows\Guards;

use App\Models\OffboardingCase;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * The case goes on to sign-off only after HR has approved the finished checklist (OFF-CHK-011).
 */
class ChecklistApproved implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($instance->subject_id);

        return $case instanceof OffboardingCase && $case->checklist_approved_at !== null;
    }

    public function message(WorkflowInstance $instance): string
    {
        return 'HR belum menyetujui checklist.';
    }
}
