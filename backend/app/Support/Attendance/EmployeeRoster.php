<?php

namespace App\Support\Attendance;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\ReportingLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who the attendance screens consider for a date range: employees who were employed at any point
 * in it, optionally narrowed by Cabang, department and a name or number search.
 *
 * Read through the company scope, so it is the active company, or every company in the combined
 * view. The calendar and the issues screen both use it, so they always look at the same people.
 */
class EmployeeRoster
{
    /**
     * @param  list<string>  $departments
     * @param  list<string>  $branches
     * @return Collection<int, Employee>
     */
    public function between(CarbonInterface $from, CarbonInterface $to, array $departments = [], string $search = '', ?int $teamLead = null, ?string $leaveType = null, ?User $viewer = null, array $branches = []): Collection
    {
        return Employee::query()
            ->when($viewer !== null, fn (Builder $query) => $query->visibleTo($viewer))
            ->with(['branch:id,name,attendance_source', 'department:id,name', 'position:id,name', 'manager:id,full_name'])
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $to->toDateString()))
            ->where(fn (Builder $query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $from->toDateString()))
            ->when($branches !== [], fn (Builder $query) => $query->whereHas('branch', fn (Builder $branch) => $branch->whereIn('name', $branches)))
            ->when($departments !== [], fn (Builder $query) => $query->whereHas('department', fn (Builder $department) => $department->whereIn('name', $departments)))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%"),
            ))
            ->when($teamLead !== null, fn (Builder $query) => $query->whereIn('id', $this->teamOf($teamLead)))
            ->when($leaveType !== null, fn (Builder $query) => $query->whereIn('id', $this->onLeaveOfType($leaveType, $from, $to)))
            ->orderBy('full_name')
            ->get(['id', 'company_id', 'full_name', 'employee_number', 'branch_id', 'department_id', 'position_id', 'manager_id', 'zona', 'joined_on', 'exited_on']);
    }

    /**
     * People who supervise others, for the team filter.
     *
     * @return Collection<int, Employee>
     */
    public function teamLeads(?User $viewer = null): Collection
    {
        return Employee::query()
            ->when($viewer !== null, fn (Builder $query) => $query->visibleTo($viewer))
            ->whereIn('id', Employee::query()->whereNotNull('manager_id')->select('manager_id'))
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }

    /**
     * Everyone below a supervisor in the reporting line, at any depth. Empty for someone who is not
     * in reach, so the filter can never reveal a team the viewer could not already see.
     *
     * @return list<int>
     */
    private function teamOf(int $leadId): array
    {
        $lead = Employee::query()->find($leadId);

        return $lead === null ? [] : app(ReportingLine::class)->downlineIds($lead);
    }

    /**
     * Employees with approved leave of the given kind that touches the range.
     *
     * @return array<int, int>
     */
    private function onLeaveOfType(string $leaveType, CarbonInterface $from, CarbonInterface $to): array
    {
        return LeaveRequest::query()
            ->where('leave_type', $leaveType)
            ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', 'approved'))
            ->whereDate('start_on', '<=', $to->toDateString())
            ->whereDate('end_on', '>=', $from->toDateString())
            ->pluck('employee_id')
            ->unique()
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The employment window of each person, in the shape DailyAttendance takes.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array{joined: string|null, exited: string|null, company: int|null, department: int|null, branch: int|null}>
     */
    public function windows(Collection $employees): array
    {
        return $employees->mapWithKeys(fn (Employee $employee): array => [
            $employee->id => [
                'joined' => $employee->joined_on?->toDateString(),
                'exited' => $employee->exited_on?->toDateString(),
                'company' => $employee->company_id,
                'department' => $employee->department_id,
                'branch' => $employee->branch_id,
            ],
        ])->all();
    }
}
