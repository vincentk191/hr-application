<?php

namespace App\Actions\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\Permission;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\Zona;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Support\Employees\EmployeeSheet;
use App\Support\Employees\InvalidEmployeeImport;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use SplFileObject;

/**
 * Bulk-loads employees from HR's data sheet, as an Excel file or a CSV (EMP-DIR-010).
 *
 * What it does and does not do, so a surprise is never silent:
 *  - It only ever creates. An employee number that already exists in the company is skipped and
 *    counted as a duplicate, never updated: a re-uploaded file must not overwrite edits made since.
 *  - Bad rows are rejected one by one with their line number; good rows still load.
 *  - Columns are found by header name (English or Indonesian), in any order; see EmployeeSheet for HR's layout.
 *  - The sensitive columns (bank, NPWP, KK, BPJS, PTKP, mother's name, insurance) are read only when the person
 *    importing holds the sensitive-data permission, like the profile form. Without it they are ignored and the result
 *    says so. Payroll columns are not part of the sheet and are never read.
 *  - A Cabang, department or position must already exist by name. Nothing is auto-created. Every employee needs a
 *    Cabang: a blank one is read as the company's only Cabang, so a file made before Cabang existed still loads, and
 *    is an error once there are several.
 *  - The manager (the sheet's Supervisor) is given as an employee number or as a full name, already in the company or
 *    elsewhere in the same file. A name must point at exactly one person.
 *  - The company named in the file must be the one selected, so a sheet is never loaded into the wrong company. The
 *    company's own NPWP numbers are filled in from the file only while the company has none.
 *  - With $dryRun nothing is written: the outcome is what an import would do.
 */
class ImportEmployees
{
    public const MAX_ROWS = 2000;

    /**
     * Header names (lower-cased, punctuation removed) that identify each column. A name that appears twice in the
     * header is told apart by order: the second is looked up as "name#2" (the sheet has two "No KTP" columns, the
     * second being the house number of the KTP address).
     *
     * @var array<string, list<string>>
     */
    public const ALIASES = [
        'company' => ['namaperusahaan', 'perusahaan', 'company', 'companyname'],
        'company_npwp_15' => ['npwp15perusahaan', 'npwpperusahaan15'],
        'company_npwp_22' => ['npwp22perusahaan', 'npwp22peresuhaan', 'npwpperusahaan22'],
        'employee_number' => ['idkaryawan', 'nomorindukkaryawan', 'employeenumber', 'employeeid', 'nomorkaryawan', 'nip', 'empno', 'id'],
        'full_name' => ['nama', 'namakaryawan', 'namalengkap', 'fullname', 'name'],
        'gender' => ['jeniskelamin', 'kelamin', 'jk', 'gender', 'sex'],
        'branch' => ['cabang', 'lokasikaryawankerja', 'lokasikerja', 'lokasi', 'branch', 'kantor', 'office'],
        'ptkp_status' => ['statusptkp', 'ptkp'],
        'department' => ['divisi', 'departemen', 'department'],
        'job_title' => ['jabatan', 'jobtitle'],
        'position' => ['posisi', 'position'],
        'manager' => ['supervisor', 'namaatasan', 'idatasan', 'atasan', 'manager', 'managerid', 'idmanager'],
        'status' => ['status', 'employmentstatus'],
        'type' => ['statuskaryawan', 'statuspegawai', 'tipe', 'jenis', 'tipekaryawan', 'employmenttype', 'statuskepegawaian'],
        'joined_on' => ['tanggalmulai', 'tglmulai', 'tanggalbergabung', 'tanggalmasuk', 'tglmasuk', 'joinedon', 'joindate', 'joined'],
        'exited_on' => ['tanggalkeluar', 'tglkeluar', 'exitedon', 'exitdate'],
        'birth_place' => ['tempatlahir', 'birthplace'],
        'birth_date' => ['tanggallahir', 'tgllahir', 'birthdate'],
        'religion' => ['agamakaryawan', 'agama', 'religion'],
        'marital_status' => ['statusperkawinan', 'statuskawin', 'perkawinan', 'maritalstatus'],
        'education_level' => ['pendidikanterakhir', 'pendidikan', 'education'],
        'zona' => ['zonaabsenkaryawan', 'zonaabsen', 'zona'],
        'email' => ['alamatemail', 'email', 'surel'],
        'phone' => ['nohandphone', 'nohp', 'telepon', 'telp', 'hp', 'phone', 'nomortelepon'],
        'address' => ['alamattempattinggal', 'alamatdomisili', 'alamat', 'address'],
        'residence_village' => ['kelurahantempattinggal', 'kelurahandomisili'],
        'residence_district' => ['kecamatantempattinggal', 'kecamatandomisili'],
        'residence_city' => ['kabupatentempattinggal', 'kotatempattinggal', 'kabupatendomisili'],
        'residence_province' => ['propinsitempattinggal', 'provinsitempattinggal', 'provinsidomisili'],
        'residence_postal_code' => ['kodepostempattinggal', 'kodeposdomisili'],
        'family_card_number' => ['nokk', 'nomorkk', 'kartukeluarga'],
        'national_id' => ['nik', 'noktp', 'ktp', 'nationalid'],
        'mother_name' => ['namaibukaryawan', 'namaibukandung', 'namaibu'],
        'id_address' => ['alamatktp'],
        'id_block' => ['blokktp'],
        'id_house_number' => ['noktp#2', 'nomorrumahktp'],
        'id_rt' => ['rtktp'],
        'id_rw' => ['rwktp'],
        'id_village' => ['kelurahanktp'],
        'id_district' => ['kecamatanktp'],
        'id_city' => ['kabupatenktp', 'kotaktp'],
        'id_province' => ['provinsiktp', 'propinsiktp'],
        'id_postal_code' => ['kodeposktp'],
        'bank_account_number' => ['norekening', 'nomorrekening'],
        'bank_account_holder' => ['namarekening', 'atasnama'],
        'bank_name' => ['namabank', 'bank'],
        'bank_branch' => ['cabangbank'],
        'tax_number' => ['npwp16', 'npwp', 'nonpwp'],
        'bpjs_employment_number' => ['nobpjsketenagakerjaan', 'bpjsketenagakerjaan', 'bpjstk'],
        'bpjs_health_number' => ['nobpjskesehatan', 'bpjskesehatan'],
    ];

    /**
     * Columns that plain text copies straight into an attribute of the same name.
     *
     * @var list<string>
     */
    private const TEXT_FIELDS = [
        'email', 'phone', 'address', 'birth_place', 'education_level', 'job_title',
        'residence_village', 'residence_district', 'residence_city', 'residence_province', 'residence_postal_code',
        'id_address', 'id_block', 'id_house_number', 'id_rt', 'id_rw', 'id_village', 'id_district', 'id_city',
        'id_province', 'id_postal_code',
        'mother_name', 'bank_account_number', 'bank_account_holder', 'bank_name', 'bank_branch',
        'bpjs_employment_number', 'bpjs_health_number',
    ];

    private const STATUSES = [
        'aktif' => EmploymentStatus::Active, 'active' => EmploymentStatus::Active,
        'nonaktif' => EmploymentStatus::Inactive, 'tidakaktif' => EmploymentStatus::Inactive, 'inactive' => EmploymentStatus::Inactive,
    ];

    private const TYPES = [
        'tetap' => EmploymentType::Permanent, 'karyawantetap' => EmploymentType::Permanent, 'pkwtt' => EmploymentType::Permanent, 'permanent' => EmploymentType::Permanent,
        'kontrak' => EmploymentType::Contract, 'karyawankontrak' => EmploymentType::Contract, 'pkwt' => EmploymentType::Contract, 'contract' => EmploymentType::Contract,
        'percobaan' => EmploymentType::Probation, 'masapercobaan' => EmploymentType::Probation, 'probation' => EmploymentType::Probation,
        'magang' => EmploymentType::Intern, 'intern' => EmploymentType::Intern, 'internship' => EmploymentType::Intern,
    ];

    /**
     * @var list<string>
     */
    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y'];

    /**
     * @return array{total: int, created: int, duplicates: int, rejected: int, errors: list<string>, dryRun: bool, sensitiveSkipped: bool}
     *
     * @throws InvalidEmployeeImport
     */
    public function handle(string $path, int $companyId, ?User $by, bool $dryRun = false): array
    {
        [$rows, $errors, $columns] = $this->read($path);
        $total = count($rows) + count($errors);

        $mayHandleSensitive = $by?->can(Permission::ViewSensitiveData->value) === true;
        $sensitiveFields = $this->sensitiveFields();
        $sensitiveSkipped = ! $mayHandleSensitive && array_intersect($sensitiveFields, array_keys($columns)) !== [];

        $company = Company::query()->findOrFail($companyId);
        $lookups = [
            'company' => $company->name,
            'branches' => $this->namesToIds(Branch::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name'])),
            'departments' => $this->namesToIds(Department::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name'])),
            'positions' => $this->positionsByName($companyId),
        ];

        $people = Employee::acrossCompanies()->where('company_id', $companyId)->get(['id', 'employee_number', 'full_name']);
        $existing = $people->mapWithKeys(fn (Employee $employee): array => [mb_strtolower($employee->employee_number) => $employee->id])->all();
        $namesTaken = $this->namesToKeys($people->mapWithKeys(fn (Employee $employee): array => [mb_strtolower($employee->employee_number) => $employee->full_name])->all());

        $takenNiks = Employee::acrossCompanies()->whereNotNull('national_id')->pluck('national_id')->flip()->all();

        $valid = [];
        $duplicates = 0;
        $companyNumbers = ['npwp_15' => null, 'npwp_22' => null];

        foreach ($rows as $row) {
            $key = mb_strtolower($row['employee_number']);

            if (isset($existing[$key]) || isset($valid[$key])) {
                $duplicates++;

                continue;
            }

            $problem = $this->validate($row, $lookups, $mayHandleSensitive, $record);

            if ($problem !== null) {
                $errors[] = "Baris {$row['line']}: {$problem}";

                continue;
            }

            // NIK is unique across the group: against what is already stored and against the file.
            if (isset($takenNiks[$record['national_id']])) {
                $errors[] = "Baris {$row['line']}: NIK {$record['national_id']} sudah terdaftar pada karyawan lain.";

                continue;
            }

            $takenNiks[$record['national_id']] = true;

            $companyNumbers['npwp_15'] ??= $this->digitsOnly($row['company_npwp_15']);
            $companyNumbers['npwp_22'] ??= $this->digitsOnly($row['company_npwp_22']);

            $valid[$key] = ['line' => $row['line'], 'manager' => $row['manager'], 'record' => $record];
        }

        $valid = $this->resolveManagerNames($valid, $existing, $namesTaken, $errors);
        $valid = $this->dropBrokenManagerLinks($valid, $existing, $errors);

        if (! $dryRun && $valid !== []) {
            $this->create($valid, $existing, $companyId, $by);
            $this->fillCompanyNumbers($company, $companyNumbers);
        }

        usort($errors, fn (string $a, string $b): int => (int) substr($a, 6) <=> (int) substr($b, 6));

        return [
            'total' => $total,
            'created' => count($valid),
            'duplicates' => $duplicates,
            'rejected' => count($errors),
            'errors' => $errors,
            'dryRun' => $dryRun,
            'sensitiveSkipped' => $sensitiveSkipped,
        ];
    }

    /**
     * Rows keyed by header field, with the file line they came from. Rows that cannot even be
     * read (no ID or no name) are returned as errors instead.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: array<string, int>}
     *
     * @throws InvalidEmployeeImport
     */
    private function read(string $path): array
    {
        $columns = null;
        $rows = [];
        $errors = [];

        foreach ($this->lines($path) as [$lineNumber, $cells]) {
            if ($columns === null) {
                $columns = $this->mapColumns($cells);

                continue;
            }

            if (count($rows) + count($errors) >= self::MAX_ROWS) {
                throw new InvalidEmployeeImport('Berkas terlalu besar: maksimal '.self::MAX_ROWS.' baris per impor. Bagi menjadi beberapa berkas.');
            }

            $row = $this->rowFrom($cells, $columns, $lineNumber);

            if ($row['employee_number'] === null || $row['full_name'] === null) {
                $errors[] = "Baris {$lineNumber}: ".($row['employee_number'] === null ? 'ID karyawan' : 'nama').' kosong.';

                continue;
            }

            // A spreadsheet round trip turns "1042" into "1042.0"; the number is an identifier.
            $row['employee_number'] = preg_match('/^(\d+)\.0+$/', $row['employee_number'], $match) === 1 ? $match[1] : $row['employee_number'];

            // A mobile number typed as a number has lost its leading zero: 81234567890 was 081234567890.
            if ($row['phone'] !== null && preg_match('/^8\d{8,12}$/', $row['phone']) === 1) {
                $row['phone'] = '0'.$row['phone'];
            }

            $rows[] = $row;
        }

        if ($columns === null) {
            throw new InvalidEmployeeImport('Berkas kosong.');
        }

        return [$rows, $errors, $columns];
    }

    /**
     * One line of the file as the sheet's fields: trimmed text, null for an empty cell.
     *
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    private function rowFrom(array $cells, array $columns, int $lineNumber): array
    {
        $row = ['line' => $lineNumber];

        foreach (array_keys(self::ALIASES) as $field) {
            $value = isset($columns[$field]) ? trim((string) ($cells[$columns[$field]] ?? '')) : '';
            $row[$field] = $value === '' ? null : $value;
        }

        return $row;
    }

    /**
     * The file's non-blank lines as [line number, cells], whether it is a spreadsheet or a CSV. A spreadsheet's line
     * is its row number.
     *
     * @return Generator<int, array{0: int, 1: list<string|null>}>
     *
     * @throws InvalidEmployeeImport
     */
    private function lines(string $path): Generator
    {
        $start = (string) file_get_contents($path, false, null, 0, 4);

        // "PK" is an .xlsx (a zip); D0 CF 11 E0 is the older .xls.
        if (str_starts_with($start, 'PK') || $start === "\xD0\xCF\x11\xE0") {
            yield from $this->spreadsheetLines($path);

            return;
        }

        yield from $this->csvLines($path);
    }

    /**
     * @return Generator<int, array{0: int, 1: list<string|null>}>
     */
    private function csvLines(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $delimiter = null;
        $lineNumber = 0;

        foreach ($file as $line) {
            $lineNumber++;

            if (! is_string($line) || trim($line) === '') {
                continue;
            }

            if ($delimiter === null) {
                $line = str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
                $delimiter = $this->detectDelimiter($line);
            }

            yield [$lineNumber, str_getcsv($line, $delimiter, '"', '')];
        }
    }

    /**
     * The first sheet of an Excel file. Dates arrive as numbers and are turned back into dates here; a number in a
     * text column (a NIK, an account number) is written out in full, never as 3,17E+15.
     *
     * @return Generator<int, array{0: int, 1: list<string|null>}>
     *
     * @throws InvalidEmployeeImport
     */
    private function spreadsheetLines(string $path): Generator
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadEmptyCells(false);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable) {
            throw new InvalidEmployeeImport('Berkas Excel tidak bisa dibaca. Simpan ulang sebagai .xlsx atau CSV, lalu coba lagi.');
        }

        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();

        // Past MAX_ROWS the loop stops: a sheet with formatting dragged down thousands of empty rows is not read in full.
        for ($row = 1; $row <= min($lastRow, self::MAX_ROWS + 50); $row++) {
            $cells = [];
            $filled = false;

            foreach ($sheet->getRowIterator($row, $row)->current()->getCellIterator('A', $lastColumn) as $cell) {
                $text = $this->cellText($cell);
                $filled = $filled || $text !== null;
                $cells[] = $text;
            }

            if ($filled) {
                yield [$row, $cells];
            }
        }
    }

    private function cellText(Cell $cell): ?string
    {
        $value = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            if (ExcelDate::isDateTime($cell)) {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            }

            return is_float($value) && floor($value) !== $value ? (string) $value : number_format($value, 0, '', '');
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     *
     * @throws InvalidEmployeeImport
     */
    private function mapColumns(array $header): array
    {
        $seen = [];
        $normalised = [];

        foreach ($header as $index => $name) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $name)) ?? '';
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $normalised[$index] = $seen[$key] > 1 ? $key.'#'.$seen[$key] : $key;
        }

        $columns = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($normalised as $index => $name) {
                if (in_array($name, $aliases, true) && ! in_array($index, $columns, true) && ! isset($columns[$field])) {
                    $columns[$field] = $index;
                }
            }
        }

        if (! isset($columns['employee_number'])) {
            throw new InvalidEmployeeImport('Kolom Nomor Induk Karyawan tidak ditemukan pada baris judul. Unduh templat untuk melihat nama kolom yang dikenali.');
        }

        if (! isset($columns['full_name'])) {
            throw new InvalidEmployeeImport('Kolom nama karyawan tidak ditemukan pada baris judul. Unduh templat untuk melihat nama kolom yang dikenali.');
        }

        return $columns;
    }

    /**
     * Turn one row into the attributes to store, or say what is wrong with it.
     *
     * @param  array<string, mixed>  $row
     * @param  array{company: string, branches: array<string, int>, departments: array<string, int>, positions: array<string, list<array{id: int, department_id: int}>>}  $lookups
     * @param  array<string, mixed>|null  $record
     */
    private function validate(array $row, array $lookups, bool $mayHandleSensitive, ?array &$record): ?string
    {
        $record = null;

        if ($row['company'] !== null && ! $this->sameCompany($row['company'], $lookups['company'])) {
            return "perusahaan \"{$row['company']}\" bukan perusahaan yang dipilih (\"{$lookups['company']}\").";
        }

        if ($row['branch'] !== null) {
            $branch = $lookups['branches'][mb_strtolower($row['branch'])] ?? null;

            if ($branch === null) {
                return "cabang \"{$row['branch']}\" belum ada.";
            }
        } elseif (count($lookups['branches']) === 1) {
            $branch = array_values($lookups['branches'])[0];
        } else {
            return 'cabang kosong.';
        }

        $department = null;
        if ($row['department'] !== null) {
            $department = $lookups['departments'][mb_strtolower($row['department'])] ?? null;

            if ($department === null) {
                return "divisi \"{$row['department']}\" belum ada sebagai departemen.";
            }
        }

        $position = null;
        if ($row['position'] !== null) {
            $candidates = $lookups['positions'][mb_strtolower($row['position'])] ?? [];

            if ($candidates === []) {
                return "posisi \"{$row['position']}\" belum ada.";
            }

            // The same name may exist under several departments; the row's Divisi decides which.
            $inDepartment = array_values(array_filter($candidates, fn (array $candidate): bool => $candidate['department_id'] === $department));
            $pool = $inDepartment !== [] ? $inDepartment : $candidates;

            if (count($pool) > 1) {
                return "posisi \"{$row['position']}\" ada di lebih dari satu departemen; isi kolom Divisi agar jelas.";
            }

            $position = $pool[0]['id'];
        }

        $status = EmploymentStatus::Active;
        if ($row['status'] !== null) {
            $status = self::STATUSES[$this->key($row['status'])] ?? null;

            if ($status === null) {
                return "status \"{$row['status']}\" tidak dikenali (aktif / nonaktif).";
            }
        }

        $type = null;
        if ($row['type'] !== null) {
            $type = self::TYPES[$this->key($row['type'])] ?? null;

            if ($type === null) {
                return "status karyawan \"{$row['type']}\" tidak dikenali (tetap / kontrak / percobaan / magang).";
            }
        }

        $gender = $row['gender'] === null ? null : Gender::fromSheet($row['gender']);
        if ($row['gender'] !== null && $gender === null) {
            return "jenis kelamin \"{$row['gender']}\" tidak dikenali (L / P).";
        }

        $religion = $row['religion'] === null ? null : Religion::fromSheet($row['religion']);
        if ($row['religion'] !== null && $religion === null) {
            return "agama \"{$row['religion']}\" tidak dikenali (Islam, Kristen, Katolik, Hindu, Buddha, Konghucu).";
        }

        $maritalStatus = $row['marital_status'] === null ? null : MaritalStatus::fromSheet($row['marital_status']);
        if ($row['marital_status'] !== null && $maritalStatus === null) {
            return "status perkawinan \"{$row['marital_status']}\" tidak dikenali (Belum Kawin, Kawin, Cerai Hidup, Cerai Mati).";
        }

        $ptkp = $row['ptkp_status'] === null ? null : PtkpStatus::fromSheet($row['ptkp_status']);
        if ($row['ptkp_status'] !== null && $ptkp === null) {
            return "status PTKP \"{$row['ptkp_status']}\" tidak dikenali (mis. TK/0, K/1, K/I/2).";
        }

        $zona = null;
        if ($row['zona'] !== null) {
            $zona = Zona::tryFrom($this->key($row['zona']));

            if ($zona === null) {
                return "zona \"{$row['zona']}\" tidak dikenali (hijau / merah / hitam).";
            }
        }

        $dates = [];
        foreach (['joined_on' => 'tanggal mulai', 'exited_on' => 'tanggal keluar', 'birth_date' => 'tanggal lahir'] as $field => $label) {
            $dates[$field] = null;

            if ($row[$field] !== null) {
                $dates[$field] = $this->parseDate($row[$field]);

                if ($dates[$field] === null) {
                    return "{$label} tidak dikenali (\"{$row[$field]}\"); gunakan 2026-12-31 atau 31/12/2026.";
                }
            }
        }

        if ($dates['joined_on'] !== null && $dates['exited_on'] !== null && $dates['exited_on'] < $dates['joined_on']) {
            return 'tanggal keluar sebelum tanggal mulai.';
        }

        if ($dates['birth_date'] !== null && $dates['birth_date'] >= now()->toDateString()) {
            return 'tanggal lahir harus sebelum hari ini.';
        }

        // Numbers typed with dots, dashes or spaces ("12.345.678.9-012.345") are read as their digits.
        $row['national_id'] = $this->digitsOnly($row['national_id']);
        $row['family_card_number'] = $this->digitsOnly($row['family_card_number']);
        $row['tax_number'] = $this->digitsOnly($row['tax_number']);

        $validator = Validator::make($row, [
            'employee_number' => ['max:50'],
            'full_name' => ['max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:50'],
            'address' => ['nullable', 'max:1000'],
            'id_address' => ['nullable', 'max:1000'],
            'national_id' => ['required', 'regex:/^\d{16}$/'],
            'family_card_number' => ['nullable', 'regex:/^\d{16}$/'],
            'tax_number' => ['nullable', 'regex:/^\d{15,16}$/'],
            'job_title' => ['nullable', 'max:150'],
            'residence_postal_code' => ['nullable', 'max:10'],
            'id_postal_code' => ['nullable', 'max:10'],
            'id_rt' => ['nullable', 'max:10'],
            'id_rw' => ['nullable', 'max:10'],
        ], [
            'national_id.required' => 'NIK kosong.',
            'national_id.regex' => 'NIK harus 16 digit angka (jika Excel mengubahnya menjadi 3,17E+15, format kolomnya sebagai teks).',
            'family_card_number.regex' => 'No KK harus 16 digit angka.',
            'tax_number.regex' => 'NPWP 16 harus 15 atau 16 digit angka.',
            'employee_number.max' => 'ID karyawan terlalu panjang.',
            'full_name.max' => 'nama terlalu panjang.',
            'email.email' => 'email tidak valid.',
            '*.max' => 'terlalu panjang.',
        ]);

        if ($validator->fails()) {
            return (string) $validator->errors()->first();
        }

        $record = [
            'employee_number' => $row['employee_number'],
            'full_name' => $row['full_name'],
            'branch_id' => $branch,
            'department_id' => $department,
            'position_id' => $position,
            'employment_status' => $status,
            'employment_type' => $type,
            'joined_on' => $dates['joined_on'],
            'exited_on' => $dates['exited_on'],
            'birth_date' => $dates['birth_date'],
            'national_id' => $row['national_id'],
            'gender' => $gender,
            'religion' => $religion,
            'marital_status' => $maritalStatus,
        ];

        foreach (self::TEXT_FIELDS as $field) {
            $record[$field] = $row[$field];
        }

        // Left out, not blanked, when the sheet does not say: a new employee is Hijau by default.
        if ($zona !== null) {
            $record['zona'] = $zona;
        }

        if ($mayHandleSensitive) {
            $record['ptkp_status'] = $ptkp;
            $record['family_card_number'] = $row['family_card_number'];
            $record['tax_number'] = $row['tax_number'];
        } else {
            $record = array_diff_key($record, array_flip($this->sensitiveFields()));
        }

        return null;
    }

    /**
     * The sheet's fields that need the sensitive-data permission.
     *
     * @return list<string>
     */
    private function sensitiveFields(): array
    {
        return array_values(array_map(
            fn (array $column): string => $column[1],
            array_filter(EmployeeSheet::COLUMNS, fn (array $column): bool => $column[2]),
        ));
    }

    /**
     * Turn each row's manager reference, a number or a full name, into the lower-cased employee number it points
     * at. A name shared by two people is refused rather than guessed at.
     *
     * @param  array<string, array{line: int, manager: string|null, record: array<string, mixed>}>  $valid
     * @param  array<string, int>  $existing
     * @param  array<string, list<string>>  $namesTaken
     * @param  list<string>  $errors
     * @return array<string, array{line: int, manager: string|null, record: array<string, mixed>}>
     */
    private function resolveManagerNames(array $valid, array $existing, array $namesTaken, array &$errors): array
    {
        $names = $namesTaken;

        foreach ($valid as $key => $row) {
            $names[$this->nameKey($row['record']['full_name'])][] = $key;
        }

        foreach ($valid as $key => $row) {
            if ($row['manager'] === null) {
                continue;
            }

            $reference = mb_strtolower($row['manager']);

            if (isset($existing[$reference]) || isset($valid[$reference])) {
                $valid[$key]['manager'] = $reference;

                continue;
            }

            $candidates = array_values(array_unique($names[$this->nameKey($row['manager'])] ?? []));

            if (count($candidates) > 1) {
                $errors[] = "Baris {$row['line']}: atasan \"{$row['manager']}\" cocok dengan lebih dari satu karyawan; tulis ID karyawannya.";
                unset($valid[$key]);
            } elseif (count($candidates) === 1) {
                $valid[$key]['manager'] = $candidates[0];
            }
        }

        return $valid;
    }

    /**
     * Reject rows whose manager cannot be resolved: not in the company, not in the file, a row that
     * was itself rejected, the employee themself, or a loop within the file. Repeats until stable,
     * because rejecting one row can strand the rows that report to it.
     *
     * @param  array<string, array{line: int, manager: string|null, record: array<string, mixed>}>  $valid
     * @param  array<string, int>  $existing
     * @param  list<string>  $errors
     * @return array<string, array{line: int, manager: string|null, record: array<string, mixed>}>
     */
    private function dropBrokenManagerLinks(array $valid, array $existing, array &$errors): array
    {
        do {
            $changed = false;

            foreach ($valid as $key => $row) {
                if ($row['manager'] === null) {
                    continue;
                }

                $manager = mb_strtolower($row['manager']);
                $problem = null;

                if ($manager === $key) {
                    $problem = 'karyawan tidak bisa menjadi atasan dirinya sendiri.';
                } elseif (! isset($valid[$manager]) && ! isset($existing[$manager])) {
                    $problem = "atasan \"{$row['manager']}\" tidak ditemukan.";
                } elseif ($this->loopsBack($key, $valid)) {
                    $problem = 'rantai atasan berputar kembali ke karyawan ini.';
                }

                if ($problem !== null) {
                    $errors[] = "Baris {$row['line']}: {$problem}";
                    unset($valid[$key]);
                    $changed = true;
                }
            }
        } while ($changed);

        return $valid;
    }

    /**
     * @param  array<string, array{line: int, manager: string|null, record: array<string, mixed>}>  $valid
     */
    private function loopsBack(string $start, array $valid): bool
    {
        $seen = [$start => true];
        $current = $valid[$start]['manager'] === null ? null : mb_strtolower($valid[$start]['manager']);

        while ($current !== null && isset($valid[$current])) {
            if (isset($seen[$current])) {
                return true;
            }

            $seen[$current] = true;
            $current = $valid[$current]['manager'] === null ? null : mb_strtolower($valid[$current]['manager']);
        }

        return false;
    }

    /**
     * Managers are created before the people who report to them, so each row is stored once, with
     * its manager already set.
     *
     * @param  array<string, array{line: int, manager: string|null, record: array<string, mixed>}>  $valid
     * @param  array<string, int>  $existing
     */
    private function create(array $valid, array $existing, int $companyId, ?User $by): void
    {
        DB::transaction(function () use ($valid, $existing, $companyId, $by): void {
            /** @var array<string, int> $ids */
            $ids = $existing;
            $pending = $valid;

            while ($pending !== []) {
                foreach ($pending as $key => $row) {
                    $manager = $row['manager'] === null ? null : mb_strtolower($row['manager']);

                    if ($manager !== null && ! isset($ids[$manager])) {
                        continue;
                    }

                    $employee = Employee::create([
                        ...$row['record'],
                        'company_id' => $companyId,
                        'manager_id' => $manager === null ? null : $ids[$manager],
                    ]);

                    $ids[$key] = $employee->id;
                    unset($pending[$key]);
                }
            }

            activity('employee')
                ->causedBy($by)
                ->withProperties(['company_id' => $companyId, 'created' => count($valid)])
                ->log('Impor karyawan dari berkas');
        });
    }

    /**
     * The company's NPWP numbers come from the file only while the company has none, so an import never overwrites
     * what someone typed in the admin panel.
     *
     * @param  array{npwp_15: string|null, npwp_22: string|null}  $numbers
     */
    private function fillCompanyNumbers(Company $company, array $numbers): void
    {
        foreach ($numbers as $field => $value) {
            if ($value !== null && $company->{$field} === null) {
                $company->{$field} = $value;
            }
        }

        $company->save();
    }

    /**
     * @param  iterable<Branch|Department>  $models
     * @return array<string, int>
     */
    private function namesToIds(iterable $models): array
    {
        $map = [];

        foreach ($models as $model) {
            $map[mb_strtolower(trim($model->name))] = $model->id;
        }

        return $map;
    }

    /**
     * @return array<string, list<array{id: int, department_id: int}>>
     */
    private function positionsByName(int $companyId): array
    {
        $map = [];

        foreach (Position::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name', 'department_id']) as $position) {
            $map[mb_strtolower(trim($position->name))][] = ['id' => $position->id, 'department_id' => $position->department_id];
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $namesByKey  employee number (lower-cased) => full name
     * @return array<string, list<string>> name key => employee numbers
     */
    private function namesToKeys(array $namesByKey): array
    {
        $map = [];

        foreach ($namesByKey as $key => $name) {
            $map[$this->nameKey($name)][] = $key;
        }

        return $map;
    }

    private function key(string $value): string
    {
        return preg_replace('/[^a-z]/', '', strtolower($value)) ?? '';
    }

    /**
     * Names compare without case, spacing or punctuation: "Budi  Santoso" and "budi santoso." are one person.
     */
    private function nameKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($name)) ?? '';
    }

    /**
     * Company names compare without "PT"/"CV"/"UD", case or punctuation, and one containing the other is enough:
     * the sheet says "PT Karya Logistik" where the app has "Karya Logistik Indonesia". The point is to stop a file
     * for another company, not to police spelling.
     */
    private function sameCompany(string $named, string $selected): bool
    {
        $strip = fn (string $name): string => preg_replace('/^(pt|cv|ud)/', '', $this->nameKey($name)) ?? '';

        $named = $strip($named);
        $selected = $strip($selected);

        return $named !== '' && $selected !== '' && (str_contains($named, $selected) || str_contains($selected, $named));
    }

    private function digitsOnly(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        return $digits === '' ? $value : $digits;
    }

    /**
     * Day-first only: guessing month-first would silently swap day and month on the first 12 days.
     */
    private function parseDate(string $value): ?string
    {
        foreach (self::DATE_FORMATS as $format) {
            if (CarbonImmutable::canBeCreatedFromFormat($value, $format)) {
                $parsed = CarbonImmutable::createFromFormat($format, $value);

                if ($parsed instanceof CarbonImmutable) {
                    return $parsed->toDateString();
                }
            }
        }

        return null;
    }

    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($line, $candidate);

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
