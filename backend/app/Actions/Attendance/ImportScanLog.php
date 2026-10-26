<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceImport;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\InvalidScanLog;
use App\Support\Attendance\ScanLogParser;
use Illuminate\Http\UploadedFile;

/**
 * Loads an uploaded scan log: read it, file every punch through IngestPunches, and keep a record
 * of what happened to each row so the person who uploaded it can see the outcome later.
 *
 * When the log carries each person's NIP (the Fingerspot export does), a PIN that is not mapped
 * yet is mapped first to the one employee with that employee number, so their punches land
 * straight in attendance instead of waiting to be mapped by hand.
 *
 * Safe to repeat. Uploading the same file twice, or two files that overlap, adds nothing the
 * second time; the duplicates are counted and reported instead.
 */
class ImportScanLog
{
    /**
     * How many bad-row messages are kept on the import record. Enough to spot a pattern without
     * storing a whole broken file's worth of text.
     */
    private const ERRORS_KEPT = 20;

    public function __construct(
        private ScanLogParser $parser,
        private IngestPunches $ingest,
        private MapEnrolId $mapEnrolId,
    ) {}

    /**
     * @param  string|null  $deviceSn  the scanner's serial, for rows whose file does not name one
     *
     * @throws InvalidScanLog
     */
    public function handle(UploadedFile $file, ?string $deviceSn, ?User $by): AttendanceImport
    {
        $parsed = $this->parser->parse($file->getRealPath(), (string) config('attendance.device_timezone'));

        if ($deviceSn === null && in_array(null, array_column($parsed['rows'], 'sn'), true)) {
            throw new InvalidScanLog('Berkas ini tidak memuat nomor seri (SN) pemindai pada setiap baris. Isi SN pemindai lalu unggah lagi.');
        }

        $rows = array_map(
            fn (array $row): array => [
                'sn' => $row['sn'] ?? (string) $deviceSn,
                'pin' => $row['pin'],
                'scan_at' => $row['scan_at'],
                'verify_mode' => $row['verify_mode'],
                'inout_mode' => $row['inout_mode'],
            ],
            $parsed['rows'],
        );

        $import = AttendanceImport::create([
            'user_id' => $by?->id,
            'source' => AttendanceImport::SOURCE_FILE,
            'original_name' => $file->getClientOriginalName(),
            'device_sn' => $deviceSn ?? ($rows[0]['sn'] ?? '-'),
            'rows_total' => $parsed['total'],
            'rejected' => count($parsed['errors']),
            'errors' => array_slice($parsed['errors'], 0, self::ERRORS_KEPT) ?: null,
            'first_scan_at' => $rows === [] ? null : min(array_map(fn (array $row) => $row['scan_at'], $rows)),
            'last_scan_at' => $rows === [] ? null : max(array_map(fn (array $row) => $row['scan_at'], $rows)),
        ]);

        $autoMapped = $this->mapByNip($parsed['rows'], $deviceSn);
        $counts = $this->ingest->handle($rows, $import->source, $import->id);

        $import->update([...$counts, 'auto_mapped' => $autoMapped]);

        return $import;
    }

    /**
     * Map each PIN the scanner has never had a mapping for to the employee whose number is the
     * NIP beside it. A PIN with any mapping already, even an expired one, is left alone: its
     * history is someone's decision. A NIP that matches no one, or more than one person across
     * the companies, is also left for HR to map by hand.
     *
     * @param  list<array{sn: string|null, pin: string, nip: string|null}>  $rows
     * @return int how many PINs were mapped
     */
    private function mapByNip(array $rows, ?string $deviceSn): int
    {
        $nips = [];

        foreach ($rows as $row) {
            if ($row['nip'] !== null) {
                $nips[($row['sn'] ?? $deviceSn).'|'.$row['pin']] ??= $row['nip'];
            }
        }

        if ($nips === []) {
            return 0;
        }

        $employees = Employee::acrossCompanies()
            ->whereIn('employee_number', array_values(array_unique($nips)))
            ->get()
            ->groupBy('employee_number');

        $mapped = 0;

        foreach ($nips as $key => $nip) {
            [$sn, $pin] = explode('|', $key, 2);
            $matches = $employees->get($nip);

            if ($matches === null || $matches->count() !== 1) {
                continue;
            }

            if (AttendanceEnrolMap::acrossCompanies()->where('device_sn', $sn)->where('pin', $pin)->exists()) {
                continue;
            }

            $this->mapEnrolId->handle($sn, $pin, $matches->first());
            $mapped++;
        }

        return $mapped;
    }
}
