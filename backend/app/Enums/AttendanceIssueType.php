<?php

namespace App\Enums;

/**
 * The kinds of attendance exception the Issues screen raises (ATT-ISS-001..003).
 */
enum AttendanceIssueType: string
{
    case MissingCheckout = 'missing_checkout';
    case UnapprovedAbsence = 'unapproved_absence';
    case Late = 'late';
    case EarlyLeave = 'early_leave';
    case DuplicatePunch = 'duplicate_punch';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::MissingCheckout => 'Belum check-out',
            self::UnapprovedAbsence => 'Tidak hadir tanpa izin',
            self::Late => 'Terlambat',
            self::EarlyLeave => 'Pulang cepat',
            self::DuplicatePunch => 'Scan ganda',
        };
    }
}
