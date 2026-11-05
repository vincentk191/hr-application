<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The headings the admin panel's menu is grouped under. The order of the cases is the order of the groups in the menu
 * (and in the phone's "Lainnya" sheet, which is built from the same navigation); each page or resource names its group
 * as `$navigationGroup` and its place within it as `$navigationSort`.
 */
enum AdminNavigationGroup: string implements HasLabel
{
    case Organisation = 'organisation';
    case Access = 'access';
    case Workflow = 'workflow';
    case Attendance = 'attendance';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Organisation => 'Organisasi',
            self::Access => 'Akses',
            self::Workflow => 'Alur kerja',
            self::Attendance => 'Absensi',
            self::System => 'Sistem',
        };
    }
}
