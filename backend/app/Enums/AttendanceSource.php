<?php

namespace App\Enums;

/**
 * Where a Cabang's attendance comes from: a fingerprint scanner whose Laporan Absensi HR imports, or
 * the rekapitulasi the branch's PIC sends. Set by HR on the Cabang.
 */
enum AttendanceSource: string
{
    case Scanner = 'scanner';
    case Rekap = 'rekap';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scanner => 'Pemindai (Laporan Absensi)',
            self::Rekap => 'Rekapitulasi dari cabang',
        };
    }
}
