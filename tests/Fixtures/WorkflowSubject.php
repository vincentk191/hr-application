<?php

namespace Tests\Fixtures;

use App\Concerns\BelongsToCompany;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A stand-in subject for exercising the workflow engine.
 *
 * The engine is deliberately generic, and its first real consumers (leave requests,
 * requisitions, offboarding cases) do not have tables yet. Testing against a fixture keeps
 * these tests about the engine rather than about whichever module landed first, and proves the
 * engine really is subject-agnostic.
 *
 * Its table is created per test by the suite; see WorkflowEngineTest.
 */
#[Fillable(['company_id', 'title'])]
class WorkflowSubject extends Model
{
    use BelongsToCompany;

    protected $table = 'workflow_test_subjects';

    /**
     * The three lines every workflow subject needs. Once a real module has a subject table
     * this is worth extracting into a shared trait; with one fixture using it, it is not.
     *
     * @return MorphOne<WorkflowInstance, $this>
     */
    public function workflow(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'subject');
    }
}
