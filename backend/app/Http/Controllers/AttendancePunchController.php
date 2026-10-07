<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\CorrectPunches;
use App\Http\Requests\StoreManualPunchRequest;
use App\Http\Requests\VoidPunchRequest;
use App\Models\AttendancePunch;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Manual corrections to a person's punches, from their profile's attendance tab (EMP-ATT-001).
 * Every action needs a reason and is audited; see CorrectPunches for how the append-only log is
 * kept intact.
 */
class AttendancePunchController extends Controller
{
    public function store(StoreManualPunchRequest $request, Employee $employee, CorrectPunches $correct): RedirectResponse
    {
        $correct->add($employee, $request->validated('date'), $request->validated('time'), $request->validated('reason'), $request->user());

        $this->done('Scan ditambahkan.');

        return back();
    }

    public function update(StoreManualPunchRequest $request, AttendancePunch $punch, CorrectPunches $correct): RedirectResponse
    {
        $correct->change($punch, $request->validated('date'), $request->validated('time'), $request->validated('reason'), $request->user());

        $this->done('Waktu scan diubah.');

        return back();
    }

    public function destroy(VoidPunchRequest $request, AttendancePunch $punch, CorrectPunches $correct): RedirectResponse
    {
        $correct->void($punch, $request->validated('reason'), $request->user());

        $this->done('Scan dibatalkan.');

        return back();
    }

    private function done(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
