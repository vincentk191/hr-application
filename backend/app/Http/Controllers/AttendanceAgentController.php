<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAgent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Ask the sync agents to sync now (ATT-DEV-003). The dashboard cannot reach into the site, so this
 * only leaves a note: the agent asks for instructions each time it polls, sees the note, syncs, and
 * the note is cleared by its next successful report.
 */
class AttendanceAgentController extends Controller
{
    public function syncNow(): RedirectResponse
    {
        $this->authorize('manage-attendance-device');

        $asked = AttendanceAgent::query()->update(['sync_requested_at' => now()]);

        Inertia::flash('toast', [
            'type' => $asked > 0 ? 'success' : 'error',
            'message' => $asked > 0
                ? 'Permintaan sinkronisasi dikirim. Agen akan menjalankannya pada pengecekan berikutnya.'
                : 'Belum ada agen sinkronisasi yang terdaftar.',
        ]);

        return back();
    }
}
