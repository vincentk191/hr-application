<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceDayStatus;

/**
 * Decides one employee's status for one day. Pure: everything it needs is passed in, so each rule
 * can be tested without a database.
 *
 * Order matters, and it is the point of the class:
 *
 *  1. A day the log has not reached yet is "no data" — never absence. While capture is manual the
 *     days after the last upload look exactly like days nobody came.
 *  2. A day nobody at all scanned in is treated as off. Public holidays are not recorded yet
 *     (they arrive with the calendar events), and without this every holiday would mark the whole
 *     company absent.
 *  3. A punch beats everything else: someone who scanned in was there, even on leave or a weekend.
 *  4. Otherwise a non-workday is off, and approved leave is leave.
 *  5. Someone with no scanner ID mapped on that date cannot have been recorded at all, so no
 *     punch tells us nothing: that is "no data", never absence. Only someone who could have
 *     scanned, on a workday inside the data, and did not, is absent.
 */
class DayClassifier
{
    /**
     * @param  string|null  $firstIn  local "HH:MM" of the earliest punch, null when there were none
     * @param  string|null  $lastOut  local "HH:MM" of the latest punch; equals $firstIn for a lone punch
     * @param  bool  $stillWorking  the day is today and it is not yet end of day
     * @param  bool  $hasScannerId  a scanner ID is mapped to this person on this date
     */
    public function classify(
        WorkSchedule $schedule,
        bool $isWorkday,
        bool $dataCoversDay,
        bool $anyoneScanned,
        bool $onApprovedLeave,
        ?string $firstIn,
        ?string $lastOut,
        int $punchCount,
        bool $stillWorking = false,
        bool $hasScannerId = true,
    ): AttendanceDayStatus {
        if (! $dataCoversDay) {
            return AttendanceDayStatus::NoData;
        }

        if ($firstIn !== null) {
            if ($firstIn > $schedule->latestOnTime()) {
                return AttendanceDayStatus::Late;
            }

            // A lone punch cannot be an early departure: there is no departure to judge.
            if ($punchCount > 1 && ! $stillWorking && $lastOut !== null && $lastOut < $schedule->end) {
                return AttendanceDayStatus::EarlyLeave;
            }

            return AttendanceDayStatus::Present;
        }

        if (! $anyoneScanned || ! $isWorkday) {
            return AttendanceDayStatus::Off;
        }

        if ($onApprovedLeave) {
            return AttendanceDayStatus::Leave;
        }

        return $hasScannerId ? AttendanceDayStatus::Absent : AttendanceDayStatus::NoData;
    }
}
