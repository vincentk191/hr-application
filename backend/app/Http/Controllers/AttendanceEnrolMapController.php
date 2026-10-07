<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\MapEnrolId;
use App\Http\Requests\StoreAttendanceEnrolMapRequest;
use App\Models\AttendanceEnrolMap;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AttendanceEnrolMapController extends Controller
{
    public function store(StoreAttendanceEnrolMapRequest $request, MapEnrolId $map): RedirectResponse
    {
        $validated = $request->validated();

        $result = $map->handle(
            trim($validated['device_sn']),
            trim($validated['pin']),
            Employee::acrossCompanies()->whereKey($validated['employee_id'])->firstOrFail(),
            isset($validated['valid_from']) ? Carbon::parse($validated['valid_from']) : null,
            isset($validated['valid_to']) ? Carbon::parse($validated['valid_to']) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['released'] > 0
                ? "PIN dipetakan. {$result['released']} scan yang menunggu kini tercatat sebagai absensi."
                : 'PIN dipetakan.',
        ]);

        return back();
    }

    /**
     * Remove a mapping. Punches already filed under it stay where they are: they were made by
     * that person, and deleting the mapping later does not change who that was.
     */
    public function destroy(int $enrolMap): RedirectResponse
    {
        $this->authorize('manage-attendance-device');

        AttendanceEnrolMap::acrossCompanies()->findOrFail($enrolMap)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pemetaan dihapus.']);

        return back();
    }
}
