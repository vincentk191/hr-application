<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Number;
use Throwable;

/**
 * What the last backup run reported, for the status box on the admin page. docker/server/backup.sh writes it to
 * `app_settings` (key `backup.status`) after every run; the application only reads it.
 *
 * Anything not reported at all is also a finding: a backup that stops running cannot report that it stopped, so a
 * report older than {@see self::STALE_AFTER_HOURS} hours is shown as a warning however good it was.
 */
final class BackupStatus
{
    public const STALE_AFTER_HOURS = 36;

    public const UNREPORTED = 'unreported';

    public const HEALTHY = 'healthy';

    public const WARNING = 'warning';

    public const FAILED = 'failed';

    public const PAUSED = 'paused';

    /**
     * @param  string  $state  one of the constants above
     * @param  list<array{label: string, value: string}>  $lines
     * @param  list<string>  $remotes  the rclone remotes the server knows, as it last reported them
     */
    private function __construct(
        public readonly string $state,
        public readonly string $heading,
        public readonly string $description,
        public readonly array $lines = [],
        public readonly array $remotes = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function from(?array $raw, CarbonImmutable $now, string $timezone): self
    {
        if ($raw === null || ! isset($raw['finished_at'])) {
            return new self(
                self::UNREPORTED,
                'Belum ada laporan dari server',
                'Cadangan belum pernah berjalan, atau server belum mengirim laporannya. Jadwalkan docker/server/backup.sh di server (README, "Backups") dan jalankan sekali.',
            );
        }

        $finished = self::time($raw['finished_at'], $timezone);
        $lastSuccess = self::time($raw['last_success_at'] ?? null, $timezone);
        $effective = is_array($raw['effective'] ?? null) ? $raw['effective'] : [];
        $remote = (string) ($effective['remote'] ?? '');
        $remotes = array_values(array_filter(array_map('strval', (array) ($raw['remotes'] ?? []))));

        $lines = array_values(array_filter([
            ['label' => 'Laporan terakhir', 'value' => self::format($finished)],
            ['label' => 'Cadangan berhasil terakhir', 'value' => $lastSuccess === null ? 'belum pernah' : self::format($lastSuccess)],
            isset($raw['folder']) ? ['label' => 'Folder', 'value' => (string) $raw['folder']] : null,
            isset($raw['bytes']) ? ['label' => 'Ukuran', 'value' => (string) Number::fileSize((int) $raw['bytes'])] : null,
            ['label' => 'Kunci enkripsi yang dipakai', 'value' => self::shortKey((string) ($effective['recipient'] ?? '')).self::source($effective['recipient_source'] ?? null)],
            ['label' => 'Salinan di luar server', 'value' => $remote === '' ? 'tidak ada' : $remote.self::source($effective['remote_source'] ?? null)],
            ['label' => 'Masa simpan', 'value' => ($effective['keep_days'] ?? '?').' hari di server, '.($effective['remote_keep_days'] ?? '?').' hari di luar server'],
            ['label' => 'Remote yang dikenal server', 'value' => $remotes === [] ? '-' : implode(', ', $remotes)],
        ]));

        if (($raw['paused'] ?? false) === true) {
            return new self(self::PAUSED, 'Cadangan otomatis dijeda', 'Dimatikan dari halaman ini. Tidak ada cadangan yang dibuat sampai dinyalakan lagi.', $lines, $remotes);
        }

        if (($raw['ok'] ?? false) !== true) {
            return new self(self::FAILED, 'Cadangan terakhir gagal', (string) ($raw['message'] ?? 'Tidak ada keterangan.'), $lines, $remotes);
        }

        if (($raw['remote_ok'] ?? null) === false) {
            return new self(self::WARNING, 'Cadangan di server berhasil, tetapi salinan di luar server gagal', (string) ($raw['message'] ?? 'Tidak ada keterangan.'), $lines, $remotes);
        }

        if ($lastSuccess === null || $lastSuccess->lt($now->subHours(self::STALE_AFTER_HOURS))) {
            return new self(self::WARNING, 'Tidak ada cadangan baru', 'Cadangan berhasil terakhir lebih dari '.self::STALE_AFTER_HOURS.' jam lalu. Periksa jadwal cron di server dan /var/log/hr-backup.log.', $lines, $remotes);
        }

        if ($remote === '') {
            return new self(self::WARNING, 'Cadangan hanya ada di server ini', 'Tanpa salinan di luar server, cadangan ikut hilang bersama server. Isi tujuan salinan di bawah.', $lines, $remotes);
        }

        return new self(self::HEALTHY, 'Cadangan berjalan', 'Cadangan terakhir berhasil dan sudah disalin ke luar server.', $lines, $remotes);
    }

    private static function time(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone($timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private static function format(?CarbonImmutable $time): string
    {
        return $time === null ? '-' : $time->format('d-m-Y H:i');
    }

    private static function shortKey(string $key): string
    {
        return $key === '' ? '-' : substr($key, 0, 8).'…'.substr($key, -6);
    }

    private static function source(mixed $source): string
    {
        return match ($source) {
            'page' => ' (dari halaman ini)',
            'file' => ' (dari backup.env di server)',
            default => '',
        };
    }
}
