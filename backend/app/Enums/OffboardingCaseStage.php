<?php

namespace App\Enums;

use App\Workflows\Contracts\WorkflowStage;

/**
 * The offboarding case pipeline of context.md §9.4 / OFF-PIP-002.
 *
 * Draft and Closed are not pipeline tabs in the design — the three tabs are Diajukan,
 * Checklist and Sign-off — but both are real states a case occupies: a case saved and not yet
 * submitted (OFF-WIZ-008), and one archived after sign-off (OFF-SIG-008).
 */
enum OffboardingCaseStage: string implements WorkflowStage
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Checklist = 'checklist';
    case SignOff = 'sign_off';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Checklist => 'Checklist',
            self::SignOff => 'Sign-off',
            self::Closed => 'Ditutup',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Closed;
    }
}
