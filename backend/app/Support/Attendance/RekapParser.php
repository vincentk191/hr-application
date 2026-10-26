<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads a branch's "REKAPITULASI ABSEN KARYAWAN" sheet: a few labelled cells on top (Cabang, Periode and
 * the dates the period runs), then a table with NAMA, JABATAN / BAGIAN, ZONA, ALPA, IZIN, SAKIT, TELAT and
 * TOTAL POTONGAN, ended by a JUMLAH row.
 *
 * Columns are found by their headings, not their position, so a branch that adds or moves a column still
 * reads. Dates are day-first (21/09/2026 s.d 20/10/2026).
 */
class RekapParser
{
    private const MONTHS = [
        'JANUARI' => 1, 'FEBRUARI' => 2, 'MARET' => 3, 'APRIL' => 4, 'MEI' => 5, 'JUNI' => 6,
        'JULI' => 7, 'AGUSTUS' => 8, 'SEPTEMBER' => 9, 'OKTOBER' => 10, 'NOVEMBER' => 11, 'DESEMBER' => 12,
    ];

    private const HEADER_SEARCH_ROWS = 60;

    public function __construct(private SpreadsheetReader $reader) {}

    /**
     * @throws InvalidRekap when the file is not a readable rekapitulasi
     */
    public function parseFile(string $path): ParsedRekap
    {
        $format = $this->reader->formatOf($path);

        if ($format === null) {
            throw new InvalidRekap('Berkas harus berupa Excel (.xls atau .xlsx).');
        }

        try {
            $rows = $this->reader->rows($path, $format);
        } catch (InvalidScanLog $exception) {
            throw new InvalidRekap($exception->getMessage());
        }

        return $this->parse($rows);
    }

    /**
     * @param  array<int, list<mixed>>  $rows  the sheet's rows, keyed by 1-based row number
     *
     * @throws InvalidRekap
     */
    public function parse(array $rows): ParsedRekap
    {
        [$headerRow, $columns] = $this->findHeader($rows);

        $above = array_filter($rows, fn (int $number): bool => $number < $headerRow, ARRAY_FILTER_USE_KEY);
        $branchName = $this->valueNextTo($above, 'CABANG');
        $periodLabel = $this->valueNextTo($above, 'PERIODE');
        [$start, $end] = $this->period($above, $periodLabel);

        $lines = [];

        foreach ($rows as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }

            if ($this->isTotalRow($cells)) {
                break;
            }

            $name = $this->text($cells[$columns['name']] ?? null);

            if ($name === null) {
                continue;
            }

            $lines[] = [
                'number' => $number,
                'name' => $name,
                'position' => isset($columns['position']) ? $this->text($cells[$columns['position']] ?? null) : null,
                'zona' => isset($columns['zona']) ? $this->text($cells[$columns['zona']] ?? null) : null,
                'alpa' => $this->count($cells[$columns['alpa']] ?? null, 'ALPA', $number),
                'izin' => $this->count($cells[$columns['izin']] ?? null, 'IZIN', $number),
                'sakit' => $this->count($cells[$columns['sakit']] ?? null, 'SAKIT', $number),
                'telat' => $this->count($cells[$columns['telat']] ?? null, 'TELAT', $number),
                'potongan' => isset($columns['potongan']) ? $this->amount($cells[$columns['potongan']] ?? null) : null,
            ];
        }

        if ($lines === []) {
            throw new InvalidRekap('Tidak ada baris karyawan di bawah judul kolom.');
        }

        return new ParsedRekap($branchName, $periodLabel, $start, $end, $lines);
    }

    /**
     * The heading row and the column each field sits in.
     *
     * @param  array<int, list<mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}
     *
     * @throws InvalidRekap
     */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, self::HEADER_SEARCH_ROWS, true) as $number => $cells) {
            $columns = [];

            foreach ($cells as $index => $cell) {
                $heading = PotonganCalculator::normalise($this->text($cell));

                match (true) {
                    $heading === 'NAMA' => $columns['name'] ??= $index,
                    str_starts_with($heading, 'JABATAN') => $columns['position'] ??= $index,
                    $heading === 'ZONA' => $columns['zona'] ??= $index,
                    $heading === 'ALPA' => $columns['alpa'] ??= $index,
                    $heading === 'IZIN' => $columns['izin'] ??= $index,
                    $heading === 'SAKIT' => $columns['sakit'] ??= $index,
                    $heading === 'TELAT' => $columns['telat'] ??= $index,
                    str_contains($heading, 'POTONGAN') => $columns['potongan'] ??= $index,
                    default => null,
                };
            }

            if (isset($columns['name'], $columns['alpa'])) {
                $missing = array_values(array_diff(['izin', 'sakit', 'telat'], array_keys($columns)));

                if ($missing !== []) {
                    throw new InvalidRekap('Kolom '.strtoupper(implode(', ', $missing)).' tidak ditemukan pada judul kolom.');
                }

                return [$number, $columns];
            }
        }

        throw new InvalidRekap('Judul kolom rekapitulasi (NAMA, ALPA, IZIN, SAKIT, TELAT) tidak ditemukan. Pastikan ini berkas rekapitulasi absen cabang.');
    }

    /**
     * The first filled cell to the right of a label such as "Cabang".
     *
     * @param  array<int, list<mixed>>  $rows
     */
    private function valueNextTo(array $rows, string $label): ?string
    {
        foreach ($rows as $cells) {
            foreach ($cells as $index => $cell) {
                if (PotonganCalculator::normalise($this->text($cell)) !== $label) {
                    continue;
                }

                foreach (array_slice($cells, $index + 1) as $next) {
                    $value = $this->text($next);

                    if ($value !== null) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The dates the period runs, from a "21/09/2026 s.d 20/10/2026" cell, or failing that the whole
     * month named in Periode ("OKTOBER 2026").
     *
     * @param  array<int, list<mixed>>  $above
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws InvalidRekap
     */
    private function period(array $above, ?string $periodLabel): array
    {
        $pattern = '#(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})\s*(?:s\s*\.?\s*d\.?|sampai|hingga|-|–|to)\s*(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})#iu';

        foreach ($above as $cells) {
            foreach ($cells as $cell) {
                if (preg_match($pattern, (string) $this->text($cell), $found) !== 1) {
                    continue;
                }

                try {
                    $start = CarbonImmutable::createFromDate((int) $found[3], (int) $found[2], (int) $found[1])->startOfDay();
                    $end = CarbonImmutable::createFromDate((int) $found[6], (int) $found[5], (int) $found[4])->startOfDay();
                } catch (Throwable) {
                    throw new InvalidRekap("Tanggal periode \"{$found[0]}\" tidak valid.");
                }

                return $this->checked($start, $end);
            }
        }

        $label = PotonganCalculator::normalise($periodLabel);

        foreach (self::MONTHS as $name => $month) {
            if (preg_match('/\b'.$name.'\s+(\d{4})\b/', $label, $found) === 1) {
                $start = CarbonImmutable::createFromDate((int) $found[1], $month, 1)->startOfDay();

                return $this->checked($start, $start->endOfMonth()->startOfDay());
            }
        }

        throw new InvalidRekap('Rentang tanggal periode tidak ditemukan. Isi sel "Tanggal" dengan bentuk 21/09/2026 s.d 20/10/2026.');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws InvalidRekap
     */
    private function checked(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($end->lt($start)) {
            throw new InvalidRekap('Tanggal akhir periode sebelum tanggal awalnya.');
        }

        if ($start->diffInDays($end) > 366) {
            throw new InvalidRekap('Periode lebih dari satu tahun. Periksa tanggal pada berkas.');
        }

        return [$start, $end];
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isTotalRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (PotonganCalculator::normalise($this->text($cell)) === 'JUMLAH') {
                return true;
            }
        }

        return false;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * A day count: empty is none, anything else must be a whole non-negative number.
     *
     * @throws InvalidRekap
     */
    private function count(mixed $value, string $column, int $row): int
    {
        $text = $this->text($value);

        if ($text === null) {
            return 0;
        }

        if (! is_numeric($text) || (float) $text < 0 || (float) $text > 366 || (float) $text != floor((float) $text)) {
            throw new InvalidRekap("Baris {$row}: kolom {$column} harus berisi bilangan bulat, bukan \"{$text}\".");
        }

        return (int) $text;
    }

    private function amount(mixed $value): ?int
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/[^\d]/', '', $text);

        return is_numeric($text) ? max(0, (int) round((float) $text)) : ($digits === '' ? null : (int) $digits);
    }
}
