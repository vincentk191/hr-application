<?php

namespace App\Workflows\Guards;

use App\Models\OffboardingCase;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;
use Carbon\CarbonImmutable;

/**
 * A case closes only after the last working day has passed, never before, so a case cannot be closed
 * early and forgotten (the person is still working that day).
 */
class LastDayPassed implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $case = OffboardingCase::acrossCompanies()->find($instance->subject_id);

        return $case instanceof OffboardingCase && $case->last_working_on !== null
            && $this->today()->toDateString() > $case->last_working_on->toDateString();
    }

    public function message(WorkflowInstance $instance): string
    {
        $case = OffboardingCase::acrossCompanies()->find($instance->subject_id);
        $day = $case instanceof OffboardingCase ? $case->last_working_on?->toDateString() : null;

        return 'Kasus baru bisa ditutup setelah hari kerja terakhir'.($day === null ? '.' : " ({$day}).");
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('attendance.device_timezone'));
    }
}
