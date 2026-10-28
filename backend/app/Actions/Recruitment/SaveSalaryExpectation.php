<?php

namespace App\Actions\Recruitment;

use App\Models\Requisition;
use App\Models\User;

/**
 * Management's salary expectation for the role (REC-APR-008): a range in rupiah, saved with "Simpan" before
 * approving (REC-APR-007) or with the approval itself. Who set it is kept, and the change is on the audit trail.
 */
class SaveSalaryExpectation
{
    public function handle(Requisition $requisition, User $by, ?int $min, ?int $max): void
    {
        $before = ['min' => $requisition->salary_min, 'max' => $requisition->salary_max];

        $requisition->update(['salary_min' => $min, 'salary_max' => $max, 'salary_set_by_user_id' => $by->id]);

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['before' => $before, 'after' => ['min' => $min, 'max' => $max]])
            ->log('Ekspektasi gaji diubah');
    }
}
