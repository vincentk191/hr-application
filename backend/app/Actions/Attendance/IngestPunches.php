<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceEnrolMap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Files scanner punches: mapped ones as attendance, unmapped ones into the holding table.
 *
 * This is the single way punches enter the system. The manual file import uses it today and the
 * sync agent's ingest endpoint will use it later, so both are idempotent in the same way: the
 * database's unique key on (device, scan time, PIN) turns a repeated punch into a duplicate
 * rather than a second row, however many times and through whichever route it arrives.
 */
class IngestPunches
{
    private const CHUNK = 500;

    /**
     * @param  list<array{sn: string, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>  $rows
     * @return array{accepted: int, duplicates: int, unmapped: int}
     */
    public function handle(array $rows, string $source, ?int $importId = null): array
    {
        $unique = $this->withoutRepeatsInBatch($rows);
        $maps = $this->mapsFor($unique);
        $timezone = (string) config('attendance.device_timezone');
        $now = now()->utc()->format('Y-m-d H:i:s');

        $mapped = [];
        $waiting = [];

        foreach ($unique as $row) {
            $map = $this->mapValidAt($maps, $row, $timezone);

            $record = [
                'device_sn' => $row['sn'],
                'pin' => $row['pin'],
                'scan_at' => $row['scan_at']->utc()->format('Y-m-d H:i:s'),
                'verify_mode' => $row['verify_mode'],
                'inout_mode' => $row['inout_mode'],
                'source' => $source,
                'import_id' => $importId,
                'created_at' => $now,
            ];

            if ($map === null) {
                $waiting[] = $record;
            } else {
                $mapped[] = [...$record, 'company_id' => $map->company_id, 'employee_id' => $map->employee_id];
            }
        }

        $accepted = 0;
        $unmapped = 0;

        DB::transaction(function () use ($mapped, $waiting, &$accepted, &$unmapped): void {
            foreach (array_chunk($mapped, self::CHUNK) as $chunk) {
                $accepted += DB::table('attendance_punches')->insertOrIgnore($chunk);
            }

            foreach (array_chunk($waiting, self::CHUNK) as $chunk) {
                $unmapped += DB::table('attendance_unmapped_punches')->insertOrIgnore($chunk);
            }
        });

        return [
            'accepted' => $accepted,
            'unmapped' => $unmapped,
            'duplicates' => count($rows) - $accepted - $unmapped,
        ];
    }

    /**
     * The same punch twice in one file is a duplicate of itself; keep one.
     *
     * @param  list<array{sn: string, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>  $rows
     * @return list<array{sn: string, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>
     */
    private function withoutRepeatsInBatch(array $rows): array
    {
        $seen = [];

        foreach ($rows as $row) {
            $seen[$row['sn'].'|'.$row['pin'].'|'.$row['scan_at']->utc()->format('Y-m-d H:i:s')] ??= $row;
        }

        return array_values($seen);
    }

    /**
     * Every mapping that could apply to these rows, keyed by device and PIN. Read across all
     * companies: a scanner serves the whole group, and the importer is a parent-wide action.
     *
     * @param  list<array{sn: string, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}>  $rows
     * @return array<string, list<AttendanceEnrolMap>>
     */
    private function mapsFor(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $pins = array_values(array_unique(array_column($rows, 'pin')));
        $devices = array_values(array_unique(array_column($rows, 'sn')));

        $grouped = [];

        foreach (AttendanceEnrolMap::acrossCompanies()->whereIn('pin', $pins)->whereIn('device_sn', $devices)->get() as $map) {
            $grouped[$map->device_sn.'|'.$map->pin][] = $map;
        }

        return $grouped;
    }

    /**
     * The mapping in force on the day of the scan, judged in the scanner's own timezone — a
     * 23:50 punch belongs to that local day, not to the next one in UTC.
     *
     * @param  array<string, list<AttendanceEnrolMap>>  $maps
     * @param  array{sn: string, pin: string, scan_at: CarbonImmutable, verify_mode: int|null, inout_mode: int|null}  $row
     */
    private function mapValidAt(array $maps, array $row, string $timezone): ?AttendanceEnrolMap
    {
        $day = $row['scan_at']->setTimezone($timezone)->toDateString();

        foreach ($maps[$row['sn'].'|'.$row['pin']] ?? [] as $map) {
            if (($map->valid_from === null || $map->valid_from->toDateString() <= $day)
                && ($map->valid_to === null || $map->valid_to->toDateString() >= $day)) {
                return $map;
            }
        }

        return null;
    }
}
