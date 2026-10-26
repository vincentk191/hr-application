<?php

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Enums\Zona;
use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\InvalidRekap;
use App\Support\Attendance\PotonganCalculator;
use App\Support\Attendance\RekapParser;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A branch's rekapitulasi: reading the file, matching names inside the Cabang, the preview HR checks and
 * the confirmation (ATT-REK). Fixture: tests/Fixtures/rekap-cabang.xls, the Tangerang sheet for
 * 21/09/2026 s.d 20/10/2026 with STAFF A to STAFF M.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->hr = rekapUser(Role::HrAdmin);
    $this->branch = Branch::factory()->forCompany($this->company)->create(['name' => 'Tangerang', 'attendance_source' => AttendanceSource::Rekap]);
    $this->people = rekapPeople($this->company, $this->branch);
});

function rekapUser(Role $role): User
{
    $user = User::factory()->create(['company_id' => null]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * STAFF A to STAFF M of the sample, in the Zona the sheet gives them (M is the Supervisor).
 *
 * @return array<string, Employee> keyed by the letter
 */
function rekapPeople(Company $company, Branch $branch): array
{
    $zonas = ['A' => 'merah', 'B' => 'merah', 'C' => 'merah', 'D' => 'merah', 'E' => 'merah', 'F' => 'hijau', 'G' => 'merah',
        'H' => 'hijau', 'I' => 'hijau', 'J' => 'merah', 'K' => 'hijau', 'L' => 'merah', 'M' => 'hijau'];
    $people = [];

    foreach ($zonas as $letter => $zona) {
        $people[$letter] = Employee::factory()->forCompany($company)->create([
            'branch_id' => $branch->id, 'full_name' => "Staff {$letter}", 'zona' => $zona,
        ]);
    }

    return $people;
}

function rekapFile(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/rekap-cabang.xls'), 'REKAP TANGERANG.xls', null, null, true);
}

function rekapUpload(?Branch $branch = null): TestResponse
{
    return test()->actingAs(test()->hr)->post(route('attendance.rekaps.store'), [
        'branch_id' => ($branch ?? test()->branch)->id,
        'file' => rekapFile(),
    ]);
}

// --- Reading the file ---

it('reads the Cabang, the period and every employee line of the sample', function () {
    $parsed = app(RekapParser::class)->parseFile(base_path('tests/Fixtures/rekap-cabang.xls'));

    expect($parsed->branchName)->toBe('TANGERANG')
        ->and($parsed->periodLabel)->toBe('OKTOBER 2026')
        ->and($parsed->periodStart->toDateString())->toBe('2026-09-21')
        ->and($parsed->periodEnd->toDateString())->toBe('2026-10-20')
        ->and($parsed->lines)->toHaveCount(13);

    $staffD = collect($parsed->lines)->firstWhere('name', 'STAFF D');
    $staffK = collect($parsed->lines)->firstWhere('name', 'STAFF K');

    expect($staffD)->toMatchArray(['position' => 'WAREHOUSE SUPPORT', 'zona' => 'MERAH', 'alpa' => 0, 'izin' => 2, 'sakit' => 0, 'telat' => 0, 'potongan' => 200000])
        ->and($staffK)->toMatchArray(['sakit' => 1, 'telat' => 4, 'potongan' => 100000]);
});

it('refuses a file that is not a rekapitulasi', function () {
    $path = tempnam(sys_get_temp_dir(), 'rekap');
    file_put_contents($path, "nama,jumlah\nBudi,2\n");

    try {
        expect(fn () => app(RekapParser::class)->parseFile($path))->toThrow(InvalidRekap::class, 'Excel');
    } finally {
        unlink($path);
    }

    $rows = [1 => ['Laporan lain'], 2 => ['NAMA', 'TOTAL']];

    expect(fn () => app(RekapParser::class)->parse($rows))->toThrow(InvalidRekap::class, 'Judul kolom');
});

it('finds the columns by their headings, and falls back to the month named in Periode', function () {
    $rows = [
        1 => ['Cabang', 'BEKASI'],
        2 => ['Periode', 'NOVEMBER 2026'],
        3 => ['NO', 'TELAT', 'NAMA', 'ALPA', 'IZIN', 'SAKIT'],
        4 => [1, 2, 'Budi', 1, null, null],
        5 => ['JUMLAH'],
    ];

    $parsed = app(RekapParser::class)->parse($rows);

    expect($parsed->periodStart->toDateString())->toBe('2026-11-01')
        ->and($parsed->periodEnd->toDateString())->toBe('2026-11-30')
        ->and($parsed->lines[0])->toMatchArray(['name' => 'Budi', 'alpa' => 1, 'izin' => 0, 'sakit' => 0, 'telat' => 2, 'zona' => null, 'potongan' => null]);
});

it('says which row has a count that is not a whole number', function () {
    $rows = [
        1 => ['Periode', 'NOVEMBER 2026'],
        2 => ['NAMA', 'ALPA', 'IZIN', 'SAKIT', 'TELAT'],
        3 => ['Budi', 'dua', null, null, null],
    ];

    expect(fn () => app(RekapParser::class)->parse($rows))->toThrow(InvalidRekap::class, 'Baris 3: kolom ALPA');
});

// --- The deduction ---

it('works out the deduction by Zona, with Telat at half and Sakit free', function () {
    $potongan = app(PotonganCalculator::class);

    expect($potongan->amount(Zona::Merah, 0, 2, 0))->toBe(200000)
        ->and($potongan->amount(Zona::Hijau, 0, 0, 1))->toBe(25000)
        ->and($potongan->amount(Zona::Hijau, 0, 0, 4))->toBe(100000)
        ->and($potongan->amount(Zona::Hitam, 1, 1, 1))->toBe(500000)
        ->and($potongan->forRow(Zona::Hijau, 0, 2, 1, 'SUPERVISOR', 'OKTOBER 2026'))->toBe(0)
        ->and($potongan->forRow(Zona::Hijau, 0, 2, 1, 'Asisten Supervisor', 'OKTOBER 2026'))->toBe(125000)
        ->and($potongan->forRow(Zona::Hitam, 3, 0, 0, 'Sopir', 'SETAHUN 2026'))->toBe(0);
});

// --- Upload and preview ---

it('turns an upload into a draft with every name matched inside the Cabang', function () {
    $other = Branch::factory()->forCompany($this->company)->create(['name' => 'Bekasi', 'attendance_source' => AttendanceSource::Rekap]);
    Employee::factory()->forCompany($this->company)->create(['branch_id' => $other->id, 'full_name' => 'Staff Z']);

    // Staff C leaves the Cabang's list and a namesake appears in another branch: neither is matched.
    $this->people['C']->update(['full_name' => 'Staff CC']);
    Employee::factory()->forCompany($this->company)->create(['branch_id' => $other->id, 'full_name' => 'Staff C']);

    rekapUpload()->assertRedirect();

    $rekap = AttendanceRekap::acrossCompanies()->firstOrFail();

    expect($rekap->status)->toBe(AttendanceRekap::STATUS_DRAFT)
        ->and($rekap->branch_id)->toBe($this->branch->id)
        ->and($rekap->company_id)->toBe($this->company->id)
        ->and($rekap->period_start->toDateString())->toBe('2026-09-21')
        ->and($rekap->period_end->toDateString())->toBe('2026-10-20')
        ->and($rekap->rows()->count())->toBe(13)
        ->and($rekap->rows()->where('match_status', 'matched')->count())->toBe(12)
        ->and($rekap->rows()->where('name', 'STAFF C')->first()->employee_id)->toBeNull()
        ->and($rekap->rows()->where('name', 'STAFF C')->first()->match_status->value)->toBe('unmatched');
});

it('shows the preview with mismatches against the profile and the Cabang\'s own total', function () {
    // The sheet says Staff A is Merah; the profile says Hijau. Staff E is not on the profile list at all.
    $this->people['A']->update(['zona' => 'hijau']);
    $this->people['E']->update(['full_name' => 'Orang Lain']);

    rekapUpload()->assertRedirect();
    $rekap = AttendanceRekap::acrossCompanies()->firstOrFail();

    $this->actingAs($this->hr)->get(route('attendance.rekaps.show', $rekap->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/rekaps/show')
            ->where('rekap.branchMismatch', false)
            ->where('rekap.periodLabel', 'OKTOBER 2026')
            ->where('summary.total', 13)
            ->where('summary.matched', 12)
            ->where('summary.needAttention', 1)
            ->where('summary.zonaMismatches', 1)
            // The file's deductions are 325.000; the app agrees on every matched line.
            ->where('summary.potonganMismatches', 0)
            ->where('summary.fileTotal', 325000)
            ->where('summary.computedTotal', 325000)
            ->where('notInFile', ['Orang Lain'])
            ->has('employees', 13));
});

it('flags a deduction that differs from the one the Cabang wrote', function () {
    // Staff D is Merah with two Izin: 200.000 in the file. Moved to Hitam the app says 400.000.
    $this->people['D']->update(['zona' => 'hitam']);

    rekapUpload()->assertRedirect();
    $rekap = AttendanceRekap::acrossCompanies()->firstOrFail();

    $this->actingAs($this->hr)->get(route('attendance.rekaps.show', $rekap->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.potonganMismatches', 1)
            ->where('summary.zonaMismatches', 1)
            ->where('summary.computedTotal', 525000)
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'STAFF D')['computedPotongan'] === 400000));
});

it('warns when the file names another Cabang than the one chosen', function () {
    $bekasi = Branch::factory()->forCompany($this->company)->create(['name' => 'Bekasi', 'attendance_source' => AttendanceSource::Rekap]);

    rekapUpload($bekasi)->assertRedirect();
    $rekap = AttendanceRekap::acrossCompanies()->firstOrFail();

    $this->actingAs($this->hr)->get(route('attendance.rekaps.show', $rekap->id))
        ->assertInertia(fn (Assert $page) => $page->where('rekap.branchMismatch', true)->where('rekap.fileBranch', 'TANGERANG'));
});

it('only takes a file for a Cabang whose attendance is a rekapitulasi', function () {
    $scanner = Branch::factory()->forCompany($this->company)->create(['name' => 'Pusat Jakarta']);

    rekapUpload($scanner)->assertSessionHasErrors('branch_id');

    expect(AttendanceRekap::acrossCompanies()->count())->toBe(0);
});

it('reports a file it cannot read on the file field', function () {
    $this->actingAs($this->hr)->post(route('attendance.rekaps.store'), [
        'branch_id' => $this->branch->id,
        'file' => UploadedFile::fake()->createWithContent('kosong.xlsx', 'bukan excel'),
    ])->assertSessionHasErrors('file');
});

it('is for people who manage attendance data only', function () {
    $employee = rekapUser(Role::Employee);

    $this->actingAs($employee)->post(route('attendance.rekaps.store'), ['branch_id' => $this->branch->id, 'file' => rekapFile()])->assertForbidden();
});

// --- Linking, skipping, confirming ---

it('will not confirm while a name has found nobody, until HR links or skips it', function () {
    $this->people['C']->update(['full_name' => 'Tidak Sama']);
    $this->people['G']->update(['full_name' => 'Juga Lain']);

    rekapUpload()->assertRedirect();
    $rekap = AttendanceRekap::acrossCompanies()->firstOrFail();

    $this->actingAs($this->hr)->post(route('attendance.rekaps.confirm', $rekap->id))->assertSessionHasErrors('confirm');
    expect($rekap->refresh()->status)->toBe(AttendanceRekap::STATUS_DRAFT);

    $staffC = $rekap->rows()->where('name', 'STAFF C')->firstOrFail();
    $staffG = $rekap->rows()->where('name', 'STAFF G')->firstOrFail();

    // Linking someone already tied to another line is refused; a person of another company is too.
    $this->actingAs($this->hr)->put(route('attendance.rekaps.rows.update', [$rekap->id, $staffC->id]), ['employee_id' => $this->people['A']->id])
        ->assertSessionHasErrors('employee_id');
    $foreign = Employee::factory()->forCompany(Company::factory()->create())->create();
    $this->actingAs($this->hr)->put(route('attendance.rekaps.rows.update', [$rekap->id, $staffC->id]), ['employee_id' => $foreign->id])
        ->assertSessionHasErrors('employee_id');

    $this->actingAs($this->hr)->put(route('attendance.rekaps.rows.update', [$rekap->id, $staffC->id]), ['employee_id' => $this->people['C']->id])->assertRedirect();
    $this->actingAs($this->hr)->put(route('attendance.rekaps.rows.update', [$rekap->id, $staffG->id]), ['skip' => true])->assertRedirect();

    expect($staffC->refresh()->employee_id)->toBe($this->people['C']->id)
        ->and($staffC->match_status->value)->toBe('matched')
        ->and($staffG->refresh()->match_status->value)->toBe('skipped')
        ->and($staffG->employee_id)->toBeNull();

    $this->actingAs($this->hr)->post(route('attendance.rekaps.confirm', $rekap->id))->assertSessionHasNoErrors();

    expect($rekap->refresh()->status)->toBe(AttendanceRekap::STATUS_CONFIRMED)
        ->and($rekap->confirmed_at)->not->toBeNull();

    // A confirmed file is final: its lines cannot be changed, and it cannot be thrown away.
    $this->actingAs($this->hr)->put(route('attendance.rekaps.rows.update', [$rekap->id, $staffG->id]), ['skip' => true])->assertStatus(409);
    $this->actingAs($this->hr)->delete(route('attendance.rekaps.destroy', $rekap->id))->assertStatus(409);
});

it('replaces the earlier file for the same Cabang and dates when a corrected one is confirmed', function () {
    rekapUpload();
    rekapUpload();
    [$first, $second] = AttendanceRekap::acrossCompanies()->orderBy('id')->get()->all();

    $this->actingAs($this->hr)->post(route('attendance.rekaps.confirm', $first->id))->assertSessionHasNoErrors();

    $this->actingAs($this->hr)->get(route('attendance.rekaps.show', $second->id))
        ->assertInertia(fn (Assert $page) => $page->where('replaces', true));

    $this->actingAs($this->hr)->post(route('attendance.rekaps.confirm', $second->id))->assertSessionHasNoErrors();

    expect($first->refresh()->status)->toBe(AttendanceRekap::STATUS_SUPERSEDED)
        ->and($second->refresh()->status)->toBe(AttendanceRekap::STATUS_CONFIRMED);
});

it('points out a confirmed file for the same Cabang that overlaps other dates', function () {
    AttendanceRekap::create([
        'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'status' => AttendanceRekap::STATUS_CONFIRMED,
        'period_start' => '2026-08-21', 'period_end' => '2026-09-25',
    ]);

    rekapUpload();
    $draft = AttendanceRekap::acrossCompanies()->where('status', AttendanceRekap::STATUS_DRAFT)->firstOrFail();

    $this->actingAs($this->hr)->get(route('attendance.rekaps.show', $draft->id))
        ->assertInertia(fn (Assert $page) => $page->where('replaces', false)->has('overlaps', 1)->where('overlaps.0.periodEnd', '2026-09-25'));
});

it('lets HR link a name to someone whose profile is filed under another Cabang of the company', function () {
    // The Cabang has nobody of that name, but the person exists in the company under "Pusat".
    $pusat = Branch::factory()->forCompany($this->company)->create(['name' => 'Pusat Jakarta']);
    $this->people['C']->update(['branch_id' => $pusat->id]);

    rekapUpload()->assertRedirect();
    $rekap = AttendanceRekap::acrossCompanies()->firstOrFail();
    $staffC = $rekap->rows()->where('name', 'STAFF C')->firstOrFail();

    expect($staffC->match_status->value)->toBe('unmatched');

    $this->actingAs($this->hr)->get(route('attendance.rekaps.show', $rekap->id))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees', 13)
            // The Cabang's own people come first; the others say where they are filed.
            ->where('employees.12.label', fn (string $label) => str_contains($label, 'Staff C') && str_contains($label, 'Pusat Jakarta')));

    $this->actingAs($this->hr)->put(route('attendance.rekaps.rows.update', [$rekap->id, $staffC->id]), ['employee_id' => $this->people['C']->id])
        ->assertSessionHasNoErrors();

    expect($staffC->refresh()->employee_id)->toBe($this->people['C']->id);
});

it('throws a draft away', function () {
    rekapUpload();
    $draft = AttendanceRekap::acrossCompanies()->firstOrFail();

    $this->actingAs($this->hr)->delete(route('attendance.rekaps.destroy', $draft->id))->assertRedirect(route('attendance.device', ['tab' => 'rekap']));

    expect(AttendanceRekap::acrossCompanies()->count())->toBe(0)
        ->and(AttendanceRekapRow::count())->toBe(0);
});

it('lists the Cabang that send a rekapitulasi, and the latest files, on the data page', function () {
    Branch::factory()->forCompany($this->company)->create(['name' => 'Pusat Jakarta']);
    rekapUpload();

    $this->actingAs($this->hr)->get(route('attendance.device'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rekapBranches', 1)
            ->where('rekapBranches.0.label', fn (string $label) => str_starts_with($label, 'Tangerang'))
            ->has('rekaps', 1)
            ->where('rekaps.0.status', 'draft')
            ->where('rekaps.0.rows', 13));
});
