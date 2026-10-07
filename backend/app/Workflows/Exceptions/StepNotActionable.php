<?php

namespace App\Workflows\Exceptions;

use App\Models\WorkflowStep;

/**
 * Someone tried to decide a step that is not theirs to decide, or not yet open.
 */
class StepNotActionable extends WorkflowException
{
    public static function locked(WorkflowStep $step): self
    {
        return new self("Step [{$step->position}] is not open yet; an earlier step is undecided.");
    }

    public static function alreadyDecided(WorkflowStep $step): self
    {
        return new self("Step [{$step->position}] was already decided.");
    }

    public static function notTheApprover(WorkflowStep $step): self
    {
        return new self("This user is not the approver for step [{$step->position}].");
    }
}
