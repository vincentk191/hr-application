<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceUnmappedPunch;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ties a scanner PIN to an employee, and releases the punches that were waiting for it
 * (ATT-DEV-005).
 *
 * Releasing is part of the same step on purpose: a mapping that left earlier punches stranded in
 * the holding table would look saved while the attendance it should have produced stayed missing.
 */
class MapEnrolId
{
    /**
     * @return array{map: AttendanceEnrolMap, released: int}
     *
     * @throws ValidationException when the PIN is already mapped to someone for any of those dates
     */
    public function handle(
        string $deviceSn,
        string $pin,
        Employee $employee,
        ?CarbonInterface $validFrom = null,
        ?CarbonInterface $validTo = null,
    ): array {
        $this->refuseOverlap($deviceSn, $pin, $validFrom, $validTo);

        return DB::transaction(function () use ($deviceSn, $pin, $employee, $validFrom, $validTo): array {
            $map = AttendanceEnrolMap::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'device_sn' => $deviceSn,
                'pin' => $pin,
                'valid_from' => $validFrom?->toDateString(),
                'valid_to' => $validTo?->toDateString(),
            ]);

            return ['map' => $map, 'released' => $this->release($map)];
        });
    }

    /**
     * Move the waiting punches this mapping covers into attendance, and out of the holding table.
     */
    private function release(AttendanceEnrolMap $map): int
    {
        $timezone = (string) config('attendance.device_timezone');
        $released = 0;

        AttendanceUnmappedPunch::query()
            ->where('device_sn', $map->device_sn)
            ->where('pin', $map->pin)
            ->orderBy('id')
            ->chunkById(500, function ($waiting) use ($map, $timezone, &$released): void {
                $covered = $waiting->filter(function (AttendanceUnmappedPunch $punch) use ($map, $timezone): bool {
                    $day = $punch->scan_at->setTimezone($timezone)->toDateString();

                    return ($map->valid_from === null || $map->valid_from->toDateString() <= $day)
                        && ($map->valid_to === null || $map->valid_to->toDateString() >= $day);
                });

                if ($covered->isEmpty()) {
                    return;
                }

                $released += DB::table('attendance_punches')->insertOrIgnore(
                    $covered->map(fn (AttendanceUnmappedPunch $punch): array => [
                        'company_id' => $map->company_id,
                        'employee_id' => $map->employee_id,
                        'device_sn' => $punch->device_sn,
                        'pin' => $punch->pin,
                        'scan_at' => $punch->scan_at->utc()->format('Y-m-d H:i:s'),
                        'verify_mode' => $punch->verify_mode,
                        'inout_mode' => $punch->inout_mode,
                        'source' => $punch->source,
                        'import_id' => $punch->import_id,
                        'created_at' => $punch->created_at?->utc()->format('Y-m-d H:i:s'),
                    ])->values()->all(),
                );

                AttendanceUnmappedPunch::query()->whereKey($covered->pluck('id'))->delete();
            });

        return $released;
    }

    /**
     * One PIN cannot belong to two people on the same day, or its punches would be filed under
     * whichever mapping happened to be found first.
     *
     * @throws ValidationException
     */
    private function refuseOverlap(string $deviceSn, string $pin, ?CarbonInterface $from, ?CarbonInterface $to): void
    {
        $overlaps = AttendanceEnrolMap::acrossCompanies()
            ->where('device_sn', $deviceSn)
            ->where('pin', $pin)
            ->where(fn ($query) => $query->whereNull('valid_to')
                ->when($from !== null, fn ($inner) => $inner->orWhereDate('valid_to', '>=', $from->toDateString()),
                    fn ($inner) => $inner->orWhereNotNull('valid_to')))
            ->where(fn ($query) => $query->whereNull('valid_from')
                ->when($to !== null, fn ($inner) => $inner->orWhereDate('valid_from', '<=', $to->toDateString()),
                    fn ($inner) => $inner->orWhereNotNull('valid_from')))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'pin' => 'PIN ini sudah dipetakan untuk tanggal tersebut. Akhiri pemetaan lama terlebih dahulu.',
            ]);
        }
    }
}
