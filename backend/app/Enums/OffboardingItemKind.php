<?php

namespace App\Enums;

/**
 * What kind of thing a checklist item is (OFF-ITM-001).
 */
enum OffboardingItemKind: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Process = 'process';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Fisik',
            self::Digital => 'Akun digital',
            self::Process => 'Proses',
        };
    }
}
