<?php

namespace App\Actions\Recruitment;

use App\Models\Requisition;
use App\Models\User;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * Saves a requisition, new or changed (REC-WIZ-003). A draft can be half-filled and finished later, at the step
 * it was left on (REC-WIZ-004); sending is what checks it is complete. A requisition already sent but not yet
 * decided can be corrected the same way (REC-WIZ-005), and stays where it is.
 */
class SaveRequisitionDraft
{
    public function __construct(private WorkflowEngine $engine, private RequisitionWorkflow $definition) {}

    /**
     * @param  array<string, mixed>  $fields  the requisition's own fields
     */
    public function handle(?Requisition $requisition, array $fields, User $by): Requisition
    {
        return DB::transaction(function () use ($requisition, $fields, $by): Requisition {
            if ($requisition === null) {
                $requisition = Requisition::create([...$fields, 'created_by_user_id' => $by->id]);

                $this->engine->start($requisition, $this->definition, [], $by);

                return $requisition;
            }

            $requisition->update($fields);

            return $requisition;
        });
    }
}
