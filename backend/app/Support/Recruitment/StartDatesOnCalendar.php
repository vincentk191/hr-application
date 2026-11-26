<?php

namespace App\Support\Recruitment;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Access\Entrance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The target start dates of roles still open, shown on the attendance calendar to the two people who answer for them:
 * HR (`manage-recruitment`) sees every open requisition of the company in view, and a new hire's direct superior (Atasan
 * langsung) the ones that name them.
 *
 * Read from the requisitions rather than stored as calendar events. A calendar event makes its day a non-working day for
 * everyone it covers and is seen by the whole company; a start date is neither. Because nothing is stored, a changed
 * date, a filled role or a cancelled one is reflected at once. Nothing is shown on the public entrance: this is
 * recruitment work, which is private-entrance only.
 */
class StartDatesOnCalendar
{
    /**
     * @return list<array{id: int, title: string, reference: string, date: string}>
     */
    public function for(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (app(Entrance::class)->isPublic()) {
            return [];
        }

        $isHr = $user->can(Permission::ManageRecruitment->value);
        $own = $isHr ? null : Employee::acrossCompanies()->where('user_id', $user->id)->value('id');

        if (! $isHr && $own === null) {
            return [];
        }

        return Requisition::query()->open()
            ->whereDate('target_start_on', '>=', $from->toDateString())
            ->whereDate('target_start_on', '<=', $to->toDateString())
            ->when(! $isHr, fn (Builder $query) => $query->where('manager_employee_id', $own))
            ->orderBy('target_start_on')
            ->orderBy('id')
            ->get()
            ->map(fn (Requisition $requisition): array => [
                'id' => $requisition->id,
                'title' => $requisition->position_name,
                'reference' => $requisition->reference(),
                'date' => $requisition->target_start_on->toDateString(),
            ])
            ->values()
            ->all();
    }
}
