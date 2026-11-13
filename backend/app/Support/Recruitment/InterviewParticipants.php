<?php

namespace App\Support\Recruitment;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who sits in on a candidate's interview (REC-INT).
 *
 * The direct superior (Atasan langsung) always does: they come from the requisition, so they follow it when HR names a
 * different one. Besides them HR chooses, per candidate, from a short list: the HR admins of the company and the users with
 * the Manajemen role. An invitation made before participants could be chosen has no such list, and keeps the old
 * arrangement: every HR admin and the direct superior.
 */
class InterviewParticipants
{
    /**
     * The direct superior, when the requisition names one who has an account.
     */
    public function superior(Requisition $requisition): ?User
    {
        return Employee::acrossCompanies()->whereKey($requisition->manager_employee_id)->first()?->user;
    }

    /**
     * What the form's list offers: HR admins and Manajemen of the requisition's company, active accounts only, without the
     * direct superior (who is in already). A person who is both is listed once, as HR.
     *
     * @return list<array{id: int, name: string, group: 'hr'|'management'}>
     */
    public function options(Requisition $requisition): array
    {
        $superiorId = $this->superior($requisition)?->id;

        $options = [];

        $hr = User::query()
            ->where('is_active', true)
            ->permission(Permission::ManageRecruitment->value)
            ->accessingCompany($requisition->company_id)
            ->get();

        foreach ($hr as $user) {
            $options[$user->id] = ['id' => $user->id, 'name' => $user->name, 'group' => 'hr'];
        }

        $management = User::role(Role::Management->value)
            ->where('is_active', true)
            ->accessingCompany($requisition->company_id)
            ->get();

        foreach ($management as $user) {
            $options[$user->id] ??= ['id' => $user->id, 'name' => $user->name, 'group' => 'management'];
        }

        unset($options[$superiorId]);

        $list = array_values($options);
        usort($list, fn (array $a, array $b): int => [$a['group'] === 'hr' ? 0 : 1, $a['name']] <=> [$b['group'] === 'hr' ? 0 : 1, $b['name']]);

        return $list;
    }

    /**
     * @return list<int>
     */
    public function optionIds(Requisition $requisition): array
    {
        return array_column($this->options($requisition), 'id');
    }

    /**
     * Whether HR has chosen participants for this candidate, as opposed to an invitation that predates the choice.
     */
    public function hasChosen(Candidate $candidate): bool
    {
        return $candidate->interviewers->isNotEmpty();
    }

    /**
     * Whether this person is one of the people HR chose for the candidate.
     */
    public function chose(Candidate $candidate, User $user): bool
    {
        return $candidate->interviewers->contains('id', $user->id);
    }

    /**
     * The people on the calendar event: those chosen and the direct superior. Before participants could be chosen the
     * event named the superior alone, and it still does.
     *
     * @return Collection<int, User>
     */
    public function attendees(Candidate $candidate, Requisition $requisition): Collection
    {
        return $this->withSuperior($candidate->interviewers, $requisition);
    }

    /**
     * Who is told about the interview and chased for the evaluation: those chosen and the direct superior; for an older
     * invitation, every HR admin of the company and the superior.
     *
     * @return Collection<int, User>
     */
    public function recipients(Candidate $candidate, Requisition $requisition): Collection
    {
        $people = $this->hasChosen($candidate)
            ? $candidate->interviewers
            : User::role(Role::HrAdmin->value)->where('is_active', true)->get()
                ->filter(fn (User $user): bool => $user->canAccessCompany($requisition->company_id));

        return $this->withSuperior($people, $requisition)->filter(fn (User $user): bool => $user->is_active)->values();
    }

    /**
     * @param  iterable<int, User>  $people
     * @return Collection<int, User>
     */
    private function withSuperior(iterable $people, Requisition $requisition): Collection
    {
        $superior = $this->superior($requisition);

        return collect($people)
            ->when($superior instanceof User, fn (Collection $users) => $users->push($superior))
            ->unique('id')
            ->values();
    }
}
