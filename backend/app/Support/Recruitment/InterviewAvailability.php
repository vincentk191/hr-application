<?php

namespace App\Support\Recruitment;

use App\Enums\LeaveRequestStage;
use App\Enums\Role;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Attendance\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who is out when, for scheduling interviews (REC-INT): the company's holidays, and the leave (waiting or approved) of the
 * people an interview depends on: the person who raised the requisition (PIC), the direct superior of the new hire when
 * that is someone else, and HR. Drawn through the same rules as the attendance calendar and the leave queue, so a day
 * here is a day there.
 *
 * Covers a window around today (four weeks back, sixteen ahead), one entry per working day, so the calendar can be
 * paged through without asking again.
 */
class InterviewAvailability
{
    private const WEEKS_BACK = 4;

    private const WEEKS_AHEAD = 16;

    /**
     * @return array{from: string, to: string, holidays: list<array{date: string, title: string}>, absences: list<array{date: string, person: string, roles: list<string>, detail: string, status: string}>}
     */
    public function for(Requisition $requisition, User $viewer): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $thisWeek = CarbonImmutable::now($timezone)->startOfWeek();
        $from = $thisWeek->subWeeks(self::WEEKS_BACK)->startOfDay();
        $to = $thisWeek->addWeeks(self::WEEKS_AHEAD)->endOfWeek()->startOfDay();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'holidays' => $this->holidays($requisition, $from, $to),
            'absences' => $this->absences($requisition, $viewer, $from, $to),
        ];
    }

    /**
     * The company's holidays (public and its own) that apply to this requisition: group-wide or the company's, and not
     * meant for one department or for another Cabang.
     *
     * @return list<array{date: string, title: string}>
     */
    private function holidays(Requisition $requisition, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $entries = [];

        CalendarEvent::query()
            ->where('kind', 'holiday')
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->where(fn (Builder $company) => $company->whereNull('company_id')->orWhere('company_id', $requisition->company_id))
            ->whereNull('department_id')
            ->where(fn (Builder $branch) => $branch->whereNull('branch_id')->orWhere('branch_id', $requisition->branch_id))
            ->get()
            ->each(function (CalendarEvent $event) use (&$entries, $from, $to): void {
                foreach ($this->days($event->starts_on->toDateString(), $event->ends_on->toDateString(), $from, $to) as $date) {
                    $entries[$date.'|'.$event->title] = ['date' => $date, 'title' => $event->title];
                }
            });

        ksort($entries);

        return array_values($entries);
    }

    /**
     * Leave still waiting or approved, on the working days it covers, for the people the interviews depend on.
     *
     * @return list<array{date: string, person: string, roles: list<string>, detail: string, status: string}>
     */
    private function absences(Requisition $requisition, User $viewer, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $people = $this->people($requisition);

        if ($people === []) {
            return [];
        }

        $schedule = WorkSchedule::current();
        $entries = [];

        LeaveRequest::query()
            ->visibleTo($viewer)
            ->with(['type', 'workflow'])
            ->whereIn('employee_id', array_keys($people))
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', [LeaveRequestStage::Submitted->value, LeaveRequestStage::Approved->value]))
            ->whereDate('start_on', '<=', $to->toDateString())
            ->whereDate('end_on', '>=', $from->toDateString())
            ->get()
            ->each(function (LeaveRequest $leave) use (&$entries, $people, $from, $to, $schedule): void {
                foreach ($this->days($leave->start_on->toDateString(), $leave->end_on->toDateString(), $from, $to) as $date) {
                    if (! $schedule->isWorkday(CarbonImmutable::parse($date))) {
                        continue;
                    }

                    $entries[] = [
                        'date' => $date,
                        'person' => $people[$leave->employee_id]['name'],
                        'roles' => $people[$leave->employee_id]['roles'],
                        'detail' => $leave->typeLabel().($leave->timeframe() !== null ? ' · '.$leave->timeframe() : ''),
                        'status' => (string) $leave->workflow?->current_stage,
                    ];
                }
            });

        usort($entries, fn (array $a, array $b): int => [$a['date'], $a['person']] <=> [$b['date'], $b['person']]);

        return $entries;
    }

    /**
     * The people whose availability matters, by employee: whoever raised the requisition, the new hire's direct superior,
     * and every active HR Admin of the company; one person holding several of these shows once with all their roles.
     *
     * @return array<int, array{name: string, roles: list<string>}>
     */
    private function people(Requisition $requisition): array
    {
        $people = [];
        $add = function (?Employee $employee, string $role) use (&$people): void {
            if ($employee === null) {
                return;
            }

            $people[$employee->id] ??= ['name' => $employee->full_name, 'roles' => []];

            if (! in_array($role, $people[$employee->id]['roles'], true)) {
                $people[$employee->id]['roles'][] = $role;
            }
        };

        $add($this->employeeOf($requisition->created_by_user_id), 'PIC');
        $add($requisition->manager_employee_id === null ? null : Employee::acrossCompanies()->find($requisition->manager_employee_id), 'Atasan');

        User::role(Role::HrAdmin->value)->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->canAccessCompany($requisition->company_id))
            ->each(fn (User $user) => $add($this->employeeOf($user->id), 'HR'));

        return $people;
    }

    private function employeeOf(?int $userId): ?Employee
    {
        return $userId === null ? null : Employee::acrossCompanies()->where('user_id', $userId)->first();
    }

    /**
     * The dates from `$start` to `$end` that fall inside the window.
     *
     * @return list<string>
     */
    private function days(string $start, string $end, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $first = CarbonImmutable::parse($start, $from->getTimezone())->max($from);
        $last = CarbonImmutable::parse($end, $from->getTimezone())->min($to);
        $dates = [];

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }
}
