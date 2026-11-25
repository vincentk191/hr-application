<?php

namespace App\Support\Attendance;

use App\Enums\Zona;
use App\Support\AppSettings;

/**
 * The attendance deduction (potongan) for a period: the Zona's daily rate times the days of Alpa and
 * Izin, plus half the rate for each Telat. Sakit is not deducted.
 *
 * The rates and exemptions are edited by an administrator in the admin panel (Potongan absensi) and stored in the
 * database; config/attendance.php supplies the defaults until someone does. They are read once per instance, so a
 * long import or recap uses one consistent set.
 */
class PotonganCalculator
{
    public const KEY_RATES = 'attendance.potongan_rates';

    public const KEY_EXEMPT_POSITIONS = 'attendance.potongan_exempt_positions';

    public const KEY_EXEMPT_PERIODS = 'attendance.potongan_exempt_periods';

    /**
     * @var array{rates: array<string, int>, exempt_positions: list<string>, exempt_periods: list<string>}|null
     */
    private ?array $resolved = null;

    /**
     * What applies now: what an administrator saved, else the config defaults. A list saved empty means nobody is
     * exempt, and is not the same as never having saved, which falls back to the config default.
     *
     * @return array{rates: array<string, int>, exempt_positions: list<string>, exempt_periods: list<string>}
     */
    public function settings(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $store = app(AppSettings::class);
        $defaults = (array) config('attendance.potongan', []);
        $saved = (array) $store->get(self::KEY_RATES, []);

        $rates = [];

        foreach (Zona::cases() as $zona) {
            $rates[$zona->value] = (int) ($saved[$zona->value] ?? $defaults['rates'][$zona->value] ?? 0);
        }

        return $this->resolved = [
            'rates' => $rates,
            'exempt_positions' => $this->listOf($store->get(self::KEY_EXEMPT_POSITIONS, $defaults['exempt_positions'] ?? [])),
            'exempt_periods' => $this->listOf($store->get(self::KEY_EXEMPT_PERIODS, $defaults['exempt_periods'] ?? [])),
        ];
    }

    public function rateFor(Zona $zona): int
    {
        return $this->settings()['rates'][$zona->value];
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

        foreach ($this->settings()[$list] as $entry) {
            $entry = self::normalise((string) $entry);

            if ($exact ? $text === $entry : str_contains($text, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function listOf(mixed $entries): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $entry): string => trim((string) $entry),
            is_array($entries) ? $entries : [],
        ), fn (string $entry): bool => $entry !== ''));
    }

    /**
     * Upper case, with anything that is not a letter or digit collapsed to single spaces.
     */
    public static function normalise(?string $text): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper((string) $text)));
    }
}
