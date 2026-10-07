<?php

namespace App\Enums;

/**
 * The leave types from the 2026-09-29 design (context.md §11.2).
 *
 * No per-type caps are modelled: the spec tracks leave taken, not an entitlement balance
 * (context.md §10).
 */
enum LeaveType: string
{
    case Sick = 'sick';
    case Annual = 'annual';
    case Maternity = 'maternity';
    case Marriage = 'marriage';
    case Bereavement = 'bereavement';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Cuti sakit',
            self::Annual => 'Cuti tahunan',
            self::Maternity => 'Cuti melahirkan',
            self::Marriage => 'Pernikahan / lamaran',
            self::Bereavement => 'Cuti duka',
            self::Unpaid => 'Cuti tanpa gaji',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
