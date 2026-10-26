<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;

/**
 * What was read from a branch rekapitulasi file, before anything is matched to employees.
 *
 * @phpstan-type RekapLine array{number: int, name: string, position: string|null, zona: string|null, alpa: int, izin: int, sakit: int, telat: int, potongan: int|null}
 */
final class ParsedRekap
{
    /**
     * @param  list<RekapLine>  $lines
     */
    public function __construct(
        public readonly ?string $branchName,
        public readonly ?string $periodLabel,
        public readonly CarbonImmutable $periodStart,
        public readonly CarbonImmutable $periodEnd,
        public readonly array $lines,
    ) {}
}
