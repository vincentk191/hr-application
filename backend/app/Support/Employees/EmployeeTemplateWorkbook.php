<?php

namespace App\Support\Employees;

use App\Actions\Employees\ImportEmployees;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\Zona;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The template for the employee import: HR's data sheet with an example row, and a second sheet listing the words
 * the importer accepts.
 *
 * An Excel file rather than a CSV because Excel opens a CSV with every long number turned into 3,17E+15 and every
 * leading zero lost. Here the identifier, phone and date columns are formatted as text before anyone types in them.
 */
class EmployeeTemplateWorkbook
{
    private const HEADER_FILL = 'FF1F2937';

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Templat data karyawan')->setCreator(config('app.name'));

        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Data karyawan');

        $textFields = [...EmployeeSheet::TEXT_FIELDS, 'joined_on', 'exited_on', 'birth_date'];
        $sample = EmployeeSheet::sample();

        foreach (EmployeeSheet::COLUMNS as $index => [$header, $field]) {
            $column = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->setCellValueExplicit("{$column}1", $header, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("{$column}2", $sample[$index], DataType::TYPE_STRING);
            $sheet->getColumnDimension($column)->setWidth(max(14, min(32, mb_strlen($header) + 4)));

            if (in_array($field, $textFields, true)) {
                $sheet->getStyle("{$column}2:{$column}".(ImportEmployees::MAX_ROWS + 1))
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        }

        $last = Coordinate::stringFromColumnIndex(count(EmployeeSheet::COLUMNS));
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
        ]);
        $sheet->freezePane('A2');

        $this->guide($book->createSheet());
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function guide(Worksheet $sheet): void
    {
        $sheet->setTitle('Petunjuk');

        $labels = fn (array $cases): string => implode(', ', array_map(fn ($case): string => $case->label(), $cases));

        $lines = [
            ['Petunjuk pengisian', ''],
            ['Baris pertama sheet "Data karyawan" adalah judul kolom: urutannya boleh diubah, namanya jangan.', ''],
            ['Baris kedua adalah contoh; hapus sebelum mengunggah.', ''],
            ['', ''],
            ['Wajib diisi', 'Nomor Induk Karyawan, Nama Karyawan, No KTP (16 digit)'],
            ['Nama Perusahaan', 'Harus perusahaan yang dipilih di pojok atas saat mengunggah.'],
            ['Lokasi Karyawan kerja', 'Nama cabang yang sudah ada. Boleh kosong bila perusahaan hanya punya satu cabang.'],
            ['Divisi, Posisi', 'Nama departemen dan posisi yang sudah ada di aplikasi (Jabatan adalah isian bebas).'],
            ['Supervisor', 'Nomor induk atau nama lengkap atasan, yang sudah ada atau ada di berkas yang sama.'],
            ['Jenis Kelamin', $labels(Gender::cases()).' (atau L / P)'],
            ['Agama Karyawan', $labels(Religion::cases())],
            ['Status Perkawinan', $labels(MaritalStatus::cases())],
            ['Status PTKP', $labels(PtkpStatus::cases())],
            ['Status Karyawan', $labels(EmploymentType::cases())],
            ['Zona Absen Karyawan', $labels(Zona::cases()).' (kosong = Hijau)'],
            ['Tanggal', '2026-12-31 atau 31/12/2026'],
            ['Kolom sensitif', 'Bank, NPWP, KK, nama ibu, PTKP, BPJS dan asuransi hanya dibaca bila yang mengunggah berhak melihat data sensitif.'],
        ];

        foreach ($lines as $index => [$left, $right]) {
            $row = $index + 1;
            $sheet->setCellValueExplicit("A{$row}", $left, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", $right, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(90);
    }
}
