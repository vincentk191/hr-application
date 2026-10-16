<?php

namespace App\Workflows\Guards;

use App\Models\OffboardingCase;
use App\Models\WorkflowInstance;
use App\Support\Offboarding\ChecklistProgress;
use App\Workflows\Contracts\TransitionGuard;

/**
 * Blocks sign-off while the checklist is not complete (OFF-CHK-001): a department has not confirmed its
 * list, or an item chosen for the case is still open. Sign-off by the Atasan and HR stays locked until
 * every item is done.
 */
class ChecklistComplete implements TransitionGuard
{
    public function __construct(private ChecklistProgress $progress) {}

    public function passes(WorkflowInstance $instance): bool
    {
        $case = $this->caseOf($instance);

        return $case !== null && $this->progress->for($case)['ready'];
    }

    public function message(WorkflowInstance $instance): string
    {
        $case = $this->caseOf($instance);

        return 'Kasus belum bisa lanjut ke sign-off: '.($case === null ? 'kasus tidak ditemukan.' : implode(' ', $this->progress->for($case)['blockers']));
    }

    private function caseOf(WorkflowInstance $instance): ?OffboardingCase
    {
        $subject = OffboardingCase::acrossCompanies()->find($instance->subject_id);

        return $subject instanceof OffboardingCase ? $subject : null;
    }
}
