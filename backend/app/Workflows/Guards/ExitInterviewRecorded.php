<?php

namespace App\Workflows\Guards;

use App\Models\OffboardingCase;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * A case cannot close until HR has filled in the exit interview, or skipped it with a reason (OFF-SIG-006).
 */
class ExitInterviewRecorded implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($instance->subject_id);

        return $case instanceof OffboardingCase && $case->exit_interview_at !== null;
    }

    public function message(WorkflowInstance $instance): string
    {
        return 'Catat wawancara keluar dulu, atau lewati dengan alasan.';
    }
}
