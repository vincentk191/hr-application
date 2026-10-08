<?php

namespace App\Support\Employees;

use App\Models\Employee;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\EmployeeRoster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "Tim" tab of a profile: where this person sits in the reporting line, and who they work with.
 *
 * Only a slice of the organisation, not the whole chart: the supervisor and the supervisor's own
 * supervisor above, the colleagues who share that supervisor beside, and this person's own direct
 * reports below. The team the figures describe is the person's direct reports when they have any,
 * otherwise the group they belong to (peers, themselves included).
 *
 * The figures for today (present, on leave) come from the same derivation as the attendance
 * calendar, and are left out when the viewer may not see attendance or when the log has not reached
 * today: a count of zero would read as "nobody came".
 */
class TeamSlice
{
    public function __construct(
        private DailyAttendance $attendance,
        private EmployeeRoster $roster,
    ) {}

    /**
     * @return array{
     *     grandSupervisor: array{id: int, name: string, position: string|null}|null,
     *     supervisor: array{id: int, name: string, position: string|null}|null,
     *     peers: array<int, array{id: int, name: string, position: string|null, isSelf: bool}>,
     *     reports: array<int, array{id: int, name: string, position: string|null}>,
     *     basis: 'reports'|'peers',
     *     size: int,
     *     presentToday: int|null,
     *     onLeaveToday: array<int, string>|null
     * }
     */
    public function for(Employee $employee, bool $withAttendance): array
    {
        $supervisor = $employee->manager_id === null ? null : Employee::query()->with('position:id,name')->find($employee->manager_id);
        $grand = $supervisor?->manager_id === null ? null : Employee::query()->with('position:id,name')->find($supervisor->manager_id);

        $peers = $supervisor === null
            ? collect([$employee->loadMissing('position:id,name')])
            : $this->active()->where('manager_id', $supervisor->id)->orWhereKey($employee->id)->orderBy('full_name')->get();

        $reports = $this->active()->where('manager_id', $employee->id)->orderBy('full_name')->get();

        $team = $reports->isNotEmpty() ? $reports : $peers;
        [$present, $onLeave] = $withAttendance ? $this->today($team) : [null, null];

        return [
            'grandSupervisor' => $grand === null ? null : $this->person($grand),
            'supervisor' => $supervisor === null ? null : $this->person($supervisor),
            'peers' => $peers->map(fn (Employee $peer): array => [...$this->person($peer), 'isSelf' => $peer->id === $employee->id])->values()->all(),
            'reports' => $reports->map(fn (Employee $report): array => $this->person($report))->values()->all(),
            'basis' => $reports->isNotEmpty() ? 'reports' : 'peers',
            'size' => $team->count(),
            'presentToday' => $present,
            'onLeaveToday' => $onLeave,
        ];
    }

    /**
     * @return Builder<Employee>
     */
    private function active(): Builder
    {
        return Employee::query()->with('position:id,name')->active();
    }

    /**
     * @return array{id: int, name: string, position: string|null}
     */
    private function person(Employee $employee): array
    {
        return ['id' => $employee->id, 'name' => $employee->full_name, 'position' => $employee->position?->name];
    }

    /**
     * @param  iterable<Employee>  $team
     * @return array{0: int|null, 1: array<int, string>|null}
     */
    private function today(iterable $team): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $members = collect($team);

        $result = $this->attendance->forRange($today, $today, $this->roster->windows($members));

        if ($result['dataThrough'] === null || $result['dataThrough'] < $today->toDateString()) {
            // Leave is known in advance, so who is on it can still be said; who came cannot.
            return [null, $this->onLeave($members, $result['records'], $today->toDateString())];
        }

        $present = $members->filter(fn (Employee $member): bool => in_array(
            $result['records'][$member->id][$today->toDateString()]['status'] ?? null,
            ['present', 'late', 'early_leave'],
            true,
        ))->count();

        return [$present, $this->onLeave($members, $result['records'], $today->toDateString())];
    }

    /**
     * @param  Collection<int, Employee>  $members
     * @param  array<int, array<string, array{status: string}>>  $records
     * @return array<int, string>
     */
    private function onLeave($members, array $records, string $date): array
    {
        return $members
            ->filter(fn (Employee $member): bool => ($records[$member->id][$date]['status'] ?? null) === 'leave')
            ->map(fn (Employee $member): string => $member->full_name)
            ->values()
            ->all();
    }
}
