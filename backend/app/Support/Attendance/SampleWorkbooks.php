<?php

namespace App\Support\Attendance;

use App\Enums\Zona;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The example files offered beside the two attendance uploads on "Data absensi": what a Laporan Absensi from the scanner
 * looks like, and what a Cabang's rekapitulasi looks like.
 *
 * They are built here rather than kept as files so they cannot drift from the readers, and they follow the real files
 * rather than the readers' minimum: the same columns in the same order, the same kind of value in each (a number where the
 * Fingerspot software writes a number, text where it writes text), and the same kinds of row (a day worked, a day on
 * approved duty, a day absent). Tests compare them with the real exports kept in tests/Fixtures and feed each one back
 * through {@see ScanLogParser} and {@see RekapParser}. Every name, PIN and number in them is made up. Only the first sheet is
 * read when a file is uploaded, so the explanation sits on a sheet of its own after the data.
 */
class SampleWorkbooks
{
    private const HEADER_FILL = 'FF1F2937';

    /** The week the scanner sample covers: Monday to Friday. */
    private const WEEK_START = '2026-11-09';

    private const OFFICE = 'KANTOR CONTOH';

    private const DEVICE_SN = '60000000000001';

    /** A day on approved duty: the software counts it, and there are no scans. */
    private const DUTY = 'dinas';

    /**
     * Made-up people for the scanner sample, with each day's scans: [in, out], null for a day absent, or self::DUTY.
     * Wednesday of the first person is a day absent, the second arrives late on Tuesday, and the third is on duty on
     * Wednesday and forgets to scan out on Thursday.
     *
     * @var list<array{pin: string, nip: string, name: string, position: string, department: string, days: list<array{0: string, 1: string|null}|string|null>}>
     */
    private const PEOPLE = [
        [
            'pin' => '1001', 'nip' => '100000000000001', 'name' => 'Budi Santoso', 'position' => 'STAF', 'department' => 'GUDANG',
            'days' => [['07:52:10', '17:04:31'], ['07:55:48', '17:01:12'], null, ['07:49:03', '17:10:55'], ['07:58:20', '17:36:02']],
        ],
        [
            'pin' => '1002', 'nip' => '100000000000002', 'name' => 'Siti Rahma', 'position' => 'STAF', 'department' => 'KEUANGAN',
            'days' => [['07:57:41', '17:02:09'], ['08:21:15', '17:05:44'], ['07:56:30', '17:00:48'], ['07:59:02', '17:03:27'], ['07:54:11', '17:33:19']],
        ],
        [
            'pin' => '1003', 'nip' => '100000000000003', 'name' => 'Agus Wijaya', 'position' => 'PENYELIA', 'department' => 'GUDANG',
            'days' => [['07:45:22', '17:15:03'], ['07:50:37', '17:08:41'], self::DUTY, ['07:51:55', null], ['07:47:30', '17:40:12']],
        ],
    ];

    private const DAY_NAMES = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT'];

    /** The Fingerspot daily report's columns, in its own order. */
    private const DAILY_COLUMNS = [
        'Tanggal', 'Jadwal', 'Jam kerja', 'Valid', 'PIN', 'NIP', 'Nama', 'JABATAN', 'DEPARTEMEN', 'KANTOR', 'Libur umum',
        'Libur rutin', 'Lembur', 'Jam Masuk', 'Scan masuk', 'SN scan masuk', 'Izin terlambat', 'Terlambat', 'Scan Istirahat 1',
        'Scan Istirahat 2', 'Istirahat', 'Lembur istirahat', 'Izin pulang awal', 'Pulang awal', 'Jam Pulang', 'Scan pulang',
        'SN scan pulang', 'Durasi', 'Dihitung', 'Lembur akhir', 'Keterangan',
    ];

    /** The Fingerspot raw log's columns, in its own order. */
    private const LOG_COLUMNS = [
        'Tanggal scan', 'Tanggal', 'Jam', 'PIN', 'NIP', 'Nama', 'JABATAN', 'DEPARTEMEN', 'KANTOR', 'Verifikasi', 'I/O',
        'Workcode', 'SN', 'Mesin',
    ];

    /**
     * The branch sample's people: [name, position, zona, alpa, izin, sakit, telat].
     *
     * @var list<array{0: string, 1: string, 2: Zona, 3: int, 4: int, 5: int, 6: int}>
     */
    private const REKAP_PEOPLE = [
        ['Budi Santoso', 'SOPIR FORKLIFT', Zona::Merah, 0, 0, 0, 0],
        ['Siti Rahma', 'ADMINISTRASI KEUANGAN', Zona::Hijau, 0, 0, 2, 1],
        ['Agus Wijaya', 'CHECKER', Zona::Merah, 1, 0, 0, 2],
        ['Dewi Lestari', 'TEKNISI', Zona::Hitam, 0, 1, 0, 0],
        ['Rudi Hartono', 'SUPERVISOR', Zona::Hijau, 0, 0, 0, 3],
        ['Nina Kusuma', 'WAREHOUSE SUPPORT', Zona::Merah, 0, 0, 1, 0],
    ];

    private const REKAP_PERIOD = 'OKTOBER 2026';

    private const REKAP_DATES = '21/09/2026 s.d 20/10/2026';

    /** How the branches' own sheet writes rupiah: a dash for zero. */
    private const ACCOUNTING = '_ * #,##0_ ;_ * \-#,##0_ ;_ * "-"_ ;_ @_ ';

    public function __construct(private PotonganCalculator $potongan) {}

    /**
     * A Laporan Absensi from the scanner: the daily report on the first sheet, the raw log (the other layout the import
     * reads) on the second, and what to know on the third.
     */
    public function scanLog(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Contoh Laporan Absensi')->setCreator(config('app.name'));

        $daily = $book->getActiveSheet()->setTitle('Laporan harian');
        $this->headerRow($daily, 1, self::DAILY_COLUMNS);
        $this->fillDaily($daily);

        $log = $book->createSheet()->setTitle('Log scan');
        $this->headerRow($log, 1, self::LOG_COLUMNS);
        $this->fillLog($log);

        $this->notes($book, 'Petunjuk', [
            'Contoh Laporan Absensi dari pemindai. Semua nama, PIN dan NIP di sini rekaan.',
            '',
            'Yang bisa diimpor, dua bentuk (lembar pertama berkas yang dibaca; berkas CSV dengan kolom yang sama juga bisa):',
            '1. Laporan harian Fingerspot (lembar "Laporan harian"): satu baris per karyawan per hari, dengan "Scan masuk", "Scan pulang" dan sampai dua "Scan Istirahat". Kolom yang dibaca: PIN, Tanggal, scan-scan itu, serta NIP dan SN bila ada. Kolom lain (Jadwal, Valid, Terlambat, Durasi, Dihitung, lembur, dan seterusnya) hanya catatan perangkat lunak pemindai dan diabaikan: aplikasi menghitung hadir, terlambat dan pulang awal sendiri dari scan, cuti dan jam kerjanya.',
            '2. Log scan (lembar "Log scan"): satu baris per scan, dengan PIN dan "Tanggal scan" (atau "Tanggal" dan "Jam" terpisah). NIP dan SN opsional.',
            '',
            'Contoh ini memuat tiga macam hari yang ada di laporan sebenarnya: hari hadir dengan scan, hari tugas atau cuti (dihitung, tanpa scan) dan hari tidak hadir. Baris tanpa scan sama sekali dilewati, bukan kesalahan.',
            'Tanggal ditulis hari-dulu (13-11-2026). Jam memakai 24 jam (07:52:10). PIN dan NIP berupa teks.',
            'PIN yang belum dikaitkan dengan karyawan ditahan, tidak dibuang: kaitkan di tab yang sama. Bila NIP ikut tertulis dan tepat satu karyawan memilikinya, PIN dikaitkan otomatis.',
            'Mengunggah berkas yang sama atau tumpang tindih dua kali tidak menambah apa pun.',
        ]);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * A Cabang's rekapitulasi, in the layout the branches send, and what to know on a second sheet.
     */
    public function rekap(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Contoh Rekapitulasi Absen')->setCreator(config('app.name'));

        $sheet = $book->getActiveSheet()->setTitle('REKAPITULASI ABSEN');

        $sheet->setCellValue('B2', 'REKAPITULASI ABSEN KARYAWAN');
        $sheet->mergeCells('B2:K2');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ([4 => ['Perusahaan', 'PT CONTOH SEJAHTERA'], 5 => ['NPWP / NITKU', '00.000.000.0-000.000'], 6 => ['Cabang', 'CABANG CONTOH'], 7 => ['Periode', self::REKAP_PERIOD]] as $row => [$label, $value]) {
            $sheet->setCellValue("B{$row}", $label);
            $sheet->setCellValueExplicit("D{$row}", $value, DataType::TYPE_STRING);
        }

        $sheet->setCellValue('K5', 'Tanggal');
        $sheet->setCellValueExplicit('K6', self::REKAP_DATES, DataType::TYPE_STRING);
        $sheet->getStyle('B4:B7')->getFont()->setBold(true);
        $sheet->getStyle('K5')->getFont()->setBold(true);

        $headings = ['NO', 'NAMA', 'JABATAN / BAGIAN', 'ZONA', 'ALPA', 'IZIN', 'SAKIT', 'TELAT', 'TOTAL POTONGAN', 'KETERANGAN'];
        $this->headerRow($sheet, 9, $headings, 'B', freeze: false);

        $row = 10;
        $totals = ['alpa' => 0, 'izin' => 0, 'sakit' => 0, 'telat' => 0, 'potongan' => 0];

        foreach (self::REKAP_PEOPLE as $index => [$name, $position, $zona, $alpa, $izin, $sakit, $telat]) {
            $potongan = $this->potongan->forRow($zona, $alpa, $izin, $telat, $position, self::REKAP_PERIOD);

            $sheet->setCellValue("B{$row}", $index + 1);
            $sheet->setCellValue("C{$row}", $name);
            $sheet->setCellValue("D{$row}", $position);
            $sheet->setCellValue("E{$row}", strtoupper($zona->label()));

            // An empty cell means none, as the branches write it.
            foreach (['F' => $alpa, 'G' => $izin, 'H' => $sakit, 'I' => $telat] as $column => $days) {
                if ($days > 0) {
                    $sheet->setCellValue("{$column}{$row}", $days);
                }
            }

            $sheet->setCellValue("J{$row}", $potongan);
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode(self::ACCOUNTING);

            $totals['alpa'] += $alpa;
            $totals['izin'] += $izin;
            $totals['sakit'] += $sakit;
            $totals['telat'] += $telat;
            $totals['potongan'] += $potongan;
            $row++;
        }

        // The table ends at the JUMLAH row; the reader stops there.
        $sheet->setCellValue("B{$row}", 'JUMLAH');
        $sheet->setCellValue("F{$row}", $totals['alpa']);
        $sheet->setCellValue("G{$row}", $totals['izin']);
        $sheet->setCellValue("H{$row}", $totals['sakit']);
        $sheet->setCellValue("I{$row}", $totals['telat']);
        $sheet->setCellValue("J{$row}", $totals['potongan']);
        $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode(self::ACCOUNTING);
        $sheet->getStyle("B{$row}:K{$row}")->getFont()->setBold(true);

        // Under the table the branches' sheet keeps the rate of each Zona (which its own formulas read) and a signature
        // block. They are for the people who sign it: the app reads neither, and uses its own rates.
        $legend = $row + 2;
        $sheet->setCellValue("C{$legend}", 'KETERANGAN');
        $sheet->setCellValue("D{$legend}", 'JUMLAH');
        $sheet->getStyle("C{$legend}:D{$legend}")->getFont()->setBold(true);
        $sheet->setCellValue("K{$legend}", 'Penanggung Jawab');

        foreach ([Zona::Hitam, Zona::Merah, Zona::Hijau] as $offset => $zona) {
            $line = $legend + 1 + $offset;
            $sheet->setCellValue("C{$line}", 'ZONA '.strtoupper($zona->label()));
            $sheet->setCellValue("D{$line}", $this->potongan->rateFor($zona));
            $sheet->getStyle("D{$line}")->getNumberFormat()->setFormatCode(self::ACCOUNTING);
        }

        $sheet->setCellValue('K'.($legend + 3), 'Kepala Cabang/HRD,');

        foreach (['A' => 3, 'B' => 14, 'C' => 24, 'D' => 28, 'E' => 10, 'F' => 8, 'G' => 8, 'H' => 8, 'I' => 8, 'J' => 18, 'K' => 28] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $this->notes($book, 'Petunjuk', [
            'Contoh rekapitulasi absen cabang. Semua nama di sini rekaan.',
            '',
            'Di atas tabel isi sel berlabel: Cabang, Periode, dan Tanggal dengan bentuk '.self::REKAP_DATES.' (hari/bulan/tahun). Bila rentang tanggal tidak ada, bulan pada Periode (misalnya OKTOBER 2026) dipakai.',
            'Tabel memakai judul kolom NAMA, JABATAN / BAGIAN, ZONA, ALPA, IZIN, SAKIT, TELAT dan TOTAL POTONGAN. Kolom dicari dari judulnya, jadi urutannya boleh berbeda; kolom tambahan diabaikan.',
            'ALPA, IZIN, SAKIT dan TELAT berisi jumlah hari, bilangan bulat; kosong dibaca nol. ZONA: HIJAU, MERAH atau HITAM.',
            'Akhiri tabel dengan baris JUMLAH. Baris setelahnya (tarif per Zona, tanda tangan) tidak dibaca.',
            '',
            'Potongan dihitung ulang oleh aplikasi dari ZONA dengan tarifnya sendiri, bukan tarif yang tertulis di bawah tabel: alpa dan izin sehari penuh, telat setengah, sakit tidak dipotong, dan Supervisor tidak dipotong. Selisih dengan angka di berkas ditandai merah saat memeriksa, dan angka aplikasi yang dipakai.',
            'Hanya lembar pertama yang dibaca. Setelah diunggah, berkas dibuka sebagai draft; belum ada yang dihitung sampai dikonfirmasi.',
        ]);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * The workbook as a download.
     */
    public function download(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fillDaily(Worksheet $sheet): void
    {
        $row = 2;

        foreach (self::PEOPLE as $person) {
            foreach (self::DAY_NAMES as $index => $dayName) {
                $date = CarbonImmutable::parse(self::WEEK_START)->addDays($index);
                $day = $person['days'][$index];
                $scans = is_array($day) ? $day : null;
                $onDuty = $day === self::DUTY;
                $end = $dayName === 'JUMAT' ? '17:30:00' : '17:00:00';
                $in = $scans[0] ?? null;
                $out = $scans[1] ?? null;
                $late = $in === null ? 0 : max(0, $this->minutes($in) - $this->minutes('08:00:00'));
                $early = $out === null ? 0 : max(0, $this->minutes($end) - $this->minutes($out));
                $complete = $in !== null && $out !== null;
                // As in the real report: the minutes credited against the schedule (late and early-leave taken off), not
                // the time between the scans.
                $credited = $complete ? max(0, $this->minutes($end) - $this->minutes('08:00:00') - $late - $early) : 0;

                // The three kinds of row the real report has: [Jadwal, Jam kerja, Valid, Dihitung].
                [$schedule, $workDay, $valid, $counted] = match (true) {
                    $scans !== null => ['JADWAL KERJA', $dayName, -1, $complete ? 1 : 0],
                    $onDuty => ['Izin dinas (Izin keperluan kantor)', null, -1, 1],
                    default => ['Tidak Hadir', 'Tidak Hadir', 0, 0],
                };

                $this->valueRow($sheet, $row++, [
                    $date->format('d-m-Y'),
                    $schedule,
                    $workDay,
                    $valid,
                    $person['pin'],
                    $person['nip'],
                    $person['name'],
                    $person['position'],
                    $person['department'],
                    self::OFFICE,
                    0, 0, 0,
                    $scans === null ? '00:00:00' : '08:00:00',
                    $in,
                    $in === null ? null : self::DEVICE_SN,
                    0,
                    $late,
                    null, null,
                    0, 0, 0,
                    $early,
                    $scans === null ? '00:00:00' : $end,
                    $out,
                    $out === null ? null : self::DEVICE_SN,
                    $credited,
                    $counted,
                    0,
                    $onDuty ? ' TUGAS KELUAR KOTA ' : ($scans !== null && $out === null ? 'Tidak scan pulang' : null),
                ]);
            }
        }

        $this->widths($sheet, count(self::DAILY_COLUMNS));
    }

    private function fillLog(Worksheet $sheet): void
    {
        $row = 2;

        foreach (self::PEOPLE as $person) {
            foreach ($person['days'] as $index => $day) {
                if (! is_array($day)) {
                    continue;
                }

                $date = CarbonImmutable::parse(self::WEEK_START)->addDays($index);

                foreach ($day as $time) {
                    if ($time === null) {
                        continue;
                    }

                    $this->valueRow($sheet, $row++, [
                        $date->format('d-m-Y').' '.$time,
                        $date->format('d-m-Y'),
                        $time,
                        $person['pin'],
                        $person['nip'],
                        $person['name'],
                        $person['position'],
                        $person['department'],
                        self::OFFICE,
                        1,
                        1,
                        0,
                        self::DEVICE_SN,
                        'Mesin contoh',
                    ]);
                }
            }
        }

        $this->widths($sheet, count(self::LOG_COLUMNS));
    }

    /**
     * Whole minutes since midnight of an "HH:MM:SS" time.
     */
    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * A row of cells as the scanner software writes them: a whole number stays a number, everything else is text so Excel
     * cannot turn a PIN or a date into a number, and a null is an empty cell.
     *
     * @param  list<string|int|null>  $values
     */
    private function valueRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $coordinate = $sheet->getCell([$index + 1, $row])->getCoordinate();

            is_int($value)
                ? $sheet->setCellValue($coordinate, $value)
                : $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
        }
    }

    /**
     * @param  list<string>  $headings
     */
    private function headerRow(Worksheet $sheet, int $row, array $headings, string $firstColumn = 'A', bool $freeze = true): void
    {
        $startIndex = ord($firstColumn) - ord('A') + 1;

        foreach ($headings as $offset => $heading) {
            $sheet->setCellValue([$startIndex + $offset, $row], $heading);
        }

        $first = $sheet->getCell([$startIndex, $row])->getCoordinate();
        $last = $sheet->getCell([$startIndex + count($headings) - 1, $row])->getCoordinate();

        $sheet->getStyle("{$first}:{$last}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
        ]);

        if ($freeze) {
            $sheet->freezePane($sheet->getCell([1, $row + 1])->getCoordinate());
        }
    }

    private function widths(Worksheet $sheet, int $columns): void
    {
        for ($index = 1; $index <= $columns; $index++) {
            $sheet->getColumnDimensionByColumn($index)->setWidth(16);
        }
    }

    /**
     * A sheet of plain explanatory lines.
     *
     * @param  list<string>  $lines
     */
    private function notes(Spreadsheet $book, string $title, array $lines): void
    {
        $sheet = $book->createSheet()->setTitle($title);
        $sheet->getColumnDimension('A')->setWidth(110);

        foreach ($lines as $index => $line) {
            $sheet->setCellValue('A'.($index + 1), $line);
            $sheet->getStyle('A'.($index + 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true);
    }
}
