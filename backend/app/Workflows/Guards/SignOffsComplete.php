<?php

namespace App\Workflows\Guards;

use App\Models\OffboardingCase;
use App\Models\OffboardingSignoff;
use App\Models\WorkflowInstance;
use App\Workflows\Contracts\TransitionGuard;

/**
 * A case cannot close until everyone in the sign-off sequence has signed (OFF-SIG-008), and there is at
 * least one signature to wait for.
 */
class SignOffsComplete implements TransitionGuard
{
    public function passes(WorkflowInstance $instance): bool
    {
        $signoffs = OffboardingSignoff::acrossCompanies()->where('offboarding_case_id', $instance->subject_id)->get();

        return $signoffs->isNotEmpty() && $signoffs->every->isSigned();
    }

    public function message(WorkflowInstance $instance): string
    {
        $open = OffboardingSignoff::acrossCompanies()->where('offboarding_case_id', $instance->subject_id)->whereNull('signed_at')->count();

        return $open > 0 ? "Masih menunggu {$open} tanda tangan." : 'Sign-off belum dimulai.';
    }
}
