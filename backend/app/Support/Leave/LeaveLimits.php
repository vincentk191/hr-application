<?php

namespace App\Support\Leave;

use App\Enums\LeaveType;
use App\Support\AppSettings;

/**
 * The longest a single request of each leave type may be, as set by HR in the admin panel.
 *
 * A type with no limit set has none: the earlier design tracked leave taken, not an entitlement, and
 * nothing is invented here. The limit is per request, counted the way the request counts its days.
 */
class LeaveLimits
{
    public function __construct(private AppSettings $settings) {}

    public static function key(LeaveType $type): string
    {
        return 'leave.max_days.'.$type->value;
    }

    public function maxDays(LeaveType $type): ?int
    {
        $value = $this->settings->get(self::key($type));

        return is_int($value) && $value > 0 ? $value : null;
    }

    public function set(LeaveType $type, ?int $days): void
    {
        $this->settings->set(self::key($type), $days !== null && $days > 0 ? $days : null);
    }

    /**
     * The leave types for a form, each with its limit.
     *
     * @return list<array{value: string, label: string, maxDays: int|null}>
     */
    public function options(): array
    {
        return array_map(
            fn (LeaveType $type): array => ['value' => $type->value, 'label' => $type->label(), 'maxDays' => $this->maxDays($type)],
            LeaveType::cases(),
        );
    }
}
