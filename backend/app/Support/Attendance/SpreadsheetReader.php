<?php

namespace App\Support\Attendance;

use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Reads the first sheet of an Excel file into plain rows, with formulas worked out to their values.
 *
 * The kind of file is told from its first bytes, not its name: an upload's temporary path has no
 * extension, and the Fingerspot software is known to save HTML under an .xls name.
 */
class SpreadsheetReader
{
    /**
     * The spreadsheet format of the file, or null when it is not one (plain text such as CSV).
     */
    public function formatOf(string $path): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 512);

        return match (true) {
            str_starts_with($head, "PK\x03\x04") => ExcelFormat::XLSX,
            str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") => ExcelFormat::XLS,
            preg_match('/^(\xEF\xBB\xBF)?\s*<(!doctype html|html|table|\?xml)/i', $head) === 1 => ExcelFormat::HTML,
            default => null,
        };
    }

    /**
     * @return array<int, list<mixed>> the first sheet's rows, keyed by their 1-based row number
     *
     * @throws InvalidScanLog when the file cannot be opened as a spreadsheet
     */
    public function rows(string $path, string $format): array
    {
        try {
            $sheets = Excel::toArray(new class implements WithCalculatedFormulas {}, $path, null, $format);
        } catch (Throwable) {
            throw new InvalidScanLog('Berkas Excel tidak dapat dibaca. Pastikan berkas tidak rusak atau terkunci kata sandi.');
        }

        $rows = [];

        foreach ($sheets[0] ?? [] as $index => $cells) {
            $rows[$index + 1] = array_values($cells);
        }

        return $rows;
    }
}
