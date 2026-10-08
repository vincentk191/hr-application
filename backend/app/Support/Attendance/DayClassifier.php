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
 *     days after the last upload look exactly like days nobody came. The exception is what is known
 *     in advance: a holiday is off and approved leave is leave, even in the future.
 *  2. A holiday or company event is off for the people it covers (ATT-WIZ). A day nobody at all
 *     scanned in is also treated as off, as a fallback for a holiday no one has entered: without
 *     it every such day would mark the whole company absent.
 *  3. A punch beats everything else: someone who scanned in was there, even on leave or a weekend.
 *  4. Otherwise approved leave is leave (weekends included), and a non-workday is off.
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
     * @param  bool  $isHoliday  a holiday or company event covers this person on this date
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
        bool $isHoliday = false,
    ): AttendanceDayStatus {
        if (! $dataCoversDay) {
            // Past the log nothing can be said about attendance, but a holiday or approved leave is
            // known in advance, so those days already show as what they are.
            if ($isHoliday) {
                return AttendanceDayStatus::Off;
            }

            return $onApprovedLeave ? AttendanceDayStatus::Leave : AttendanceDayStatus::NoData;
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

        if ($isHoliday) {
            return AttendanceDayStatus::Off;
        }

        // Approved leave is a recorded fact, so it shows even on a weekend or a day nobody scanned:
        // the leave request counts those days too, and the calendar should agree with it.
        if ($onApprovedLeave) {
            return AttendanceDayStatus::Leave;
        }

        if (! $anyoneScanned || ! $isWorkday) {
            return AttendanceDayStatus::Off;
        }

        return $hasScannerId ? AttendanceDayStatus::Absent : AttendanceDayStatus::NoData;
    }
}
