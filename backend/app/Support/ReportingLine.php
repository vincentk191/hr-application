<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Support\Access\Entrance;
use Illuminate\Support\Facades\DB;

/**
 * Walks the Management → Supervisor → Employee line (CORE-RBA-004, context.md §9.2).
 *
 * A supervisor sees their own downline, Management sees its scope. Approval routing uses the
 * line directly today; when the Knowledge Hub lands, SOP visibility resolves through the same
 * walk (context.md Appendix B).
 *
 * Implemented as a recursive CTE rather than loading the tree into PHP, because the "everyone
 * under this person" question is asked per request and the tree is as deep as the org chart.
 */
class ReportingLine
{
    /**
     * Every employee below this one, at any depth.
     *
     * Constrained to the manager's own company explicitly: a raw CTE bypasses CompanyScope, so
     * without this a cycle through a mis-set manager_id could walk into another company.
     *
     * @return list<int>
     */
    public function downlineIds(Employee $manager): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE downline AS (
                SELECT id FROM employees WHERE manager_id = ? AND company_id = ?
                UNION
                SELECT e.id
                FROM employees e
                INNER JOIN downline d ON e.manager_id = d.id
                WHERE e.company_id = ?
            )
            SELECT id FROM downline
        SQL, [$manager->id, $manager->company_id, $manager->company_id]);

        // DB::select() hands back plain stdClass rows, whose properties PHPStan cannot know.
        // Casting each row to an array makes the lookup something it can type, without
        // asserting a shape over the top of it.
        return array_values(array_map(
            static fn (object $row): int => (int) ((array) $row)['id'],
            $rows,
        ));
    }

    /**
     * Every employee below this one at any depth, in whichever company they work. For a supervisor whose team spans
     * companies; `downlineIds()` stays inside the manager's own company, which is what every other caller wants.
     *
     * @return list<int>
     */
    public function downlineIdsAcrossCompanies(Employee $manager): array
    {
        return array_map(static fn (array $row): int => $row['id'], $this->walkAcrossCompanies($manager));
    }

    /**
     * The companies the people below this employee work in, whichever they are.
     *
     * @return list<int>
     */
    public function downlineCompanyIds(Employee $manager): array
    {
        return array_values(array_unique(array_map(static fn (array $row): int => $row['company_id'], $this->walkAcrossCompanies($manager))));
    }

    /**
     * @return list<array{id: int, company_id: int}>
     */
    private function walkAcrossCompanies(Employee $manager): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE downline AS (
                SELECT id, company_id FROM employees WHERE manager_id = ?
                UNION
                SELECT e.id, e.company_id
                FROM employees e
                INNER JOIN downline d ON e.manager_id = d.id
            )
            SELECT id, company_id FROM downline
        SQL, [$manager->id]);

        return array_values(array_map(static function (object $row): array {
            $values = (array) $row;

            return ['id' => (int) $values['id'], 'company_id' => (int) $values['company_id']];
        }, $rows));
    }

    /**
     * The downline plus the manager themselves — "my team including me", which is what most
     * screens actually want.
     *
     * @return list<int>
     */
    public function scopeIds(Employee $manager): array
    {
        return [$manager->id, ...$this->downlineIds($manager)];
    }

    /**
     * The employees this user may see (CORE-RBA-004): themselves and everyone below them, at any
     * depth; never a colleague on their own level or anyone above. Null means "not limited" for a
     * holder of `view-all-employees`, whose reach is then the company scope alone. An account with
     * no employee record behind it sees nobody.
     *
     * @return list<int>|null
     */
    public function visibleIds(User $user): ?array
    {
        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        // On the public entrance a person sees themselves only, whatever their place in the line.
        if (app(Entrance::class)->isPublic()) {
            return $own === null ? [] : [$own->id];
        }

        if ($user->can(Permission::ViewAllEmployees->value)) {
            return null;
        }

        if ($own === null) {
            return [];
        }

        // A supervisor's team may span companies; everyone else's line stays within their own.
        return $user->reachesAcrossCompanies()
            ? [$own->id, ...$this->downlineIdsAcrossCompanies($own)]
            : $this->scopeIds($own);
    }

    /**
     * Whether anyone reports to this user's own employee record, which is what earns an ordinary
     * employee a view of their team without any role.
     */
    public function hasReports(User $user): bool
    {
        if (app(Entrance::class)->isPublic()) {
            return false;
        }

        $own = Employee::acrossCompanies()->where('user_id', $user->id)->value('id');

        return $own !== null && Employee::acrossCompanies()->where('manager_id', $own)->exists();
    }

    public function manages(Employee $manager, Employee $candidate): bool
    {
        return in_array($candidate->id, $this->downlineIds($manager), strict: true);
    }

    /**
     * The nearest active account above this employee, walking up the managers, that holds the role and can reach
     * the company: who decides for them when a flow is bound to the line (an offboarding case's or a
     * requisition's Management). Null when nobody up the line qualifies; the flow then falls back to anyone with
     * the role. Never `$excluding` (the person the flow is about, or the requester), and a loop in the managers
     * ends the walk instead of running for ever.
     */
    public function nearestAbove(Employee $from, Role $role, int $companyId, ?User $excluding = null): ?User
    {
        $seen = [$from->id];
        $current = $from->manager_id === null ? null : Employee::acrossCompanies()->with('user')->find($from->manager_id);

        while ($current !== null && ! in_array($current->id, $seen, strict: true)) {
            $seen[] = $current->id;
            $user = $current->user;

            if ($user !== null && $user->is_active && $user->isA($role) && $user->canAccessCompany($companyId)
                && ($excluding === null || $user->isNot($excluding))) {
                return $user;
            }

            $current = $current->manager_id === null ? null : Employee::acrossCompanies()->with('user')->find($current->manager_id);
        }

        return null;
    }
}
