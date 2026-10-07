<?php

namespace App\Support;

use App\Models\Employee;
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
     * The downline plus the manager themselves — "my team including me", which is what most
     * screens actually want.
     *
     * @return list<int>
     */
    public function scopeIds(Employee $manager): array
    {
        return [$manager->id, ...$this->downlineIds($manager)];
    }

    public function manages(Employee $manager, Employee $candidate): bool
    {
        return in_array($candidate->id, $this->downlineIds($manager), strict: true);
    }
}
