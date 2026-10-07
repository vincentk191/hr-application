<?php

namespace App\Concerns;

use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Applied to anything that moves through the approval engine: a leave request, and later a
 * requisition or an offboarding case.
 *
 * The subject owns no stage of its own — the instance does — so this is the whole link between
 * a domain record and its workflow.
 */
trait HasWorkflow
{
    /**
     * @return MorphOne<WorkflowInstance, $this>
     */
    public function workflow(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'subject');
    }

    public function hasWorkflow(): bool
    {
        return $this->workflow()->exists();
    }
}
