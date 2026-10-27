<?php

namespace App\Support\Backup;

use App\Support\AppSettings;
use Carbon\CarbonImmutable;

/**
 * How backups are made, as set on the admin page "Cadangan data" and read by docker/server/backup.sh on the host.
 *
 * The page cannot edit a file on the host, so the settings live in `app_settings` and the script queries them. That
 * makes them input to a root cron job from a web form, so the script does not trust them: it checks every value
 * again with the same patterns as below and ignores anything that does not match, falling back to the server's own
 * backup.env. Keep the patterns here and in backup.sh the same.
 *
 * A blank text or number means "use the value in backup.env on the server", not "off". Saving this page can therefore
 * never switch off a copy off the server or the monitoring that the server file set up. The credentials for that
 * copy are deliberately not here: they stay in rclone's configuration on the host, out of reach of the application.
 */
class BackupSettings
{
    public const ENABLED = 'backup.enabled';

    public const RECIPIENT = 'backup.age_recipient';

    public const REMOTE = 'backup.remote';

    public const KEEP_DAYS = 'backup.keep_days';

    public const REMOTE_KEEP_DAYS = 'backup.remote_keep_days';

    public const OK_URL = 'backup.ok_url';

    public const FAIL_URL = 'backup.fail_url';

    /** Written by backup.sh after every run, never by the application. */
    public const STATUS = 'backup.status';

    /** An age public key: "age1" and 58 characters of the bech32 alphabet (no 1, b, i or o). */
    public const RECIPIENT_PATTERN = '/^age1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{58}$/';

    /**
     * An rclone destination "name:path" for a remote set up on the server. The name starts with a letter, digit or
     * underscore, so it can neither be read as an option (--password-command runs a command) nor be one of rclone's
     * on-the-fly ":backend,option=..." connections. A path may not climb out with "..".
     */
    public const REMOTE_PATTERN = '/^(?!.*\.\.)[A-Za-z0-9_][A-Za-z0-9_.-]*:[A-Za-z0-9_.\/-]*$/';

    /** An https address to ping. */
    public const URL_PATTERN = '/^https:\/\/[A-Za-z0-9._~:\/?#@!&\'()*+,;=%-]{1,500}$/';

    public function __construct(private AppSettings $settings) {}

    /**
     * What the page shows. A blank means the server's own value applies.
     *
     * @return array{enabled: bool, age_recipient: string, remote: string, keep_days: int|null, remote_keep_days: int|null, ok_url: string, fail_url: string}
     */
    public function values(): array
    {
        return [
            'enabled' => $this->settings->get(self::ENABLED, true) !== false,
            'age_recipient' => $this->text(self::RECIPIENT),
            'remote' => $this->text(self::REMOTE),
            'keep_days' => $this->number(self::KEEP_DAYS),
            'remote_keep_days' => $this->number(self::REMOTE_KEEP_DAYS),
            'ok_url' => $this->text(self::OK_URL),
            'fail_url' => $this->text(self::FAIL_URL),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $this->settings->set(self::ENABLED, (bool) $data['enabled']);
        $this->settings->set(self::RECIPIENT, trim((string) ($data['age_recipient'] ?? '')));
        $this->settings->set(self::REMOTE, trim((string) ($data['remote'] ?? '')));
        $this->settings->set(self::KEEP_DAYS, filled($data['keep_days'] ?? null) ? (int) $data['keep_days'] : null);
        $this->settings->set(self::REMOTE_KEEP_DAYS, filled($data['remote_keep_days'] ?? null) ? (int) $data['remote_keep_days'] : null);
        $this->settings->set(self::OK_URL, trim((string) ($data['ok_url'] ?? '')));
        $this->settings->set(self::FAIL_URL, trim((string) ($data['fail_url'] ?? '')));
    }

    /**
     * The values for the audit trail. A monitoring address carries a secret token in its path, so only whether one is
     * set is recorded; the public key and the destination are not secret and are kept whole.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function forAudit(array $values): array
    {
        foreach (['ok_url', 'fail_url'] as $key) {
            $values[$key] = filled($values[$key] ?? null) ? '(diatur)' : '';
        }

        return $values;
    }

    public function status(): BackupStatus
    {
        $raw = $this->settings->get(self::STATUS);

        return BackupStatus::from(
            is_array($raw) ? $raw : null,
            CarbonImmutable::now(),
            (string) config('attendance.device_timezone', 'Asia/Jakarta'),
        );
    }

    private function text(string $key): string
    {
        return trim((string) ($this->settings->get($key) ?? ''));
    }

    private function number(string $key): ?int
    {
        $value = $this->settings->get($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
