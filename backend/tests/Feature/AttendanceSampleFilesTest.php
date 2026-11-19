<?php

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Enums\Zona;
use App\Models\AttendanceRekap;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Support\Attendance\PotonganCalculator;
use App\Support\Attendance\RekapParser;
use App\Support\Attendance\SampleWorkbooks;
use App\Support\Attendance\ScanLogParser;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The example files offered beside the two uploads on "Data absensi". They are only worth having if the importers accept
 * them, so most of this feeds each one back through the real reader.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['attendance.device_timezone' => 'Asia/Jakarta']);
});

function sampleUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/** A workbook saved to a temporary .xlsx, for the readers that take a path. The caller deletes it. */
function savedSample(Spreadsheet $book): string
{
    $path = tempnam(sys_get_temp_dir(), 'sample').'.xlsx';
    (new Xlsx($book))->save($path);

    return $path;
}

// --- Who may download ---

it('lets HR download both examples as Excel files', function (string $route, string $filename) {
    $response = $this->actingAs(sampleUser(Role::HrAdmin))->get(route($route))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain($filename)
        ->and(substr($response->streamedContent(), 0, 2))->toBe('PK');
})->with([
    'Laporan Absensi' => ['attendance.imports.sample', 'contoh-laporan-absensi.xlsx'],
    'Rekapitulasi cabang' => ['attendance.rekaps.sample', 'contoh-rekapitulasi-cabang.xlsx'],
]);

it('keeps the examples from anyone who cannot upload', function (string $route, Role $role, bool $pinnedToCompany) {
    $this->actingAs(sampleUser($role, $pinnedToCompany ? Company::factory()->create() : null))
        ->get(route($route))
        ->assertForbidden();
})->with([
    ['attendance.imports.sample'],
    ['attendance.rekaps.sample'],
])->with([
    'Management' => [Role::Management, false],
    'Employee' => [Role::Employee, false],
    'an HR admin pinned to one company' => [Role::HrAdmin, true],
]);

it('asks a signed-out visitor to sign in', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['attendance.imports.sample', 'attendance.rekaps.sample']);

it('is not there when attendance is switched off', function (string $route) {
    config(['features.attendance' => false]);

    $this->actingAs(sampleUser(Role::HrAdmin))->get(route($route))->assertNotFound();
})->with(['attendance.imports.sample', 'attendance.rekaps.sample']);

// --- The Laporan Absensi example is a file the import reads ---

it('is read as a daily report with no row refused', function () {
    $path = savedSample(app(SampleWorkbooks::class)->scanLog());

    $parsed = app(ScanLogParser::class)->parse($path, 'Asia/Jakarta');
    unlink($path);

    // Three people for five days: 4 + 5 + 4 days with two scans, and one forgotten scan-out.
    expect($parsed['errors'])->toBe([])
        ->and($parsed['rows'])->toHaveCount(27)
        ->and(collect($parsed['rows'])->pluck('pin')->unique()->sort()->values()->all())->toBe(['1001', '1002', '1003'])
        ->and(collect($parsed['rows'])->pluck('nip')->unique()->count())->toBe(3);
});

it('has the same scans on the raw log sheet as on the daily report', function () {
    $daily = savedSample(app(SampleWorkbooks::class)->scanLog());

    // The importer reads the first sheet only, so the raw log is moved to the front to read it.
    $book = app(SampleWorkbooks::class)->scanLog();
    $book->removeSheetByIndex(0);
    $log = savedSample($book);

    $scans = fn (string $path): array => collect(app(ScanLogParser::class)->parse($path, 'Asia/Jakarta')['rows'])
        ->map(fn (array $row): string => $row['pin'].' '.$row['scan_at']->toDateTimeString())
        ->sort()
        ->values()
        ->all();

    expect($scans($log))->toBe($scans($daily))->toHaveCount(27);

    unlink($daily);
    unlink($log);
});

it('can itself be uploaded to the import', function () {
    $path = savedSample(app(SampleWorkbooks::class)->scanLog());

    $this->actingAs(sampleUser(Role::HrAdmin))
        ->post(route('attendance.imports.store'), ['file' => new UploadedFile($path, 'contoh.xlsx', null, null, true)])
        ->assertSessionHasNoErrors();

    unlink($path);
});

// --- The rekapitulasi example is a file the upload reads ---

it('is read as a rekapitulasi with its Cabang, period and every employee line', function () {
    $path = savedSample(app(SampleWorkbooks::class)->rekap());

    $parsed = app(RekapParser::class)->parseFile($path);
    unlink($path);

    expect($parsed->branchName)->toBe('CABANG CONTOH')
        ->and($parsed->periodLabel)->toBe('OKTOBER 2026')
        ->and($parsed->periodStart->toDateString())->toBe('2026-09-21')
        ->and($parsed->periodEnd->toDateString())->toBe('2026-10-20')
        ->and($parsed->lines)->toHaveCount(6)
        ->and(array_column($parsed->lines, 'name'))->toContain('Budi Santoso', 'Rudi Hartono');
});

it('writes deductions that agree with the app\'s own rules, so the preview marks nothing red', function () {
    $path = savedSample(app(SampleWorkbooks::class)->rekap());
    $parsed = app(RekapParser::class)->parseFile($path);
    unlink($path);

    $calculator = app(PotonganCalculator::class);

    foreach ($parsed->lines as $line) {
        $expected = $calculator->forRow(
            Zona::from(strtolower((string) $line['zona'])),
            $line['alpa'],
            $line['izin'],
            $line['telat'],
            $line['position'],
            $parsed->periodLabel,
        );

        expect($line['potongan'])->toBe($expected);
    }

    // The Supervisor has telat days and is still not deducted.
    $supervisor = collect($parsed->lines)->firstWhere('position', 'SUPERVISOR');

    expect($supervisor['telat'])->toBeGreaterThan(0)->and($supervisor['potongan'])->toBe(0);
});

it('can itself be uploaded as a rekapitulasi draft', function () {
    $branch = Branch::factory()->forCompany(Company::factory()->create())->create(['attendance_source' => AttendanceSource::Rekap]);
    $path = savedSample(app(SampleWorkbooks::class)->rekap());

    $this->actingAs(sampleUser(Role::HrAdmin))
        ->post(route('attendance.rekaps.store'), ['branch_id' => $branch->id, 'file' => new UploadedFile($path, 'contoh.xlsx', null, null, true)])
        ->assertSessionHasNoErrors();

    unlink($path);

    $rekap = AttendanceRekap::query()->firstOrFail();

    expect($rekap->status)->toBe(AttendanceRekap::STATUS_DRAFT)->and($rekap->rows()->count())->toBe(6);
});
