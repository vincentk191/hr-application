<?php

namespace Tests\Fixtures;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
    use BelongsToCompany, HasWorkflow;

    protected $table = 'workflow_test_subjects';
}
