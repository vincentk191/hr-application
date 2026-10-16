<?php

namespace App\Workflows\Guards;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * A person who supervises others cannot leave until those people have a new Atasan: leave requests and
 * everything else that follows the reporting line would otherwise be left pointing at a closed account.
 */
class ReportsReassigned implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        return $this->remaining($instance) === 0;
    }

    public function message(WorkflowInstance $instance): string
    {
        return "Masih ada {$this->remaining($instance)} bawahan langsung yang belum dipindahkan ke atasan baru.";
    }

    private function remaining(WorkflowInstance $instance): int
    {
        $case = OffboardingCase::acrossCompanies()->find($instance->subject_id);

        return $case instanceof OffboardingCase
            ? Employee::acrossCompanies()->where('manager_id', $case->employee_id)->where('employment_status', EmploymentStatus::Active)->count()
            : 0;
    }
}
