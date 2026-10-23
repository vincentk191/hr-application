<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;

/**
 * Cabang, department and position names for list filters, where a name stands for every company's
 * record of that name.
 *
 * Cabang, departments and positions belong to one company each, so "Gudang" exists once per company. A
 * filter that listed every row would show the same name once per company when companies are
 * viewed combined. Filtering by name instead lists each once, applies across companies, and keeps
 * its choice when the company switcher changes, so the company filter and this one compose.
 */
final class OrganisationNames
{
    /**
     * The Cabang names in the active company scope, each once, limited like the departments.
     *
     * @return list<string>
     */
    public static function branches(?User $viewer = null): array
    {
        $query = Branch::query();

        if ($viewer !== null && self::isLimited($viewer)) {
            $query->whereIn('id', Employee::query()->visibleTo($viewer)->select('branch_id'));
        }

        return array_values(array_map(
            fn (mixed $name): string => (string) $name,
            $query->distinct()->orderBy('name')->pluck('name')->all(),
        ));
    }

    /**
     * The department names in the active company scope, each once. Given a viewer, only those of
     * the people they may see, so a filter never names a department they cannot reach.
     *
     * @return list<string>
     */
    public static function departments(?User $viewer = null): array
    {
        $query = Department::query();

        if ($viewer !== null && self::isLimited($viewer)) {
            $query->whereIn('id', Employee::query()->visibleTo($viewer)->select('department_id'));
        }

        return array_values(array_map(
            fn (mixed $name): string => (string) $name,
            $query->distinct()->orderBy('name')->pluck('name')->all(),
        ));
    }

    /**
     * The position names in the active company scope, each once, limited like the departments.
     *
     * @return list<string>
     */
    public static function positions(?User $viewer = null): array
    {
        $query = Position::query();

        if ($viewer !== null && self::isLimited($viewer)) {
            $query->whereIn('id', Employee::query()->visibleTo($viewer)->select('position_id'));
        }

        return array_values(array_map(
            fn (mixed $name): string => (string) $name,
            $query->distinct()->orderBy('name')->pluck('name')->all(),
        ));
    }

    /**
     * Whether the viewer sees only part of the company, so the lists must follow suit.
     */
    private static function isLimited(User $viewer): bool
    {
        return app(ReportingLine::class)->visibleIds($viewer) !== null;
    }

    /**
     * Names as read from a query string: strings only, trimmed, no blanks or repeats.
     *
     * @return list<string>
     */
    public static function fromInput(mixed $value): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $name): string => is_string($name) ? trim($name) : '', (array) $value),
            fn (string $name): bool => $name !== '',
        )));
    }
}
