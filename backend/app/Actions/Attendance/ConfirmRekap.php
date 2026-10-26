<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Support\Attendance\InvalidRekap;
use Illuminate\Support\Facades\DB;

/**
 * Makes a checked draft the Cabang's rekapitulasi for its dates. A confirmed file for the same Cabang and
 * the same dates is replaced, so sending a corrected file does not count the period twice.
 */
class ConfirmRekap
{
    /**
     * @throws InvalidRekap when the draft still has lines that need HR, or counts someone twice
     */
    public function handle(AttendanceRekap $rekap): AttendanceRekap
    {
        if ($rekap->status !== AttendanceRekap::STATUS_DRAFT) {
            throw new InvalidRekap('Rekapitulasi ini sudah dikonfirmasi atau digantikan.');
        }

        $rows = $rekap->rows()->get();
        $open = $rows->filter(fn (AttendanceRekapRow $row): bool => $row->match_status->needsAttention())->count();

        if ($open > 0) {
            throw new InvalidRekap("Masih ada {$open} baris yang belum dicocokkan. Pilih karyawannya atau lewati barisnya.");
        }

        $twice = $rows->whereNotNull('employee_id')->countBy('employee_id')->filter(fn (int $count): bool => $count > 1);

        if ($twice->isNotEmpty()) {
            throw new InvalidRekap('Seorang karyawan terhubung ke lebih dari satu baris. Lewati salah satunya.');
        }

        return DB::transaction(function () use ($rekap): AttendanceRekap {
            AttendanceRekap::acrossCompanies()
                ->where('branch_id', $rekap->branch_id)
                ->where('status', AttendanceRekap::STATUS_CONFIRMED)
                ->whereDate('period_start', $rekap->period_start->toDateString())
                ->whereDate('period_end', $rekap->period_end->toDateString())
                ->update(['status' => AttendanceRekap::STATUS_SUPERSEDED]);

            $rekap->update(['status' => AttendanceRekap::STATUS_CONFIRMED, 'confirmed_at' => now()]);

            return $rekap;
        });
    }
}
