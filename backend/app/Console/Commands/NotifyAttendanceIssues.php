<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\Attendance\EmployeeRoster;
use App\Support\Attendance\IssueDetector;
use App\Support\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Tell employees and their supervisors about the attendance exceptions of a day (ATT-ISS).
 *
 * Issues are never stored, so this reads them off the derived days like the Issues screen does. It
 * skips excused ones, and a day the scan log has not reached raises nothing, so a missing upload
 * cannot send everyone an absence notice. Safe to run twice for the same day: each person is told
 * once per day.
 */
class NotifyAttendanceIssues extends Command
{
    protected $signature = 'notifications:attendance-issues {--date= : The day to check, default yesterday}';

    protected $description = 'Notify employees and supervisors about attendance issues on a day';

    public function handle(IssueDetector $detector, EmployeeRoster $roster, Notifier $notifier): int
    {
        $timezone = (string) config('attendance.device_timezone');
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->subDay()->startOfDay();

        $employees = $roster->between($date, $date);
        $open = array_filter($detector->detect($date, $date, $employees), fn (array $issue): bool => $issue['resolution'] === null);

        $byEmployee = [];

        foreach ($open as $issue) {
            $byEmployee[$issue['employeeId']][] = $issue['typeLabel'];
        }

        foreach ($byEmployee as $employeeId => $labels) {
            $employee = Employee::acrossCompanies()->find($employeeId);

            if ($employee !== null) {
                $notifier->attendanceIssues($employee, $date->toDateString(), array_values(array_unique($labels)));
            }
        }

        $this->info(count($byEmployee).' employee(s) with issues on '.$date->toDateString().'.');

        return self::SUCCESS;
    }
}
