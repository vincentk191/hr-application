<?php

namespace App\Support\Attendance;

use Carbon\CarbonInterface;

/**
 * The working week the calendar judges punches against: which days are workdays, when work
 * starts and ends, and how much lateness is forgiven.
 *
 * One schedule for everyone, read from config/attendance.php. The requirements do not define
 * shifts or per-department hours, so this is a deliberate simplification; a warehouse on shifts
 * would need a schedule per employee or department, and this class is where that would plug in.
 */
class WorkSchedule
{
    /**
     * @param  list<int>  $workdays  ISO weekday numbers, Monday = 1
     */
    public function __construct(
        public readonly array $workdays,
        public readonly string $start,
        public readonly string $end,
        public readonly int $lateGraceMinutes,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{workdays: list<int>, start: string, end: string, late_grace_minutes: int} $schedule */
        $schedule = config('attendance.schedule');

        return new self(
            $schedule['workdays'],
            $schedule['start'],
            $schedule['end'],
            $schedule['late_grace_minutes'],
        );
    }

    public function isWorkday(CarbonInterface $day): bool
    {
        return in_array($day->dayOfWeekIso, $this->workdays, true);
    }

    /**
     * The latest arrival, as "HH:MM", that still counts as on time.
     */
    public function latestOnTime(): string
    {
        return $this->addMinutes($this->start, $this->lateGraceMinutes);
    }

    private function addMinutes(string $time, int $minutes): string
    {
        [$hours, $mins] = array_map('intval', explode(':', $time));
        $total = $hours * 60 + $mins + $minutes;

        return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
    }
}
