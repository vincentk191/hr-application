<?php

namespace App\Enums;

/**
 * What one employee's day looks like on the attendance calendar (ATT-CAL-001).
 *
 * The first four are about someone who is expected to work. `Off` and `NoData` are deliberately
 * not statuses of the person: they say the calendar cannot judge the day, which must never be
 * mistaken for absence while the scanner log is loaded by hand.
 */
enum AttendanceDayStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case EarlyLeave = 'early_leave';
    case Absent = 'absent';
    case Leave = 'leave';
    case Off = 'off';
    case NoData = 'no_data';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::EarlyLeave => 'Pulang cepat',
            self::Absent => 'Tidak hadir',
            self::Leave => 'Cuti',
            self::Off => 'Libur / tidak ada scan',
            self::NoData => 'Belum ada data',
        };
    }

    /**
     * One letter for the dense month grid.
     */
    public function code(): string
    {
        return match ($this) {
            self::Present => 'H',
            self::Late => 'T',
            self::EarlyLeave => 'P',
            self::Absent => 'A',
            self::Leave => 'C',
            self::Off => '·',
            self::NoData => '?',
        };
    }

    /**
     * Whether the day counts as the person having been at work.
     */
    public function isAttended(): bool
    {
        return in_array($this, [self::Present, self::Late, self::EarlyLeave], true);
    }
}
