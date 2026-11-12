<?php

namespace App\Enums;

/**
 * The icons an administrator can give a kind of request. The value is the key the front end draws an icon for
 * (`request-type-icons.ts`), so a case added here needs its icon added there.
 */
enum RequestTypeIcon: string
{
    case Plane = 'plane';
    case Sun = 'sun';
    case Stethoscope = 'stethoscope';
    case DoorOpen = 'door-open';
    case Baby = 'baby';
    case Heart = 'heart';
    case Briefcase = 'briefcase';
    case Clock = 'clock';
    case House = 'house';
    case GraduationCap = 'graduation-cap';
    case CalendarDays = 'calendar-days';
    case FileText = 'file-text';

    /**
     * The Indonesian name shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Plane => 'Pesawat (cuti, bepergian)',
            self::Sun => 'Matahari (libur, istirahat)',
            self::Stethoscope => 'Stetoskop (sakit)',
            self::DoorOpen => 'Pintu (izin keluar)',
            self::Baby => 'Bayi (melahirkan, cuti ayah)',
            self::Heart => 'Hati (duka, menikah)',
            self::Briefcase => 'Tas kerja (dinas)',
            self::Clock => 'Jam (lembur, jam kerja)',
            self::House => 'Rumah (kerja dari rumah)',
            self::GraduationCap => 'Topi wisuda (pendidikan)',
            self::CalendarDays => 'Kalender',
            self::FileText => 'Dokumen (lainnya)',
        };
    }

    /**
     * @return array<string, string> the label by value, for a select
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $icon): array => [$icon->value => $icon->label()])->all();
    }
}
