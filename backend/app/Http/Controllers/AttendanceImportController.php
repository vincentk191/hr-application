<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\ImportScanLog;
use App\Http\Requests\StoreAttendanceImportRequest;
use App\Support\Attendance\InvalidScanLog;
use App\Support\Attendance\SampleWorkbooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceImportController extends Controller
{
    /**
     * An example Laporan Absensi, for whoever uploads one to see the layout the import reads.
     */
    public function sample(SampleWorkbooks $samples): StreamedResponse
    {
        $this->authorize('manage-attendance-device');

        return $samples->download($samples->scanLog(), 'contoh-laporan-absensi.xlsx');
    }

    public function store(StoreAttendanceImportRequest $request, ImportScanLog $import): RedirectResponse
    {
        try {
            $result = $import->handle(
                $request->file('file'),
                trim((string) $request->validated('device_sn')) ?: null,
                $request->user(),
            );
        } catch (InvalidScanLog $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $autoMapped = $result->auto_mapped > 0 ? " {$result->auto_mapped} PIN dipetakan otomatis lewat NIP." : '';

        Inertia::flash('toast', [
            'type' => $result->accepted + $result->unmapped > 0 || $result->duplicates > 0 ? 'success' : 'error',
            'message' => "Impor selesai: {$result->accepted} baru, {$result->duplicates} duplikat, "
                ."{$result->unmapped} menunggu pemetaan, {$result->rejected} ditolak.".$autoMapped,
        ]);

        return back();
    }
}
