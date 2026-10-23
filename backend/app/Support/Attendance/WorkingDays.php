<?php

namespace App\Support\Attendance;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Counts and tests working days: Monday to Friday, less the holidays and company events that
 * apply to a given company, department and Cabang.
 *
 * This is the one definition of "a day people work", shared by the attendance calendar and, when
 * it is built, the leave request's day count (REQ-WIZ-005), so the two can never disagree about
 * whether a day is a working day.
 */
class WorkingDays
{
    public function __construct(private WorkSchedule $schedule) {}

    public function isWorkingDay(CarbonInterface $day, ?int $companyId, ?int $departmentId, ?int $branchId): bool
    {
        if (! $this->schedule->isWorkday($day)) {
            return false;
        }

        return ! CalendarEvent::query()
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->whereDate('ends_on', '>=', $day->toDateString())
            ->get()
            ->contains(fn (CalendarEvent $event): bool => $event->appliesTo($companyId, $departmentId, $branchId));
    }

    /**
     * Working days from one date to another, both included.
     */
    public function between(CarbonInterface $from, CarbonInterface $to, ?int $companyId, ?int $departmentId, ?int $branchId): int
    {
        $events = CalendarEvent::query()
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->get()
            ->filter(fn (CalendarEvent $event): bool => $event->appliesTo($companyId, $departmentId, $branchId));

        $count = 0;

        for ($day = CarbonImmutable::instance($from)->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            if (! $this->schedule->isWorkday($day)) {
                continue;
            }

            $date = $day->toDateString();

            $isEventDay = $events->contains(
                fn (CalendarEvent $event): bool => $event->starts_on->toDateString() <= $date && $event->ends_on->toDateString() >= $date,
            );

            if (! $isEventDay) {
                $count++;
            }
        }

        return $count;
    }
}
