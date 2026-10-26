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
 * would plug in. The one exception is Friday, which may end later than the rest of the week.
 */
class WorkSchedule
{
    public const KEY_START = 'attendance.work_start';

    public const KEY_END = 'attendance.work_end';

    public const KEY_END_FRIDAY = 'attendance.work_end_friday';

    public const KEY_GRACE = 'attendance.late_grace_minutes';

    /**
     * @param  string|null  $fridayEnd  when Friday ends, if not at $end like every other weekday
     */
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly int $lateGraceMinutes,
        public readonly ?string $fridayEnd = null,
    ) {}

    /**
     * The schedule in force now: what an administrator saved, else the config defaults.
     */
    public static function current(): self
    {
        $settings = app(AppSettings::class);

        /** @var array{start: string, end: string, late_grace_minutes: int, end_friday?: string|null} $defaults */
        $defaults = config('attendance.schedule');

        // Saved as null (the field emptied on the admin page) means "same as the other days", and is not
        // the same as never having saved, which falls back to the config default.
        $fridayEnd = $settings->get(self::KEY_END_FRIDAY, $defaults['end_friday'] ?? null);

        return new self(
            (string) $settings->get(self::KEY_START, $defaults['start']),
            (string) $settings->get(self::KEY_END, $defaults['end']),
            (int) $settings->get(self::KEY_GRACE, $defaults['late_grace_minutes']),
            is_string($fridayEnd) && $fridayEnd !== '' ? $fridayEnd : null,
        );
    }

    /**
     * When work ends on this day, as "HH:MM": Friday's own end if it has one, else the usual end.
     */
    public function endFor(CarbonInterface $day): string
    {
        return $day->isFriday() && $this->fridayEnd !== null ? $this->fridayEnd : $this->end;
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
