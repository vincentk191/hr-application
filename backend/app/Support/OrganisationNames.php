<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Position;

/**
 * Department and position names for list filters, where a name stands for every company's
 * department of that name.
 *
 * Departments and positions belong to one company each, so "Gudang" exists once per company. A
 * filter that listed every row would show the same name once per company when companies are
 * viewed combined. Filtering by name instead lists each once, applies across companies, and keeps
 * its choice when the company switcher changes, so the company filter and this one compose.
 */
final class OrganisationNames
{
    /**
     * The department names in the active company scope, each once.
     *
     * @return list<string>
     */
    public static function departments(): array
    {
        return Department::query()->distinct()->orderBy('name')->pluck('name')->all();
    }

    /**
     * The position names in the active company scope, each once.
     *
     * @return list<string>
     */
    public static function positions(): array
    {
        return Position::query()->distinct()->orderBy('name')->pluck('name')->all();
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
