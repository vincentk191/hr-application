<?php

namespace App\Support\Attendance;

use App\Enums\Zona;

/**
 * The attendance deduction (potongan) for a period: the Zona's daily rate times the days of Alpa and
 * Izin, plus half the rate for each Telat. Sakit is not deducted. Rates and exemptions are in
 * config/attendance.php.
 */
class PotonganCalculator
{
    public function rateFor(Zona $zona): int
    {
        return (int) config("attendance.potongan.rates.{$zona->value}", 0);
    }

    public function amount(Zona $zona, int $alpa, int $izin, int $telat): int
    {
        $rate = $this->rateFor($zona);

        return $rate * ($alpa + $izin) + intdiv($rate * $telat, 2);
    }

    /**
     * Whether nothing is deducted: the person's position or the period's label is on the exempt lists.
     */
    public function isExempt(?string $position, ?string $periodLabel): bool
    {
        return $this->listed($position, 'exempt_positions', exact: true) || $this->listed($periodLabel, 'exempt_periods', exact: false);
    }

    /**
     * The amount, or zero for someone exempt.
     */
    public function forRow(Zona $zona, int $alpa, int $izin, int $telat, ?string $position, ?string $periodLabel): int
    {
        return $this->isExempt($position, $periodLabel) ? 0 : $this->amount($zona, $alpa, $izin, $telat);
    }

    /**
     * A position has to be exactly one of the listed ones ("Asisten Supervisor" is not a Supervisor);
     * a period only has to mention one ("SETAHUN 2026").
     */
    private function listed(?string $text, string $list, bool $exact): bool
    {
        $text = self::normalise($text);

        if ($text === '') {
            return false;
        }

        foreach ((array) config("attendance.potongan.{$list}", []) as $entry) {
            $entry = self::normalise((string) $entry);

            if ($exact ? $text === $entry : str_contains($text, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upper case, with anything that is not a letter or digit collapsed to single spaces.
     */
    public static function normalise(?string $text): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper((string) $text)));
    }
}
