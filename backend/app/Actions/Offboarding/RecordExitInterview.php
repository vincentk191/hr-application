<?php

namespace App\Actions\Offboarding;

use App\Models\OffboardingCase;
use App\Models\User;

/**
 * HR records the exit interview, or skips it with a reason (OFF-SIG-003). One or the other has to be
 * on the case before HR can sign.
 */
class RecordExitInterview
{
    /**
     * @param  array{reason?: string|null, rehire?: bool|null, notes?: string|null, skipped_reason?: string|null}  $data
     */
    public function handle(OffboardingCase $case, array $data, User $by): void
    {
        $skipped = filled($data['skipped_reason'] ?? null);

        $case->update([
            'exit_interview_reason' => $skipped ? null : ($data['reason'] ?? null),
            'exit_interview_rehire' => $skipped ? null : ($data['rehire'] ?? null),
            'exit_interview_notes' => $skipped ? null : ($data['notes'] ?? null),
            'exit_interview_skipped_reason' => $skipped ? trim((string) $data['skipped_reason']) : null,
            'exit_interview_at' => now(),
        ]);

        activity('offboarding')->performedOn($case)->causedBy($by)->log($skipped ? 'Wawancara keluar dilewati' : 'Wawancara keluar dicatat');
    }
}
