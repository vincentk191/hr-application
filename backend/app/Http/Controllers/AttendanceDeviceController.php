<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceImport;
use App\Models\AttendanceUnmappedPunch;
use App\Models\Employee;
use App\Support\Attendance\CaptureStatus;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The attendance data page (ATT-DEV): how current the log is, the upload that refreshes it, the
 * scanner IDs waiting to be matched to employees, and the matches already made.
 *
 * Group-level by design. A scanner serves the whole group, so everything here is read across
 * companies and the page is only open to parent-wide accounts that hold the manage-attendance
 * permission.
 */
class AttendanceDeviceController extends Controller
{
    private const HISTORY_LIMIT = 15;

    private const WAITING_LIMIT = 200;

    public function index(CaptureStatus $status): Response
    {
        $this->authorize('manage-attendance-device');

        $timezone = (string) config('attendance.device_timezone');
        $employees = $this->employeeOptions();

        return Inertia::render('attendance/device', [
            'status' => $status->resolve(),
            'timezone' => $timezone,
            'lastDeviceSn' => AttendanceImport::query()->latest('id')->value('device_sn'),
            'knownDevices' => $this->knownDevices(),
            'imports' => AttendanceImport::query()
                ->with('user:id,name')
                ->latest('id')
                ->limit(self::HISTORY_LIMIT)
                ->get()
                ->map(fn (AttendanceImport $import): array => [
                    'id' => $import->id,
                    'at' => $import->created_at?->setTimezone($timezone)->toIso8601String(),
                    'by' => $import->user?->name,
                    'file' => $import->original_name,
                    'deviceSn' => $import->device_sn,
                    'rowsTotal' => $import->rows_total,
                    'accepted' => $import->accepted,
                    'duplicates' => $import->duplicates,
                    'unmapped' => $import->unmapped,
                    'rejected' => $import->rejected,
                    'firstScanAt' => $import->first_scan_at?->setTimezone($timezone)->toIso8601String(),
                    'lastScanAt' => $import->last_scan_at?->setTimezone($timezone)->toIso8601String(),
                    'errors' => $import->errors ?? [],
                ])
                ->all(),
            'waiting' => $this->waitingIds($employees, $timezone),
            'maps' => AttendanceEnrolMap::acrossCompanies()
                ->with(['employee:id,full_name,employee_number', 'company:id,code'])
                ->orderBy('device_sn')
                ->orderBy('pin')
                ->get()
                ->map(fn (AttendanceEnrolMap $map): array => [
                    'id' => $map->id,
                    'deviceSn' => $map->device_sn,
                    'pin' => $map->pin,
                    'employee' => $map->employee?->full_name,
                    'employeeNumber' => $map->employee?->employee_number,
                    'companyCode' => $map->company?->code,
                    'validFrom' => $map->valid_from?->toDateString(),
                    'validTo' => $map->valid_to?->toDateString(),
                ])
                ->all(),
            'employees' => $employees,
        ]);
    }

    /**
     * Employees a PIN can be mapped to, across every company.
     *
     * @return array<int, array{id: int, label: string, number: string}>
     */
    private function employeeOptions(): array
    {
        return Employee::acrossCompanies()
            ->active()
            ->with('company:id,code')
            ->orderBy('full_name')
            ->get(['id', 'company_id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'label' => "{$employee->full_name} ({$employee->employee_number})"
                    .($employee->company ? " · {$employee->company->code}" : ''),
                'number' => $employee->employee_number,
            ])
            ->values()
            ->all();
    }

    /**
     * Scanner IDs with punches but no employee yet, with a suggestion where the ID happens to
     * equal exactly one employee number.
     *
     * @param  array<int, array{id: int, label: string, number: string}>  $employees
     * @return array<int, array<string, mixed>>
     */
    private function waitingIds(array $employees, string $timezone): array
    {
        $byNumber = collect($employees)->groupBy('number');

        return AttendanceUnmappedPunch::query()
            ->selectRaw('device_sn, pin, count(*) as punches, min(scan_at) as first_at, max(scan_at) as last_at')
            ->groupBy('device_sn', 'pin')
            ->orderBy('device_sn')
            ->orderBy('pin')
            ->limit(self::WAITING_LIMIT)
            ->get()
            ->map(function (AttendanceUnmappedPunch $row) use ($byNumber, $timezone): array {
                $matches = $byNumber->get($row->pin);

                return [
                    'deviceSn' => $row->device_sn,
                    'pin' => $row->pin,
                    'punches' => (int) $row->getAttribute('punches'),
                    'firstAt' => CarbonImmutable::parse($row->getAttribute('first_at'), 'UTC')->setTimezone($timezone)->toIso8601String(),
                    'lastAt' => CarbonImmutable::parse($row->getAttribute('last_at'), 'UTC')->setTimezone($timezone)->toIso8601String(),
                    'suggestedEmployeeId' => $matches !== null && $matches->count() === 1 ? $matches->first()['id'] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Serial numbers already seen, offered as suggestions on the upload form.
     *
     * @return array<int, string>
     */
    private function knownDevices(): array
    {
        return AttendanceImport::query()->distinct()->pluck('device_sn')
            ->merge(AttendanceEnrolMap::acrossCompanies()->distinct()->pluck('device_sn'))
            ->unique()
            ->sort()
            ->map(fn (mixed $serial): string => (string) $serial)
            ->values()
            ->all();
    }
}
