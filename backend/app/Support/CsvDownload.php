<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams rows as a CSV download that opens correctly in Excel.
 *
 * Two details matter. A byte-order mark is written first, or Excel reads UTF-8 names (accents,
 * Indonesian place names) as mojibake. And any cell that begins with =, +, - or @ is prefixed with
 * an apostrophe, because a spreadsheet would otherwise run it as a formula: an employee name or a
 * free-text reason is user-entered data, and exporting it must not be a way to inject one.
 */
class CsvDownload
{
    /**
     * @param  iterable<array<int, string|int|float|null>>  $rows
     */
    public static function stream(string $filename, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::safe(...), $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function safe(string|int|float|null $cell): string
    {
        $text = (string) $cell;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
