<?php

namespace App\Support\Attendance;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who the attendance screens consider for a date range: employees who were employed at any point
 * in it, optionally narrowed by department and a name or number search.
 *
 * Read through the company scope, so it is the active company, or every company in the combined
 * view. The calendar and the issues screen both use it, so they always look at the same people.
 */
class EmployeeRoster
{
    /**
     * @param  list<int>  $departments
     * @return Collection<int, Employee>
     */
    public function between(CarbonInterface $from, CarbonInterface $to, array $departments = [], string $search = ''): Collection
    {
        return Employee::query()
            ->with('department:id,name')
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $to->toDateString()))
            ->where(fn (Builder $query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $from->toDateString()))
            ->when($departments !== [], fn (Builder $query) => $query->whereIn('department_id', $departments))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%"),
            ))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number', 'department_id', 'joined_on', 'exited_on']);
    }

    /**
     * The employment window of each person, in the shape DailyAttendance takes.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array{joined: string|null, exited: string|null}>
     */
    public function windows(Collection $employees): array
    {
        return $employees->mapWithKeys(fn (Employee $employee): array => [
            $employee->id => [
                'joined' => $employee->joined_on?->toDateString(),
                'exited' => $employee->exited_on?->toDateString(),
            ],
        ])->all();
    }
}
