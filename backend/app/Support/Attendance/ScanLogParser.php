<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use SplFileObject;

/**
 * Reads a scanner log into normalised punches.
 *
 * The file can be the Fingerspot software's own Excel export (.xls or .xlsx, "export laporan"), or
 * a CSV saved from it or from anything else. No single layout is hard-coded: the header row is
 * found by its column names, in English and Indonesian, so title or blank rows above it do not
 * matter, and a CSV may use a comma, semicolon, tab or pipe. When an export differs, the place to
 * adapt is the alias lists below; nothing else depends on the file's shape.
 *
 * Two layouts are understood. The raw scan log has one row per scan: a PIN and either one date-time column or a date
 * column plus a time column. The Fingerspot *daily report* ("Laporan harian") has one row per employee per day, with
 * the day's scans in "Scan masuk", "Scan pulang" and up to two "Scan istirahat" columns: each filled one becomes a
 * punch, and a row with no scan at all (absent, on leave, a day off) is skipped. The rest of that report (its own
 * schedule, lateness, "Dihitung", leave and absence status) is not read: the dashboard works those out itself, and
 * its own leave requests are the source of truth for leave.
 *
 * Optional in both: the device serial, verify mode, in/out mode and the employee's NIP.
 */
class ScanLogParser
{
    /**
     * Header names (lower-cased, punctuation removed) that identify each column.
     *
     * "Tanggal scan" is the Fingerspot export's full date-time; it also has "Tanggal" and "Jam"
     * beside it, and the date-time is preferred when both are present.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'pin' => ['pin', 'enrollnumber', 'enrollno', 'enrollid', 'enrolid', 'userid', 'id', 'noid', 'nomorid', 'idkaryawan'],
        'datetime' => ['scandate', 'scanat', 'datetime', 'scantime', 'timestamp', 'waktu', 'waktuscan', 'tanggalwaktu', 'tanggaldanwaktu', 'tanggalscan'],
        'date' => ['date', 'tanggal', 'tgl'],
        'time' => ['time', 'jam', 'jamscan'],
        'sn' => ['sn', 'serial', 'serialno', 'serialnumber', 'devicesn', 'deviceserial', 'nomorseri'],
        'verify' => ['verifymode', 'verify', 'verifikasi'],
        'inout' => ['inoutmode', 'inout', 'io', 'statuspresensi', 'mode'],
        'nip' => ['nip', 'nik', 'nomorinduk', 'employeenumber', 'nokaryawan', 'nomorkaryawan'],
        // The daily report's per-scan columns. Each scan carries its own serial; the break scans have none.
        'scan_in' => ['scanmasuk'],
        'scan_out' => ['scanpulang'],
        'break1' => ['scanistirahat1'],
        'break2' => ['scanistirahat2'],
        'sn_in' => ['snscanmasuk'],
        'sn_out' => ['snscanpulang'],
    ];

    /**
     * The columns only the daily report has, dropped when a file also has the raw log's date-time so that a file which
     * always parsed as a raw log keeps doing so.
     *
     * @var list<string>
     */
    private const DAILY_ONLY = ['scan_in', 'scan_out', 'break1', 'break2', 'sn_in', 'sn_out'];

    /**
     * The daily report's scan columns in the order they happen in a day, with how an error names them.
     *
     * @var array<string, string>
     */
    private const DAILY_SCANS = [
        'scan_in' => 'Scan masuk',
        'break1' => 'Scan istirahat 1',
        'break2' => 'Scan istirahat 2',
        'scan_out' => 'Scan pulang',
    ];

    /**
     * How many non-empty rows are searched for the header before the file is given up on.
     */
    private const HEADER_SEARCH_ROWS = 10;

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

    public function __construct(private SpreadsheetReader $spreadsheets) {}

    /**
     * @return array{
     *     total: int,
     *     rows: list<array{sn: string|null, pin: string, nip: string|null, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>,
     *     errors: list<string>
     * }
     *
     * @throws InvalidScanLog when the file has no header this parser can read
     */
    public function parse(string $path, string $timezone): array
    {
        $format = $this->spreadsheets->formatOf($path);

        return $this->parseRows(
            $format === null ? $this->csvRows($path) : $this->spreadsheets->rows($path, $format),
            $timezone,
        );
    }

    /**
     * @param  iterable<int, list<mixed>>  $lines  cells per row, keyed by the row's 1-based number in the file
     * @return array{
     *     total: int,
     *     rows: list<array{sn: string|null, pin: string, nip: string|null, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>,
     *     errors: list<string>
     * }
     *
     * @throws InvalidScanLog
     */
    private function parseRows(iterable $lines, string $timezone): array
    {
        $columns = null;
        $firstProblem = null;
        $rowsSearched = 0;
        $total = 0;
        $rows = [];
        $errors = [];

        foreach ($lines as $lineNumber => $cells) {
            if ($this->isBlank($cells)) {
                continue;
            }

            if ($columns === null) {
                $columns = $this->mapColumns($cells, $problem);
                $firstProblem ??= $problem;

                if ($columns === null && ++$rowsSearched >= self::HEADER_SEARCH_ROWS) {
                    break;
                }

                continue;
            }

            if (isset($columns['scan_in']) || isset($columns['scan_out'])) {
                $day = $this->parseDailyRow($cells, $columns, $timezone);

                // One "row" is one scan here, as in the raw log, so the counts the import shows still add up.
                $total += count($day['punches']) + count($day['errors']);
                array_push($rows, ...$day['punches']);

                foreach ($day['errors'] as $message) {
                    $errors[] = "Baris {$lineNumber}: {$message}";
                }

                continue;
            }

            $total++;
            $row = $this->parseRow($cells, $columns, $timezone, $error);

            if ($row === null) {
                $errors[] = "Baris {$lineNumber}: {$error}";

                continue;
            }

            $rows[] = $row;
        }

        if ($columns === null) {
            throw new InvalidScanLog($firstProblem ?? 'Berkas kosong.');
        }

        return ['total' => $total, 'rows' => $rows, 'errors' => $errors];
    }

    /**
     * The CSV's rows, split on whichever delimiter its header row uses.
     *
     * @return array<int, list<string|null>>
     */
    private function csvRows(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $lines = [];

        foreach ($file as $index => $line) {
            if (is_string($line) && trim($line) !== '') {
                $lines[$index + 1] = $index === 0 ? $this->stripBom($line) : $line;
            }
        }

        $delimiter = $this->detectDelimiter(array_slice($lines, 0, self::HEADER_SEARCH_ROWS));

        return array_map(fn (string $line): array => str_getcsv($line, $delimiter, '"', ''), $lines);
    }

    /**
     * The column index of each field, or null (with the reason) when this row is not a usable header.
     *
     * @param  list<mixed>  $header
     * @return array<string, int>|null
     */
    private function mapColumns(array $header, ?string &$problem): ?array
    {
        $problem = null;

        $normalised = array_map(
            fn (mixed $name): string => preg_replace('/[^a-z0-9]/', '', strtolower(is_scalar($name) ? (string) $name : '')) ?? '',
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
            $problem = 'Kolom PIN / ID tidak ditemukan pada baris judul. Gunakan nama kolom seperti "PIN", "ID" atau "Enroll Number".';

            return null;
        }

        $raw = isset($columns['datetime']) || (isset($columns['date']) && isset($columns['time']));

        if (! $raw && (isset($columns['scan_in']) || isset($columns['scan_out']))) {
            if (! isset($columns['date'])) {
                $problem = 'Kolom "Tanggal" tidak ditemukan pada laporan harian.';

                return null;
            }

            return $columns;
        }

        if (! $raw) {
            $problem = 'Kolom tanggal dan waktu tidak ditemukan. Gunakan satu kolom "Scan Date" / "Tanggal scan", kolom "Tanggal" dan "Jam" terpisah, atau laporan harian Fingerspot dengan kolom "Scan masuk" / "Scan pulang".';

            return null;
        }

        return array_diff_key($columns, array_flip(self::DAILY_ONLY));
    }

    /**
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columns
     * @return array{sn: string|null, pin: string, nip: string|null, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}|null
     */
    private function parseRow(array $cells, array $columns, string $timezone, ?string &$error): ?array
    {
        $error = null;

        $pin = $this->normaliseNumber($this->text($cells, $columns, 'pin'));

        if ($pin === '') {
            $error = 'PIN kosong.';

            return null;
        }

        $raw = $this->dateTimeText($cells, $columns);
        $scanAt = $this->parseDateTime($raw, $timezone);

        if ($scanAt === null) {
            $error = 'tanggal/waktu tidak dikenali ("'.$raw.'").';

            return null;
        }

        $nip = $this->normaliseNumber($this->text($cells, $columns, 'nip'));

        return [
            'sn' => $this->optionalString($cells, $columns, 'sn'),
            'pin' => $pin,
            'nip' => $nip === '' ? null : $nip,
            'scan_at' => $scanAt,
            'verify_mode' => $this->optionalInt($cells, $columns, 'verify'),
            'inout_mode' => $this->optionalInt($cells, $columns, 'inout'),
        ];
    }

    /**
     * The punches in one row of the daily report: one per filled scan column. A row with no scan at all has nothing to
     * file and is not an error. A bad scan is an error of its own, so the others in the row still load.
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columns
     * @return array{
     *     punches: list<array{sn: string|null, pin: string, nip: string|null, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>,
     *     errors: list<string>
     * }
     */
    private function parseDailyRow(array $cells, array $columns, string $timezone): array
    {
        $scans = [];

        foreach (self::DAILY_SCANS as $field => $label) {
            $time = $this->text($cells, $columns, $field, 'H:i:s');

            if ($time !== '') {
                $scans[$field] = [$label, $time];
            }
        }

        if ($scans === []) {
            return ['punches' => [], 'errors' => []];
        }

        $pin = $this->normaliseNumber($this->text($cells, $columns, 'pin'));
        $nip = $this->normaliseNumber($this->text($cells, $columns, 'nip'));
        $date = $this->text($cells, $columns, 'date', 'd-m-Y');
        $snIn = $this->optionalString($cells, $columns, 'sn_in');
        $snOut = $this->optionalString($cells, $columns, 'sn_out');

        $punches = [];
        $errors = [];

        foreach ($scans as $field => [$label, $time]) {
            if ($pin === '' || $date === '') {
                $errors[] = $label.': '.($pin === '' ? 'PIN kosong.' : 'tanggal kosong.');

                continue;
            }

            $scanAt = $this->parseDateTime("{$date} {$time}", $timezone);

            if ($scanAt === null) {
                $errors[] = "{$label}: tanggal/waktu tidak dikenali (\"{$date} {$time}\").";

                continue;
            }

            $punches[] = [
                // The break scans carry no serial of their own: the day's other scan names the scanner.
                'sn' => $field === 'scan_out' ? ($snOut ?? $snIn) : ($snIn ?? $snOut),
                'pin' => $pin,
                'nip' => $nip === '' ? null : $nip,
                'scan_at' => $scanAt,
                'verify_mode' => null,
                'inout_mode' => null,
            ];
        }

        return ['punches' => $punches, 'errors' => $errors];
    }

    /**
     * The scan's date and time as one piece of text. A date-time column that turns out to hold only
     * a date is completed from the time column when there is one.
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function dateTimeText(array $cells, array $columns): string
    {
        $time = isset($columns['time']) ? $this->text($cells, $columns, 'time', 'H:i:s') : '';

        if (isset($columns['datetime'])) {
            $value = $this->text($cells, $columns, 'datetime', 'd-m-Y H:i:s');

            return str_contains($value, ':') || $time === '' ? $value : trim("{$value} {$time}");
        }

        return trim($this->text($cells, $columns, 'date', 'd-m-Y').' '.$time);
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
     * A cell as trimmed text. A real Excel date or time arrives as a serial number, not text; given
     * a layout, such a number is written out as the wall-clock value Excel shows.
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function text(array $cells, array $columns, string $field, ?string $dateLayout = null): string
    {
        if (! isset($columns[$field])) {
            return '';
        }

        $value = $cells[$columns[$field]] ?? null;

        if ($dateLayout !== null && (is_int($value) || is_float($value))) {
            return ExcelDate::excelToDateTimeObject($value, 'UTC')->format($dateLayout);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * A spreadsheet round trip turns "1042" into "1042.0"; a PIN or NIP is an identifier, not a number.
     */
    private function normaliseNumber(string $value): string
    {
        return preg_match('/^(\d+)\.0+$/', $value, $match) === 1 ? $match[1] : $value;
    }

    /**
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function optionalString(array $cells, array $columns, string $field): ?string
    {
        $value = $this->text($cells, $columns, $field);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function optionalInt(array $cells, array $columns, string $field): ?int
    {
        $value = $this->normaliseNumber($this->text($cells, $columns, $field));

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (is_scalar($cell) && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    /**
     * The delimiter that splits the opening rows the most, which is the header's.
     *
     * @param  list<string>  $lines
     */
    private function detectDelimiter(array $lines): string
    {
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = max(array_map(fn (string $line): int => substr_count($line, $candidate), $lines ?: ['']));

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
