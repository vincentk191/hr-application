<?php

namespace App\Http\Controllers;

use App\Actions\Recruitment\DecideOffer;
use App\Actions\Recruitment\ProposeOffers;
use App\Actions\Recruitment\WithdrawOffer;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Offers to the candidates the direct superior chose (REC-OFS): HR puts them to Management, Management approves each with
 * a starting salary or rejects it with a reason, and HR can pull one back until it is sent. The offer's own page is the
 * Penawaran tab of its requisition, which is why every route here is nested under it.
 */
class RequisitionOfferController extends Controller
{
    /**
     * "Ajukan ke Manajemen": the ticked candidates become offers waiting on Management (REC-OFS-001/002).
     */
    public function store(Request $request, Requisition $requisition, ProposeOffers $propose): RedirectResponse
    {
        $this->authorize('proposeOffers', $requisition);

        $validated = $request->validate([
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer'],
        ], ['candidate_ids.required' => 'Pilih setidaknya satu kandidat.', 'candidate_ids.min' => 'Pilih setidaknya satu kandidat.']);

        $offers = $propose->handle($requisition, array_values(array_map('intval', $validated['candidate_ids'])), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => count($offers).' penawaran diajukan ke Manajemen.']);

        return back();
    }

    /**
     * Management decides one offer (REC-OFS-006). Approving needs the starting salary; rejecting needs a reason.
     */
    public function decide(Request $request, Requisition $requisition, CandidateOffer $offer, DecideOffer $decide): RedirectResponse
    {
        $this->authorize('decideOffer', [$requisition, $offer]);

        $approving = $request->input('decision') === 'approve';

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'salary' => [$approving ? 'required' : 'nullable', 'integer', 'min:1', 'max:9999999999'],
            'note' => [$approving ? 'nullable' : 'required', 'string', 'max:1000'],
        ], [
            'salary.required' => 'Isi gaji awal sebelum menyetujui.',
            'salary.integer' => 'Gaji awal harus berupa angka.',
            'salary.min' => 'Gaji awal harus lebih dari nol.',
            'note.required' => 'Tulis alasan penolakan.',
        ]);

        $name = $offer->candidate?->name;

        if ($approving) {
            $decide->approve($requisition, $offer, $request->user(), (int) $validated['salary'], $validated['note'] ?? null);

            Inertia::flash('toast', ['type' => 'success', 'message' => "Penawaran {$name} disetujui."]);
        } else {
            $decide->reject($requisition, $offer, $request->user(), $validated['note']);

            Inertia::flash('toast', ['type' => 'success', 'message' => "Penawaran {$name} ditolak; kandidat kembali ke daftar interview."]);
        }

        return back();
    }

    /**
     * "Tarik": HR pulls an offer back before it is sent.
     */
    public function withdraw(Request $request, Requisition $requisition, CandidateOffer $offer, WithdrawOffer $withdraw): RedirectResponse
    {
        $this->authorize('withdrawOffer', [$requisition, $offer]);

        $withdraw->handle($offer, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Penawaran {$offer->candidate?->name} ditarik."]);

        return back();
    }
}
