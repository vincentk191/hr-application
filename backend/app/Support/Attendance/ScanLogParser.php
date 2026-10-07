<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use SplFileObject;

/**
 * Reads a scanner log exported as CSV into normalised punches.
 *
 * Personnel's export format has not been verified yet, so this does not hard-code one layout. It
 * reads the header row and recognises the common names for each column, in English and
 * Indonesian, and accepts a comma, semicolon, tab or pipe delimiter. When a real export is
 * available and differs, the place to adapt is the alias lists below; nothing else depends on
 * the file's shape.
 *
 * Required: a PIN column and either one date-time column or a date column plus a time column.
 * Optional: the device serial, verify mode and in/out mode.
 */
class ScanLogParser
{
    /**
     * Header names (lower-cased, punctuation removed) that identify each column.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'pin' => ['pin', 'enrollnumber', 'enrollno', 'enrollid', 'enrolid', 'userid', 'id', 'noid', 'nomorid', 'idkaryawan'],
        'datetime' => ['scandate', 'scanat', 'datetime', 'scantime', 'timestamp', 'waktu', 'waktuscan', 'tanggalwaktu', 'tanggaldanwaktu'],
        'date' => ['date', 'tanggal', 'tgl', 'tanggalscan'],
        'time' => ['time', 'jam', 'jamscan'],
        'sn' => ['sn', 'serial', 'serialno', 'serialnumber', 'devicesn', 'deviceserial', 'nomorseri'],
        'verify' => ['verifymode', 'verify', 'verifikasi'],
        'inout' => ['inoutmode', 'inout', 'statuspresensi', 'mode'],
    ];

    /**
     * Date-time layouts tried in order. Day-first only: a scanner in Indonesia never writes
     * month-first, and guessing would silently swap day and month on the first 12 days.
     *
     * @var list<string>
     */
    private const FORMATS = [
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y/m/d H:i:s', 'Y/m/d H:i',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'j/n/Y H:i:s', 'j/n/Y H:i',
        'd-m-Y H:i:s', 'd-m-Y H:i', 'j-n-Y H:i:s', 'j-n-Y H:i',
        'd.m.Y H:i:s', 'd.m.Y H:i',
    ];

    /**
     * @return array{
     *     total: int,
     *     rows: list<array{sn: string|null, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>,
     *     errors: list<string>
     * }
     *
     * @throws InvalidScanLog when the file has no header this parser can read
     */
    public function parse(string $path, string $timezone): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $header = null;
        $columns = [];
        $delimiter = ',';
        $lineNumber = 0;
        $total = 0;
        $rows = [];
        $errors = [];

        foreach ($file as $line) {
            $lineNumber++;

            if (! is_string($line) || trim($line) === '') {
                continue;
            }

            if ($header === null) {
                $line = $this->stripBom($line);
                $delimiter = $this->detectDelimiter($line);
                $header = str_getcsv($line, $delimiter, '"', '');
                $columns = $this->mapColumns($header);

                continue;
            }

            $total++;
            $cells = str_getcsv($line, $delimiter, '"', '');

            $row = $this->parseRow($cells, $columns, $timezone, $error);

            if ($row === null) {
                $errors[] = "Baris {$lineNumber}: {$error}";

                continue;
            }

            $rows[] = $row;
        }

        if ($header === null) {
            throw new InvalidScanLog('Berkas kosong.');
        }

        return ['total' => $total, 'rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     *
     * @throws InvalidScanLog
     */
    private function mapColumns(array $header): array
    {
        $normalised = array_map(
            fn (?string $name): string => preg_replace('/[^a-z0-9]/', '', strtolower((string) $name)) ?? '',
            $header,
        );

        $columns = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($normalised as $index => $name) {
                if (in_array($name, $aliases, true) && ! isset($columns[$field])) {
                    $columns[$field] = $index;
                }
            }
        }

        // "waktu" is ambiguous: a full timestamp on its own, but the time half when the file also
        // has a separate date column.
        if (isset($columns['date'], $columns['datetime']) && ! isset($columns['time'])) {
            $columns['time'] = $columns['datetime'];
            unset($columns['datetime']);
        }

        if (! isset($columns['pin'])) {
            throw new InvalidScanLog('Kolom PIN / ID tidak ditemukan pada baris judul. Gunakan nama kolom seperti "PIN", "ID" atau "Enroll Number".');
        }

        if (! isset($columns['datetime']) && ! (isset($columns['date']) && isset($columns['time']))) {
            throw new InvalidScanLog('Kolom tanggal dan waktu tidak ditemukan. Gunakan satu kolom "Scan Date" / "Waktu", atau kolom "Tanggal" dan "Jam" terpisah.');
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     * @return array{sn: string|null, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}|null
     */
    private function parseRow(array $cells, array $columns, string $timezone, ?string &$error): ?array
    {
        $error = null;

        $pin = $this->normalisePin((string) ($cells[$columns['pin']] ?? ''));

        if ($pin === '') {
            $error = 'PIN kosong.';

            return null;
        }

        $raw = isset($columns['datetime'])
            ? trim((string) ($cells[$columns['datetime']] ?? ''))
            : trim((string) ($cells[$columns['date']] ?? '')).' '.trim((string) ($cells[$columns['time']] ?? ''));

        $scanAt = $this->parseDateTime(trim($raw), $timezone);

        if ($scanAt === null) {
            $error = 'tanggal/waktu tidak dikenali ("'.trim($raw).'").';

            return null;
        }

        return [
            'sn' => $this->optionalString($cells, $columns, 'sn'),
            'pin' => $pin,
            'scan_at' => $scanAt,
            'verify_mode' => $this->optionalInt($cells, $columns, 'verify'),
            'inout_mode' => $this->optionalInt($cells, $columns, 'inout'),
        ];
    }

    private function parseDateTime(string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            if (CarbonImmutable::canBeCreatedFromFormat($value, $format)) {
                $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);

                if ($parsed instanceof CarbonImmutable) {
                    return $parsed->utc();
                }
            }
        }

        return null;
    }

    /**
     * A spreadsheet round trip turns "1042" into "1042.0"; the PIN is an identifier, not a number.
     */
    private function normalisePin(string $value): string
    {
        $value = trim($value);

        return preg_match('/^(\d+)\.0+$/', $value, $match) === 1 ? $match[1] : $value;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     */
    private function optionalString(array $cells, array $columns, string $field): ?string
    {
        if (! isset($columns[$field])) {
            return null;
        }

        $value = trim((string) ($cells[$columns[$field]] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     */
    private function optionalInt(array $cells, array $columns, string $field): ?int
    {
        $value = $this->optionalString($cells, $columns, $field);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($line, $candidate);

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
