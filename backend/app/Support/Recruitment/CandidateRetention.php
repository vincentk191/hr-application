<?php

namespace App\Support\Recruitment;

use App\Support\AppSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How long a rejected candidate's record is kept (decided 2026-11-17): the whole record is deleted this many months after
 * the rejection. Set on the admin page "Penyimpanan data pelamar"; twelve months until someone changes it.
 */
class CandidateRetention
{
    public const KEY = 'recruitment.rejected_retention_months';

    public const DEFAULT_MONTHS = 12;

    public const MIN_MONTHS = 1;

    public const MAX_MONTHS = 120;

    public function __construct(private AppSettings $settings) {}

    public function months(): int
    {
        $saved = $this->settings->get(self::KEY);

        return is_numeric($saved) && (int) $saved >= self::MIN_MONTHS && (int) $saved <= self::MAX_MONTHS
            ? (int) $saved
            : self::DEFAULT_MONTHS;
    }

    public function save(int $months): void
    {
        $this->settings->set(self::KEY, max(self::MIN_MONTHS, min(self::MAX_MONTHS, $months)));
    }

    /**
     * A candidate rejected before this moment has outlived the retention period.
     */
    public function cutoff(?CarbonInterface $now = null): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->subMonthsNoOverflow($this->months());
    }
}
