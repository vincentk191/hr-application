<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Employee;
use App\Support\Attendance\AttendanceRecap;
use App\Support\Attendance\CaptureStatus;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\DateRangeInput;
use App\Support\Attendance\EmployeeRoster;
use App\Support\CsvDownload;
use App\Support\OrganisationNames;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attendance totals for a period, per employee and per department, with a CSV export
 * (EMP-ATT-003, ATT-CAL-005).
 *
 * Follows the company switcher like the calendar does, and is derived from the same days, so a
 * total here is always the sum of what the calendar shows.
 */
class AttendanceRecapController extends Controller
{
    /**
     * A recap of more than a year is a different report; this keeps one request bounded.
     */
    private const MAX_DAYS = 366;

    private const MAX_ROWS = 500;

    public function index(Request $request, AttendanceRecap $recap, EmployeeRoster $roster, DailyAttendance $attendance, CaptureStatus $capture): Response|StreamedResponse
    {
        abort_unless($request->user()->can(Permission::ViewAttendance->value), 403);

        $timezone = (string) config('attendance.device_timezone');
        [$from, $to] = DateRangeInput::fromRequest($request, $attendance->dataThrough($timezone), $timezone, self::MAX_DAYS);

        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branches' => OrganisationNames::fromInput($request->input('branches')),
            'departments' => OrganisationNames::fromInput($request->input('departments')),
            'team' => $request->integer('team') ?: null,
            'search' => trim((string) $request->string('search')),
        ];

        $employees = $roster->between($from, $to, $filters['departments'], $filters['search'], $filters['team'], viewer: $request->user(), branches: $filters['branches']);
        $result = $recap->build($from, $to, $employees);

        if ($request->string('export')->toString() === 'csv') {
            return $this->export($result, $filters);
        }

        return Inertia::render('attendance/recap', [
            'rows' => array_slice($result['rows'], 0, self::MAX_ROWS),
            'truncated' => count($result['rows']) > self::MAX_ROWS,
            'branches' => $result['branches'],
            'departments' => $result['departments'],
            'periods' => $result['periods'],
            'total' => $result['total'] + ['employees' => count($result['rows'])],
            'filters' => $filters,
            'options' => [
                'branches' => OrganisationNames::branches($request->user()),
                'departments' => OrganisationNames::departments($request->user()),
                'teams' => $roster->teamLeads($request->user()),
            ],
            'dataThrough' => $result['dataThrough'],
            'rekapNotes' => $result['rekapNotes'],
            'capture' => $capture->resolve(),
        ]);
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, branches: list<array<string, mixed>>, departments: list<array<string, mixed>>, periods: list<array<string, mixed>>, total: array<string, int|float>, dataThrough: string|null, rekapNotes: list<array{branch: string|null, from: string, to: string}>}  $result
     * @param  array<string, mixed>  $filters
     */
    private function export(array $result, array $filters): StreamedResponse
    {
        $hours = fn (int $minutes): string => number_format($minutes / 60, 2, '.', '');

        $header = [
            'Karyawan', 'ID', 'Cabang', 'Departemen', 'Hadir', 'Terlambat', 'Pulang cepat', 'Tidak hadir', 'Cuti', 'Belum ada data', 'Jam kerja',
            'Alpa', 'Izin', 'Sakit', 'Telat', 'Potongan (Rp)', 'Zona', 'Sumber',
        ];

        // Alpa, Izin, Sakit, Telat and the deduction; from the scanner's days or a confirmed rekapitulasi.
        // The Zona and source belong to a person, so a Cabang or department line leaves them empty.
        $figures = fn (array $row): array => [
            $row['present'], $row['late'], $row['earlyLeave'], $row['absent'], $row['leave'], $row['noData'], $hours((int) $row['minutes']),
            $row['alpa'], $row['izin'], $row['sakit'], $row['telat'], $row['potongan'],
            $row['zona'] ?? '', isset($row['source']) ? ($row['source'] === 'rekap' ? ($row['rekapMissing'] ? 'rekap (belum ada)' : 'rekap') : 'pemindai') : '',
        ];

        $lines = [
            ['Rekap absensi', "{$filters['from']} s/d {$filters['to']}", 'Data sampai '.($result['dataThrough'] ?? '-')],
            [],
            $header,
        ];

        foreach ($result['rows'] as $row) {
            $lines[] = [$row['name'], $row['number'], $row['branch'] ?? '', $row['department'] ?? '', ...$figures($row)];
        }

        $lines[] = [];
        $lines[] = ['Per cabang'];

        foreach ($result['branches'] as $branch) {
            $lines[] = [$branch['name'] ?? '(tanpa cabang)', $branch['employees'].' karyawan', '', '', ...$figures($branch)];
        }

        $lines[] = [];
        $lines[] = ['Per departemen'];

        foreach ($result['departments'] as $department) {
            $lines[] = [$department['name'] ?? '(tanpa departemen)', $department['employees'].' karyawan', '', '', ...$figures($department)];
        }

        $lines[] = [];
        $lines[] = ['Per periode'];

        foreach ($result['periods'] as $period) {
            $lines[] = [$period['label'], "{$period['from']} s/d {$period['to']}", '', '', ...$figures($period)];
        }

        $lines[] = [];
        $lines[] = ['Total', count($result['rows']).' karyawan', '', '', ...$figures($result['total'])];

        return CsvDownload::stream("rekap-absensi-{$filters['from']}-{$filters['to']}.csv", $lines);
    }
}
