<?php

namespace App\Support\Offboarding;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who is concerned with an offboarding case, for the notices a change of plan has to reach: whoever made
 * it, whoever approved it, the leaver's Atasan, and the owners of the checklist. The person leaving is
 * never among them.
 */
class OffboardingAudience
{
    /**
     * Management as it concerns this case: the account the approval was bound to, or every Management
     * account of the company when it was open to any of them.
     *
     * @return Collection<int, User>
     */
    public function management(OffboardingCase $case): Collection
    {
        $bound = $case->workflow?->steps()->whereNotNull('approver_user_id')->pluck('approver_user_id')->all() ?? [];

        $users = $bound !== []
            ? User::query()->whereIn('id', $bound)->get()
            : User::role(Role::Management->value)->get()->filter(fn (User $user): bool => $user->canAccessCompany($case->company_id))->values();

        return $this->alive($case, $users);
    }

    public function atasan(OffboardingCase $case): ?User
    {
        $leaver = Employee::acrossCompanies()->with('manager.user')->whereKey($case->employee_id)->first();
        $user = $leaver?->manager?->user;

        if ($user === null || ! $user->is_active || $user->is($leaver->user) || ! $user->canAccessCompany($case->company_id)) {
            return null;
        }

        return $user;
    }

    /**
     * @return Collection<int, User>
     */
    public function owners(OffboardingCase $case): Collection
    {
        return $this->alive($case, $case->checklistDepartments()->with('owner')->get()->pluck('owner')->filter());
    }

    /**
     * Everyone above: the requester, Management, the Atasan and the owners.
     *
     * @return Collection<int, User>
     */
    public function everyone(OffboardingCase $case): Collection
    {
        $case->loadMissing('creator');

        return $this->alive($case, collect([$case->creator, $this->atasan($case)])
            ->merge($this->management($case))
            ->merge($this->owners($case))
            ->filter());
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function alive(OffboardingCase $case, Collection $users): Collection
    {
        $leaver = Employee::acrossCompanies()->whereKey($case->employee_id)->value('user_id');

        return $users->unique('id')->reject(fn (User $user): bool => ! $user->is_active || $user->id === $leaver)->values();
    }
}
