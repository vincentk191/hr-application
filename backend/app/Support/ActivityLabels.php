<?php

namespace App\Support;

use Spatie\Activitylog\Models\Activity;

/**
 * Indonesian wording for the activity log in the admin panel.
 *
 * What is stored stays as written (a few descriptions are English, and Spatie records model events as
 * "created", "updated" and "deleted"), so tests and searches keep matching. Only the display is translated,
 * which covers entries written before the wording changed too.
 */
class ActivityLabels
{
    /**
     * @var array<string, string>
     */
    private const LOG_NAMES = [
        'default' => 'Umum',
        'account' => 'Akun',
        'attendance' => 'Absensi',
        'auth' => 'Masuk & 2FA',
        'calendar' => 'Kalender',
        'documents' => 'Dokumen',
        'employee' => 'Karyawan',
        'offboarding' => 'Offboarding',
        'recruitment' => 'Rekrutmen',
        'request-types' => 'Jenis pengajuan',
        'roles' => 'Peran',
        'settings' => 'Pengaturan',
    ];

    /**
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'restored' => 'Dipulihkan',
        'Signed in' => 'Masuk',
        'Two-factor authentication turned on' => '2FA diaktifkan',
        'Two-factor authentication turned off' => '2FA dinonaktifkan',
        'Two-factor authentication reset by an administrator' => '2FA diatur ulang oleh administrator',
        'Punch added manually' => 'Scan ditambahkan manual',
        'Punch voided' => 'Scan dibatalkan',
        'Punch changed' => 'Scan diubah',
        'Issue excused' => 'Masalah absensi dimaklumi',
        'Issue reopened' => 'Masalah absensi dibuka kembali',
        'Restricted document downloaded' => 'Dokumen terbatas diunduh',
        'Backup settings changed' => 'Pengaturan cadangan diubah',
        'Invitation template changed' => 'Template undangan diubah',
        'Recruitment template changed' => 'Template rekrutmen diubah',
        'Working hours changed' => 'Jam kerja diubah',
        'Group colour changed' => 'Warna grup diubah',
    ];

    public static function logName(?string $name): string
    {
        return self::LOG_NAMES[$name ?? 'default'] ?? (string) $name;
    }

    public static function description(?string $description): string
    {
        return self::DESCRIPTIONS[$description ?? ''] ?? (string) $description;
    }

    /**
     * The log names in use, as filter options keyed by what is stored.
     *
     * @return array<string, string>
     */
    public static function logNameOptions(): array
    {
        $options = [];

        foreach (Activity::query()->distinct()->orderBy('log_name')->pluck('log_name') as $name) {
            if (is_string($name) && $name !== '') {
                $options[$name] = self::logName($name);
            }
        }

        return $options;
    }
}
