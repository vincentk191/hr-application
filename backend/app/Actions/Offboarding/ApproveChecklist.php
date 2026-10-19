<?php

namespace App\Actions\Offboarding;

use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Offboarding\ChecklistProgress;
use Illuminate\Validation\ValidationException;

/**
 * HR approves a finished checklist, the second check before a case may go on to sign-off (OFF-CHK-011). Any
 * HR of the company may, not only the one who asked for the case, so it is never held up by one person.
 */
class ApproveChecklist
{
    public function __construct(private ChecklistProgress $progress) {}

    /**
     * @throws ValidationException
     */
    public function handle(OffboardingCase $case, User $by): void
    {
        $progress = $this->progress->for($case);

        if (! $progress['ready']) {
            throw ValidationException::withMessages(['approve' => 'Checklist belum lengkap: '.implode(' ', $progress['blockers'])]);
        }

        $case->update(['checklist_approved_at' => now(), 'checklist_approved_by_user_id' => $by->id]);
    }
}
