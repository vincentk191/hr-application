<?php

namespace App\Enums;

/**
 * How a name in a branch's rekapitulasi was tied to an employee of that Cabang.
 */
enum RekapMatch: string
{
    /** Exactly one employee of the Cabang has this name, or HR linked it by hand. */
    case Matched = 'matched';

    /** More than one employee of the Cabang has this name: HR must choose. */
    case Ambiguous = 'ambiguous';

    /** Nobody of the Cabang has this name: HR links it by hand or skips it. */
    case Unmatched = 'unmatched';

    /** HR chose to leave this row out. */
    case Skipped = 'skipped';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Cocok',
            self::Ambiguous => 'Nama ganda',
            self::Unmatched => 'Belum cocok',
            self::Skipped => 'Dilewati',
        };
    }

    /**
     * Whether the row still needs HR before the file can be confirmed.
     */
    public function needsAttention(): bool
    {
        return $this === self::Ambiguous || $this === self::Unmatched;
    }
}
