<?php

namespace App\Http\Controllers;

use App\Actions\Offboarding\CancelOffboardingCase;
use App\Actions\Offboarding\ChangeLastWorkingDay;
use App\Actions\Offboarding\CloseOffboardingCase;
use App\Actions\Offboarding\ReassignReports;
use App\Actions\Offboarding\RecordExitInterview;
use App\Actions\Offboarding\SendBackToChecklist;
use App\Actions\Offboarding\SignOffCase;
use App\Http\Requests\RecordExitInterviewRequest;
use App\Models\OffboardingCase;
use App\Models\OffboardingSignoff;
use App\Support\Offboarding\OffboardingPresenter;
use App\Workflows\Exceptions\TransitionBlocked;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The last stage of an offboarding case and what can end it (OFF-SIG): the sequence of signatures, the exit
 * interview, sending a case back to the checklist, closing it, cancelling it, and the two things HR settles
 * along the way, the leaver's reports and the last working day.
 */
class OffboardingSignOffController extends Controller
{
    public function sign(Request $request, OffboardingCase $offboardingCase, OffboardingSignoff $signoff, SignOffCase $sign): RedirectResponse
    {
        $this->authorize('signOff', $signoff);
        abort_unless($signoff->offboarding_case_id === $offboardingCase->id, 404);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $sign->handle($signoff, $request->user(), $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanda tangan tercatat.']);

        return back();
    }

    public function sendBack(Request $request, OffboardingCase $offboardingCase, SendBackToChecklist $sendBack): RedirectResponse
    {
        $this->authorize('sendBack', $offboardingCase);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Tulis apa yang perlu diperbaiki.']);

        $sendBack->handle($offboardingCase, $request->user(), $validated['note']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kasus dikembalikan ke checklist.']);

        return back();
    }

    public function interview(RecordExitInterviewRequest $request, OffboardingCase $offboardingCase, RecordExitInterview $record): RedirectResponse
    {
        $record->handle($offboardingCase, [
            'skipped_reason' => $request->boolean('skip') ? $request->input('skipped_reason') : null,
            'reason' => $request->input('reason'),
            'rehire' => $request->has('rehire') ? $request->boolean('rehire') : null,
            'notes' => $request->input('notes'),
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wawancara keluar tersimpan.']);

        return back();
    }

    public function lastDay(Request $request, OffboardingCase $offboardingCase, ChangeLastWorkingDay $change): RedirectResponse
    {
        $this->authorize('changeLastDay', $offboardingCase);

        $validated = $request->validate([
            'last_working_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['last_working_on.required' => 'Isi hari kerja terakhir yang baru.']);

        $change->handle($offboardingCase, $validated['last_working_on'], $validated['note'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hari terakhir diperbarui; Manajemen diberi tahu.']);

        return back();
    }

    public function cancel(Request $request, OffboardingCase $offboardingCase, CancelOffboardingCase $cancel): RedirectResponse
    {
        $this->authorize('cancel', $offboardingCase);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Tulis alasan pembatalan.']);

        $cancel->handle($offboardingCase, $validated['note'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offboardingCase->reference()} dibatalkan."]);

        return back();
    }

    /**
     * "Tutup kasus": HR closes it once the guards allow (OFF-SIG-008).
     */
    public function close(Request $request, OffboardingCase $offboardingCase, CloseOffboardingCase $close): RedirectResponse
    {
        $this->authorize('close', $offboardingCase);

        try {
            $close->handle($offboardingCase, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['close' => implode(' ', $blocked->reasons)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offboardingCase->reference()} ditutup."]);

        return back();
    }

    /**
     * Give the leaver's direct reports a new Atasan, so the case can close.
     */
    public function reassign(Request $request, OffboardingCase $offboardingCase, ReassignReports $reassign): RedirectResponse
    {
        $this->authorize('reassign', $offboardingCase);

        $validated = $request->validate(['moves' => ['required', 'array', 'min:1'], 'moves.*' => ['required', 'integer']], ['moves.required' => 'Pilih atasan baru.']);

        $count = $reassign->handle($offboardingCase, $validated['moves'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} bawahan dipindahkan."]);

        return back();
    }

    /**
     * The handover report (OFF-SIG-007): a page set up for printing, always built from the case as it is now.
     */
    public function beritaAcara(Request $request, OffboardingCase $offboardingCase, OffboardingPresenter $presenter): Response
    {
        $this->authorize('beritaAcara', $offboardingCase);

        return Inertia::render('offboarding/berita-acara', [
            'case' => $presenter->detail($offboardingCase, $request->user()),
        ]);
    }
}
