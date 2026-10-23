<?php

namespace App\Actions\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Support\Employees\InvalidEmployeeImport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

/**
 * Bulk-loads employees from a CSV (EMP-DIR-010).
 *
 * What it does and does not do, so a surprise is never silent:
 *  - It only ever creates. An employee number that already exists in the company is skipped and
 *    counted as a duplicate, never updated: a re-uploaded file must not overwrite edits made since.
 *  - Bad rows are rejected one by one with their line number; good rows still load.
 *  - Columns are found by header name (English or Indonesian). Bank, payroll and health columns
 *    are not imported: they are sensitive, and are entered on the profile by someone allowed to.
 *  - A Cabang, department or position must already exist by name, and the manager is given as an
 *    employee number, either already in the company or elsewhere in the same file. Nothing is
 *    auto-created. Every employee needs a Cabang: a blank one is read as the company's only Cabang,
 *    so a file made before Cabang existed still loads, and is an error once there are several.
 *  - With $dryRun nothing is written: the outcome is what an import would do.
 */
class ImportEmployees
{
    public const MAX_ROWS = 2000;

    /**
     * Header names (lower-cased, punctuation removed) that identify each column.
     *
     * @var array<string, list<string>>
     */
    public const ALIASES = [
        'employee_number' => ['idkaryawan', 'employeenumber', 'employeeid', 'nomorkaryawan', 'nip', 'empno', 'id'],
        'full_name' => ['nama', 'namalengkap', 'fullname', 'name'],
        'branch' => ['cabang', 'branch', 'kantor', 'office'],
        'department' => ['departemen', 'department', 'divisi'],
        'position' => ['jabatan', 'position', 'posisi'],
        'manager' => ['idatasan', 'atasan', 'manager', 'managerid', 'idmanager'],
        'status' => ['status', 'statuskaryawan', 'employmentstatus'],
        'type' => ['tipe', 'jenis', 'tipekaryawan', 'employmenttype', 'statuskepegawaian'],
        'joined_on' => ['tanggalbergabung', 'tanggalmasuk', 'tglmasuk', 'joinedon', 'joindate', 'joined'],
        'exited_on' => ['tanggalkeluar', 'tglkeluar', 'exitedon', 'exitdate'],
        'email' => ['email', 'surel'],
        'phone' => ['telepon', 'telp', 'hp', 'nohp', 'phone', 'nomortelepon'],
        'address' => ['alamat', 'address'],
        'national_id' => ['nik', 'noktp', 'ktp', 'nationalid'],
    ];

    private const STATUSES = [
        'aktif' => EmploymentStatus::Active, 'active' => EmploymentStatus::Active,
        'nonaktif' => EmploymentStatus::Inactive, 'tidakaktif' => EmploymentStatus::Inactive, 'inactive' => EmploymentStatus::Inactive,
    ];

    private const TYPES = [
        'tetap' => EmploymentType::Permanent, 'permanent' => EmploymentType::Permanent,
        'kontrak' => EmploymentType::Contract, 'contract' => EmploymentType::Contract,
        'percobaan' => EmploymentType::Probation, 'probation' => EmploymentType::Probation,
        'magang' => EmploymentType::Intern, 'intern' => EmploymentType::Intern,
    ];

    /**
     * @var list<string>
     */
    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y'];

    /**
     * @return array{total: int, created: int, duplicates: int, rejected: int, errors: list<string>, dryRun: bool}
     *
     * @throws InvalidEmployeeImport
     */
    public function handle(string $path, int $companyId, ?User $by, bool $dryRun = false): array
    {
        [$rows, $errors] = $this->read($path);
        $total = count($rows) + count($errors);

        $branches = $this->namesToIds(Branch::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name']));
        $departments = $this->namesToIds(Department::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name']));
        $positions = $this->namesToIds(Position::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name']));
        $existing = Employee::acrossCompanies()->where('company_id', $companyId)->pluck('id', 'employee_number')
            ->mapWithKeys(fn (int $id, string $number): array => [mb_strtolower($number) => $id])->all();

        $takenNiks = Employee::acrossCompanies()->whereNotNull('national_id')->pluck('national_id')->flip()->all();

        $valid = [];
        $duplicates = 0;

        foreach ($rows as $row) {
            $key = mb_strtolower($row['employee_number']);

            if (isset($existing[$key]) || isset($valid[$key])) {
                $duplicates++;

                continue;
            }

            $problem = $this->validate($row, $branches, $departments, $positions, $record);

            if ($problem !== null) {
                $errors[] = "Baris {$row['line']}: {$problem}";

                continue;
            }

            // NIK is unique across the group: against what is already stored and against the file.
            if (isset($takenNiks[$row['national_id']])) {
                $errors[] = "Baris {$row['line']}: NIK {$row['national_id']} sudah terdaftar pada karyawan lain.";

                continue;
            }

            $takenNiks[$row['national_id']] = true;

            $valid[$key] = ['line' => $row['line'], 'manager' => $row['manager'], 'record' => $record];
        }

        $valid = $this->dropBrokenManagerLinks($valid, $existing, $errors);

        if (! $dryRun && $valid !== []) {
            $this->create($valid, $existing, $companyId, $by);
        }

        usort($errors, fn (string $a, string $b): int => (int) substr($a, 6) <=> (int) substr($b, 6));

        return [
            'total' => $total,
            'created' => count($valid),
            'duplicates' => $duplicates,
            'rejected' => count($errors),
            'errors' => $errors,
            'dryRun' => $dryRun,
        ];
    }

    /**
     * Rows keyed by header field, with the file line they came from. Rows that cannot even be
     * read (no ID or no name) are returned as errors instead.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     *
     * @throws InvalidEmployeeImport
     */
    private function read(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $columns = null;
        $delimiter = ',';
        $lineNumber = 0;
        $rows = [];
        $errors = [];

        foreach ($file as $line) {
            $lineNumber++;

            if (! is_string($line) || trim($line) === '') {
                continue;
            }

            if ($columns === null) {
                $line = str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
                $delimiter = $this->detectDelimiter($line);
                $columns = $this->mapColumns(str_getcsv($line, $delimiter, '"', ''));

                continue;
            }

            if (count($rows) + count($errors) >= self::MAX_ROWS) {
                throw new InvalidEmployeeImport('Berkas terlalu besar: maksimal '.self::MAX_ROWS.' baris per impor. Bagi menjadi beberapa berkas.');
            }

            $cells = str_getcsv($line, $delimiter, '"', '');
            $row = ['line' => $lineNumber];

            foreach (array_keys(self::ALIASES) as $field) {
                $value = isset($columns[$field]) ? trim((string) ($cells[$columns[$field]] ?? '')) : '';
                $row[$field] = $value === '' ? null : $value;
            }

            if ($row['employee_number'] === null || $row['full_name'] === null) {
                $errors[] = "Baris {$lineNumber}: ".($row['employee_number'] === null ? 'ID karyawan' : 'nama').' kosong.';

                continue;
            }

            // A spreadsheet round trip turns "1042" into "1042.0"; the number is an identifier.
            $row['employee_number'] = preg_match('/^(\d+)\.0+$/', $row['employee_number'], $match) === 1 ? $match[1] : $row['employee_number'];

            $rows[] = $row;
        }

        if ($columns === null) {
            throw new InvalidEmployeeImport('Berkas kosong.');
        }

        return [$rows, $errors];
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     *
     * @throws InvalidEmployeeImport
     */
    private function mapColumns(array $header): array
    {
        $normalised = array_map(
            fn (?string $name): string => preg_replace('/[^a-z0-9]/', '', strtolower((string) $name)) ?? '',
            $header,
        );

        $columns = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($normalised as $index => $name) {
                if (in_array($name, $aliases, true) && ! in_array($index, $columns, true) && ! isset($columns[$field])) {
                    $columns[$field] = $index;
                }
            }
        }

        if (! isset($columns['employee_number'])) {
            throw new InvalidEmployeeImport('Kolom ID karyawan tidak ditemukan pada baris judul. Unduh templat untuk melihat nama kolom yang dikenali.');
        }

        if (! isset($columns['full_name'])) {
            throw new InvalidEmployeeImport('Kolom nama tidak ditemukan pada baris judul. Unduh templat untuk melihat nama kolom yang dikenali.');
        }

        return $columns;
    }

    /**
     * Turn one row into the attributes to store, or say what is wrong with it.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $branches
     * @param  array<string, int>  $departments
     * @param  array<string, int>  $positions
     * @param  array<string, mixed>|null  $record
     */
    private function validate(array $row, array $branches, array $departments, array $positions, ?array &$record): ?string
    {
        $record = null;

        if ($row['branch'] !== null) {
            $branch = $branches[mb_strtolower($row['branch'])] ?? null;

            if ($branch === null) {
                return "cabang \"{$row['branch']}\" belum ada.";
            }
        } elseif (count($branches) === 1) {
            $branch = array_values($branches)[0];
        } else {
            return 'cabang kosong.';
        }

        $department = null;
        if ($row['department'] !== null) {
            $department = $departments[mb_strtolower($row['department'])] ?? null;

            if ($department === null) {
                return "departemen \"{$row['department']}\" belum ada.";
            }
        }

        $position = null;
        if ($row['position'] !== null) {
            $position = $positions[mb_strtolower($row['position'])] ?? null;

            if ($position === null) {
                return "jabatan \"{$row['position']}\" belum ada.";
            }
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
                return "tipe \"{$row['type']}\" tidak dikenali (tetap / kontrak / percobaan / magang).";
            }
        }

        $dates = [];
        foreach (['joined_on' => 'tanggal bergabung', 'exited_on' => 'tanggal keluar'] as $field => $label) {
            $dates[$field] = null;

            if ($row[$field] !== null) {
                $dates[$field] = $this->parseDate($row[$field]);

                if ($dates[$field] === null) {
                    return "{$label} tidak dikenali (\"{$row[$field]}\"); gunakan 2026-12-31 atau 31/12/2026.";
                }
            }
        }

        if ($dates['joined_on'] !== null && $dates['exited_on'] !== null && $dates['exited_on'] < $dates['joined_on']) {
            return 'tanggal keluar sebelum tanggal bergabung.';
        }

        $validator = Validator::make($row, [
            'employee_number' => ['max:50'],
            'full_name' => ['max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:50'],
            'address' => ['nullable', 'max:1000'],
            'national_id' => ['required', 'regex:/^\d{16}$/'],
        ], [
            'national_id.required' => 'NIK kosong.',
            'national_id.regex' => 'NIK harus 16 digit angka (jika Excel mengubahnya menjadi 3,17E+15, format kolomnya sebagai teks).',
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
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'national_id' => $row['national_id'],
        ];

        return null;
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
                ->log('Impor karyawan dari CSV');
        });
    }

    /**
     * @param  iterable<Branch|Department|Position>  $models
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

    private function key(string $value): string
    {
        return preg_replace('/[^a-z]/', '', strtolower($value)) ?? '';
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
