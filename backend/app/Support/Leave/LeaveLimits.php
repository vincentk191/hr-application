<?php

namespace App\Support\Leave;

use App\Models\RequestType;

/**
 * The longest a single request of each type may be, as set by HR on the type itself in the admin
 * panel (Jenis pengajuan).
 *
 * A type with no limit has none: the design tracks leave taken, not an entitlement, and nothing is
 * invented here. The limit is per request, counted the way the request counts its days.
 */
class LeaveLimits
{
    public function maxDays(string $code): ?int
    {
        $value = RequestType::query()->where('code', $code)->value('max_days');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * How many calendar days back a request of this type may start, or null when it may not start
     * before today (REQ-WIZ-014).
     */
    public function backDays(string $code): ?int
    {
        $value = RequestType::query()->where('code', $code)->value('max_back_days');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function set(string $code, ?int $days): void
    {
        RequestType::query()->where('code', $code)->update(['max_days' => $days !== null && $days > 0 ? $days : null]);
    }

    /**
     * The types a form may offer, each with its limit: the ones switched on, in the administrators'
     * order, plus the one a request being corrected already has even if it has since been switched off.
     *
     * @return array<int, array{value: string, label: string, maxDays: int|null, backDays: int|null}>
     */
    public function options(?string $alsoCode = null): array
    {
        return RequestType::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($alsoCode !== null, fn ($inner) => $inner->orWhere('code', $alsoCode)))
            ->ordered()
            ->get()
            ->map(fn (RequestType $type): array => [
                'value' => $type->code,
                'label' => $type->label,
                'maxDays' => $type->max_days > 0 ? $type->max_days : null,
                'backDays' => $type->max_back_days > 0 ? $type->max_back_days : null,
            ])
            ->values()
            ->all();
    }
}
