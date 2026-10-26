<?php

namespace App\Support\Attendance;

use Closure;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The attendance recap as an Excel workbook, one sheet per cut (employee, Cabang, department, period).
 *
 * A CSV cannot carry styling or number formats, so this is the download to hand to people: a dark header
 * row, borders, a frozen header, bold totals, and real numbers, with the deduction formatted `#,##0` so
 * Excel groups the thousands the way the reader's own settings do (1.250.000 on an Indonesian system).
 *
 * Every cell is written with an explicit type. Names are user-entered, and a name starting with "=" must
 * stay text, never become a formula.
 *
 * @phpstan-type Column array{head: string, width: int, value: Closure(array<string, mixed>): (string|int|float|null), format?: string, text?: bool}
 */
class RecapWorkbook
{
    private const HEADER_FILL = 'FF1F2937';

    private const TOTAL_FILL = 'FFE5E7EB';

    private const BORDER = 'FFB6BCC6';

    private const FIRST_TABLE_ROW = 4;

    /**
     * @param  array{rows: list<array<string, mixed>>, branches: list<array<string, mixed>>, departments: list<array<string, mixed>>, periods: list<array<string, mixed>>, total: array<string, int|float>, dataThrough: string|null}  $result
     * @param  array<string, mixed>  $filters
     */
    public function build(array $result, array $filters): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Rekap absensi')->setCreator(config('app.name'));
        $subtitle = "{$filters['from']} s/d {$filters['to']} · Data sampai ".($result['dataThrough'] ?? '-');
        $total = $result['total'];
        $people = count($result['rows']);

        $this->sheet($book->getActiveSheet(), 'Per karyawan', $subtitle, [
            $this->text('Karyawan', 28, fn (array $row): string => (string) $row['name']),
            $this->text('ID', 14, fn (array $row): string => (string) $row['number']),
            $this->text('Cabang', 18, fn (array $row): string => (string) ($row['branch'] ?? '')),
            $this->text('Departemen', 20, fn (array $row): string => (string) ($row['department'] ?? '')),
            $this->text('Zona', 9, fn (array $row): string => (string) $row['zona']),
            $this->text('Sumber', 16, fn (array $row): string => $this->source($row)),
            ...$this->figureColumns(),
        ], $result['rows'], ['name' => "Total ({$people} karyawan)"] + $total + ['zona' => '', 'number' => '', 'branch' => null, 'department' => null, 'source' => 'total'], filter: true);

        $this->sheet($book->createSheet(), 'Per cabang', $subtitle, [
            $this->text('Cabang', 28, fn (array $row): string => (string) ($row['name'] ?? '(tanpa cabang)')),
            $this->number('Karyawan', 11, fn (array $row): int => (int) $row['employees']),
            ...$this->figureColumns(),
        ], $result['branches'], ['name' => 'Total', 'employees' => $people] + $total);

        $this->sheet($book->createSheet(), 'Per departemen', $subtitle, [
            $this->text('Departemen', 28, fn (array $row): string => (string) ($row['name'] ?? '(tanpa departemen)')),
            $this->number('Karyawan', 11, fn (array $row): int => (int) $row['employees']),
            ...$this->figureColumns(),
        ], $result['departments'], ['name' => 'Total', 'employees' => $people] + $total);

        $this->sheet($book->createSheet(), 'Per periode', $subtitle.' · Cabang rekapitulasi tidak ikut', [
            $this->text('Periode', 20, fn (array $row): string => (string) $row['label']),
            $this->text('Tanggal', 24, fn (array $row): string => "{$row['from']} s/d {$row['to']}"),
            ...$this->figureColumns(),
        ], $result['periods'], ['label' => 'Total', 'from' => $filters['from'], 'to' => $filters['to']] + $total);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * The figures every sheet shares. A person counted from a rekapitulasi has no days, so what only days can
     * say is left empty; one whose Cabang has no confirmed file in the range has nothing at all yet.
     *
     * @return list<Column>
     */
    private function figureColumns(): array
    {
        $days = fn (string $key): Closure => fn (array $row): ?int => ($row['source'] ?? null) === 'rekap' ? null : (int) $row[$key];
        $counted = fn (string $key): Closure => fn (array $row): ?int => ($row['rekapMissing'] ?? false) ? null : (int) $row[$key];

        return [
            $this->number('Hadir', 9, $days('present')),
            $this->number('Alpa', 9, $counted('alpa')),
            $this->number('Izin', 9, $counted('izin')),
            $this->number('Sakit', 9, $counted('sakit')),
            $this->number('Telat', 9, $counted('telat')),
            $this->number('Potongan (Rp)', 16, $counted('potongan'), '#,##0'),
            $this->number('Pulang cepat', 13, $days('earlyLeave')),
            $this->number('Cuti lain', 10, $days('otherLeave')),
            $this->number('Belum ada data', 15, $days('noData')),
            $this->number('Jam kerja', 11, fn (array $row): ?float => ($row['source'] ?? null) === 'rekap' ? null : round((int) $row['minutes'] / 60, 2), '0.00'),
        ];
    }

    private function source(array $row): string
    {
        return match ($row['source'] ?? 'scanner') {
            'total' => '',
            'rekap' => ($row['rekapMissing'] ?? false) ? 'Rekap (belum ada)' : 'Rekap',
            default => 'Pemindai',
        };
    }

    /**
     * @param  Closure(array<string, mixed>): (string|int|float|null)  $value
     * @return Column
     */
    private function text(string $head, int $width, Closure $value): array
    {
        return ['head' => $head, 'width' => $width, 'value' => $value, 'text' => true];
    }

    /**
     * @param  Closure(array<string, mixed>): (string|int|float|null)  $value
     * @return Column
     */
    private function number(string $head, int $width, Closure $value, string $format = '0'): array
    {
        return ['head' => $head, 'width' => $width, 'value' => $value, 'format' => $format];
    }

    /**
     * One sheet: a title, the range, the table with a styled header, and a bold total line.
     *
     * @param  list<Column>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $total
     */
    private function sheet(Worksheet $sheet, string $title, string $subtitle, array $columns, array $rows, array $total, bool $filter = false): void
    {
        $sheet->setTitle($title);
        $last = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->setCellValueExplicit('A1', "Rekap absensi — {$title}", DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValueExplicit('A2', $subtitle, DataType::TYPE_STRING);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setARGB('FF4B5563');

        $headerRow = self::FIRST_TABLE_ROW;

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValueExplicit("{$letter}{$headerRow}", $column['head'], DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }

        $header = $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}");
        $header->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $header->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $row = $headerRow;

        foreach ([...$rows, $total] as $position => $line) {
            $row++;
            $isTotal = $position === count($rows);

            foreach ($columns as $index => $column) {
                $this->write($sheet, Coordinate::stringFromColumnIndex($index + 1).$row, $column, $line);
            }

            if ($isTotal) {
                $style = $sheet->getStyle("A{$row}:{$last}{$row}");
                $style->getFont()->setBold(true);
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);
                $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
            }
        }

        $table = $sheet->getStyle("A{$headerRow}:{$last}{$row}");
        $table->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::BORDER);
        $table->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->freezePane('B'.($headerRow + 1));

        if ($filter && $rows !== []) {
            $sheet->setAutoFilter("A{$headerRow}:{$last}".($headerRow + count($rows)));
        }

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getSheetView()->setZoomScale(100);
    }

    /**
     * @param  Column  $column
     * @param  array<string, mixed>  $line
     */
    private function write(Worksheet $sheet, string $cell, array $column, array $line): void
    {
        $value = ($column['value'])($line);

        if ($value === null || $value === '') {
            return;
        }

        if ($column['text'] ?? false) {
            $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);

            return;
        }

        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
        $style = $sheet->getStyle($cell);
        $style->getNumberFormat()->setFormatCode($column['format'] ?? '0');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
}
