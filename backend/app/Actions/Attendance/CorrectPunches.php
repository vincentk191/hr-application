<?php

namespace App\Actions\Attendance;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual corrections to an employee's punches (EMP-ATT-001).
 *
 * The punch log is append-only, so nothing here edits or deletes a scan. Adding writes a new
 * punch marked manual; voiding keeps the scan and marks it void; changing a time is both, in one
 * step. Every correction needs a reason and leaves an entry on the audit trail naming who did it,
 * and the calendar recomputes from the punches on its next read, so nothing else needs updating
 * (EMP-ATT-002).
 */
class CorrectPunches
{
    /**
     * Manual punches carry this in place of a scanner serial number.
     */
    public const MANUAL_DEVICE = 'MANUAL';

    public const SOURCE = 'manual';

    /**
     * @throws ValidationException
     */
    public function add(Employee $employee, string $localDate, string $localTime, string $reason, User $by): AttendancePunch
    {
        return DB::transaction(function () use ($employee, $localDate, $localTime, $reason, $by): AttendancePunch {
            $punch = $this->create($employee, $this->at($localDate, $localTime), $reason, $by);

            $this->audit($punch, $by, 'Punch added manually', ['reason' => $reason]);

            return $punch;
        });
    }

    /**
     * @throws ValidationException
     */
    public function void(AttendancePunch $punch, string $reason, User $by): AttendancePunch
    {
        if ($punch->voided_at !== null) {
            throw ValidationException::withMessages(['reason' => 'Scan ini sudah dibatalkan.']);
        }

        return DB::transaction(function () use ($punch, $reason, $by): AttendancePunch {
            $punch->forceFill([
                'voided_at' => now(),
                'voided_by_user_id' => $by->id,
                'void_reason' => $reason,
            ])->save();

            $this->audit($punch, $by, 'Punch voided', ['reason' => $reason]);

            return $punch;
        });
    }

    /**
     * Replace a punch's time: void the old scan and add the corrected one.
     *
     * @throws ValidationException
     */
    public function change(AttendancePunch $punch, string $localDate, string $localTime, string $reason, User $by): AttendancePunch
    {
        if ($punch->voided_at !== null) {
            throw ValidationException::withMessages(['reason' => 'Scan ini sudah dibatalkan.']);
        }

        return DB::transaction(function () use ($punch, $localDate, $localTime, $reason, $by): AttendancePunch {
            $employee = Employee::acrossCompanies()->findOrFail($punch->employee_id);
            $replacement = $this->create($employee, $this->at($localDate, $localTime), $reason, $by);

            $punch->forceFill([
                'voided_at' => now(),
                'voided_by_user_id' => $by->id,
                'void_reason' => "Diganti: {$reason}",
            ])->save();

            $this->audit($replacement, $by, 'Punch changed', [
                'reason' => $reason,
                'replaced_punch_id' => $punch->id,
                'was' => $punch->scan_at->utc()->toDateTimeString(),
            ]);

            return $replacement;
        });
    }

    /**
     * @throws ValidationException
     */
    private function at(string $localDate, string $localTime): CarbonImmutable
    {
        $at = CarbonImmutable::parse("{$localDate} {$localTime}", (string) config('attendance.device_timezone'));

        if ($at->isFuture()) {
            throw ValidationException::withMessages(['time' => 'Waktu scan tidak boleh di masa depan.']);
        }

        return $at->utc();
    }

    /**
     * @throws ValidationException
     */
    private function create(Employee $employee, CarbonImmutable $at, string $reason, User $by): AttendancePunch
    {
        $pin = 'M'.$employee->id;

        $exists = AttendancePunch::acrossCompanies()
            ->where('device_sn', self::MANUAL_DEVICE)
            ->where('pin', $pin)
            ->where('scan_at', $at->format('Y-m-d H:i:s'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['time' => 'Sudah ada scan manual pada waktu yang sama.']);
        }

        return AttendancePunch::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'device_sn' => self::MANUAL_DEVICE,
            'pin' => $pin,
            'scan_at' => $at,
            'source' => self::SOURCE,
            'reason' => $reason,
            'created_by_user_id' => $by->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(AttendancePunch $punch, User $by, string $description, array $properties): void
    {
        activity('attendance')
            ->performedOn($punch)
            ->causedBy($by)
            ->withProperties([...$properties, 'employee_id' => $punch->employee_id, 'scan_at' => $punch->scan_at->utc()->toDateTimeString()])
            ->log($description);
    }
}
