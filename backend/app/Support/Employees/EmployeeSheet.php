<?php

namespace App\Support\Employees;

use App\Models\Employee;

/**
 * The layout of HR's employee data sheet ("Lembar data field karyawan"): its columns in order, under the
 * names HR gave them. The template download, the directory export and the importer all start from this, so a file
 * exported from one company loads into another and HR's own sheet loads as it is.
 *
 * Headers are kept as HR wrote them, typos included: the importer matches columns by a normalised header name, so
 * a corrected spelling reads just as well.
 */
class EmployeeSheet
{
    /**
     * Header, field, and whether the column is sensitive (read, and written, only with the sensitive-data permission).
     * Fields that are not a column of the employees table are explained in `value()`.
     *
     * @var list<array{0: string, 1: string, 2: bool}>
     */
    public const COLUMNS = [
        ['Nama Perusahaan', 'company', false],
        ['NPWP 15 PERUSAHAAN', 'company_npwp_15', false],
        ['NPWP 22_PERESUHAAN', 'company_npwp_22', false],
        ['Nomor Induk Karyawan', 'employee_number', false],
        ['Nama Karyawan', 'full_name', false],
        ['Jenis Kelamin', 'gender', false],
        ['Lokasi Karyawan kerja', 'branch', false],
        ['Status PTKP', 'ptkp_status', true],
        ['Tanggal Mulai', 'joined_on', false],
        ['Tanggal Keluar', 'exited_on', false],
        ['Tempat Lahir', 'birth_place', false],
        ['Tanggal Lahir', 'birth_date', false],
        ['Agama Karyawan', 'religion', false],
        ['Status Perkawinan', 'marital_status', false],
        ['Alamat Tempat Tinggal', 'address', false],
        ['Kelurahan Tempat Tinggal', 'residence_village', false],
        ['Kecamatan Tempat Tinggal', 'residence_district', false],
        ['Kabupaten Tempat Tinggal', 'residence_city', false],
        ['Propinsi Tempat Tinggal', 'residence_province', false],
        ['Kode Pos Tempat Tinggal', 'residence_postal_code', false],
        ['No KK', 'family_card_number', true],
        ['No KTP', 'national_id', false],
        ['Nama IBU Karyawan', 'mother_name', true],
        ['ALamat KTP', 'id_address', false],
        ['Blok KTP', 'id_block', false],
        ['No_KTP', 'id_house_number', false],
        ['RT KTP', 'id_rt', false],
        ['RW KTP', 'id_rw', false],
        ['Kelurahan KTP', 'id_village', false],
        ['Kecamatan KTP', 'id_district', false],
        ['Kabupaten KTP', 'id_city', false],
        ['Provinsi KTP', 'id_province', false],
        ['Kode Pos KTP', 'id_postal_code', false],
        ['Divisi', 'department', false],
        ['Jabatan', 'job_title', false],
        ['Posisi', 'position', false],
        ['No Rekening', 'bank_account_number', true],
        ['NAMA_REKENING', 'bank_account_holder', true],
        ['NAMA_BANK', 'bank_name', true],
        ['CABANG BANK', 'bank_branch', true],
        ['NPWP 16', 'tax_number', true],
        ['No Handphone', 'phone', false],
        ['No BPJS Ketenagakerjaan', 'bpjs_employment_number', true],
        ['No BPJS Kesehatan', 'bpjs_health_number', true],
        ['Alamat Email', 'email', false],
        ['Status Karyawan', 'employment_type', false],
        ['Pendidikan Terakhir', 'education_level', false],
        ['Zona Absen Karyawan', 'zona', false],
        ['Supervisor', 'manager', false],
    ];

    /**
     * Columns that hold identifiers or phone numbers: Excel must keep them as text, or a 16-digit NIK becomes
     * 3,17E+15 and a leading zero is lost.
     *
     * @var list<string>
     */
    public const TEXT_FIELDS = [
        'company_npwp_15', 'company_npwp_22', 'employee_number', 'residence_postal_code', 'family_card_number',
        'national_id', 'id_rt', 'id_rw', 'id_house_number', 'id_postal_code', 'bank_account_number', 'tax_number',
        'phone', 'bpjs_employment_number', 'bpjs_health_number',
    ];

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return array_column(self::COLUMNS, 0);
    }

    /**
     * One employee as a row of the sheet. Sensitive columns are left blank unless asked for, and so is the Supervisor
     * when the reader may not see that person (someone does not see their own manager). The employee must have its
     * company, branch, department, position and manager loaded.
     *
     * @return list<string|null>
     */
    public static function row(Employee $employee, bool $withSensitive, bool $showManager = true): array
    {
        return array_map(function (array $column) use ($employee, $withSensitive, $showManager): ?string {
            [, $field, $sensitive] = $column;

            if (($sensitive && ! $withSensitive) || ($field === 'manager' && ! $showManager)) {
                return null;
            }

            return self::value($employee, $field);
        }, self::COLUMNS);
    }

    private static function value(Employee $employee, string $field): ?string
    {
        $value = match ($field) {
            'company' => $employee->company?->name,
            'company_npwp_15' => $employee->company?->npwp_15,
            'company_npwp_22' => $employee->company?->npwp_22,
            'branch' => $employee->branch?->name,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'manager' => $employee->manager?->full_name,
            default => $employee->{$field},
        };

        return match (true) {
            $value === null => null,
            $value instanceof \BackedEnum => method_exists($value, 'label') ? $value->label() : (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => (string) $value,
        };
    }

    /**
     * The example row of the template.
     *
     * @return list<string>
     */
    public static function sample(): array
    {
        $values = [
            'company' => 'PT Contoh Sejahtera', 'company_npwp_15' => '01.234.567.8-901.000', 'company_npwp_22' => '0123456789012000000000',
            'employee_number' => 'EMP-001', 'full_name' => 'Budi Santoso', 'gender' => 'Laki-laki', 'branch' => 'Pusat',
            'ptkp_status' => 'K/1', 'joined_on' => '2024-01-15', 'exited_on' => '', 'birth_place' => 'Surabaya',
            'birth_date' => '1990-05-20', 'religion' => 'Islam', 'marital_status' => 'Kawin',
            'address' => 'Jl. Contoh No. 1', 'residence_village' => 'Menteng', 'residence_district' => 'Menteng',
            'residence_city' => 'Jakarta Pusat', 'residence_province' => 'DKI Jakarta', 'residence_postal_code' => '10310',
            'family_card_number' => '3174000000000002', 'national_id' => '3174000000000001', 'mother_name' => 'Siti Aminah',
            'id_address' => 'Jl. Contoh No. 1', 'id_block' => '', 'id_house_number' => '1', 'id_rt' => '001', 'id_rw' => '002',
            'id_village' => 'Menteng', 'id_district' => 'Menteng', 'id_city' => 'Jakarta Pusat', 'id_province' => 'DKI Jakarta',
            'id_postal_code' => '10310', 'department' => 'Operasional', 'job_title' => 'Staff', 'position' => 'Admin Gudang',
            'bank_account_number' => '1234567890', 'bank_account_holder' => 'Budi Santoso', 'bank_name' => 'BCA',
            'bank_branch' => 'KCP Menteng', 'tax_number' => '0123456789012000', 'phone' => '081200000000',
            'bpjs_employment_number' => '12345678901', 'bpjs_health_number' => '0001234567890',
            'email' => 'budi@example.com', 'employment_type' => 'Tetap',
            'education_level' => 'S1', 'zona' => 'Hijau', 'manager' => '',
        ];

        return array_map(fn (array $column): string => $values[$column[1]], self::COLUMNS);
    }
}
