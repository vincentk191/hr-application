<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceImport;
use App\Models\User;
use App\Support\Attendance\InvalidScanLog;
use App\Support\Attendance\ScanLogParser;
use Illuminate\Http\UploadedFile;

/**
 * Loads an uploaded scan log: read it, file every punch through IngestPunches, and keep a record
 * of what happened to each row so the person who uploaded it can see the outcome later.
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
    ) {}

    /**
     * @throws InvalidScanLog
     */
    public function handle(UploadedFile $file, string $deviceSn, ?User $by): AttendanceImport
    {
        $parsed = $this->parser->parse($file->getRealPath(), (string) config('attendance.device_timezone'));

        $rows = array_map(
            fn (array $row): array => [...$row, 'sn' => $row['sn'] ?? $deviceSn],
            $parsed['rows'],
        );

        $import = AttendanceImport::create([
            'user_id' => $by?->id,
            'source' => AttendanceImport::SOURCE_FILE,
            'original_name' => $file->getClientOriginalName(),
            'device_sn' => $deviceSn,
            'rows_total' => $parsed['total'],
            'rejected' => count($parsed['errors']),
            'errors' => array_slice($parsed['errors'], 0, self::ERRORS_KEPT) ?: null,
            'first_scan_at' => $rows === [] ? null : min(array_map(fn (array $row) => $row['scan_at'], $rows)),
            'last_scan_at' => $rows === [] ? null : max(array_map(fn (array $row) => $row['scan_at'], $rows)),
        ]);

        $counts = $this->ingest->handle($rows, $import->source, $import->id);

        $import->update($counts);

        return $import;
    }
}
