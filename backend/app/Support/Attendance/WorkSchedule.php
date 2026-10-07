<?php

namespace App\Support\Attendance;

use App\Support\AppSettings;
use Carbon\CarbonInterface;

/**
 * The working day the calendar judges punches against: when work starts and ends, and how much
 * lateness is forgiven.
 *
 * Saturday and Sunday are never workdays. That is fixed here rather than configurable on purpose:
 * nobody works the weekend, and a setting for it would only be a way to mark everyone absent by
 * accident. Public holidays are separate and arrive with the calendar events.
 *
 * The hours are edited by an administrator in the admin panel (Jam kerja) and stored in the
 * database; config/attendance.php supplies the defaults until someone does. One schedule applies
 * to everyone — shift work would need one per employee or department, and this is where that
 * would plug in.
 */
class WorkSchedule
{
    public const KEY_START = 'attendance.work_start';

    public const KEY_END = 'attendance.work_end';

    public const KEY_GRACE = 'attendance.late_grace_minutes';

    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly int $lateGraceMinutes,
    ) {}

    /**
     * The schedule in force now: what an administrator saved, else the config defaults.
     */
    public static function current(): self
    {
        $settings = app(AppSettings::class);

        /** @var array{start: string, end: string, late_grace_minutes: int} $defaults */
        $defaults = config('attendance.schedule');

        return new self(
            (string) $settings->get(self::KEY_START, $defaults['start']),
            (string) $settings->get(self::KEY_END, $defaults['end']),
            (int) $settings->get(self::KEY_GRACE, $defaults['late_grace_minutes']),
        );
    }

    /**
     * Monday to Friday.
     */
    public function isWorkday(CarbonInterface $day): bool
    {
        return $day->isWeekday();
    }

    /**
     * The latest arrival, as "HH:MM", that still counts as on time.
     */
    public function latestOnTime(): string
    {
        [$hours, $minutes] = array_map('intval', explode(':', $this->start));
        $total = $hours * 60 + $minutes + $this->lateGraceMinutes;

        return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
    }
}
