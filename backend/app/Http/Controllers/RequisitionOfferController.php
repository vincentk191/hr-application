<?php

namespace App\Http\Controllers;

use App\Actions\Recruitment\ConvertHireToEmployee;
use App\Actions\Recruitment\DecideOffer;
use App\Actions\Recruitment\ProposeOffers;
use App\Actions\Recruitment\RespondToOffer;
use App\Actions\Recruitment\SendOffer;
use App\Actions\Recruitment\SetOfferTerms;
use App\Actions\Recruitment\WithdrawOffer;
use App\Enums\OfferStage;
use App\Enums\Permission;
use App\Http\Requests\ConvertHireRequest;
use App\Models\CandidateOffer;
use App\Models\Employee;
use App\Models\Requisition;
use App\Support\Employees\EmployeeFormOptions;
use App\Support\Recruitment\HireProfile;
use App\Support\Recruitment\OfferLetterComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
     * HR fills in the start date and the probation of an approved offer (REC-OFR). The start date must be after today and
     * the probation at most three months, 0 meaning none.
     */
    public function terms(Request $request, Requisition $requisition, CandidateOffer $offer, SetOfferTerms $setTerms): RedirectResponse
    {
        $this->authorize('setOfferTerms', [$requisition, $offer]);

        $today = now()->setTimezone((string) config('attendance.device_timezone'))->toDateString();

        $validated = $request->validate([
            'start_on' => ['required', 'date_format:Y-m-d', 'after:'.$today],
            'probation_months' => ['required', 'integer', 'min:0', 'max:3'],
            'letter_number' => ['nullable', 'string', 'max:60'],
            'letter_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'start_on.required' => 'Isi tanggal mulai kerja.',
            'start_on.date_format' => 'Tanggal mulai kerja harus berupa tanggal yang sah.',
            'start_on.after' => 'Tanggal mulai kerja harus setelah hari ini.',
            'probation_months.required' => 'Pilih masa percobaan.',
            'probation_months.max' => 'Masa percobaan maksimal 3 bulan.',
            'letter_number.max' => 'Nomor surat maksimal 60 karakter.',
            'letter_note.max' => 'Catatan surat maksimal 1000 karakter.',
        ]);

        $setTerms->handle(
            $requisition,
            $offer,
            $request->user(),
            $validated['start_on'],
            (int) $validated['probation_months'],
            $validated['letter_number'] ?? null,
            $validated['letter_note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Ketentuan penawaran {$offer->candidate?->name} disimpan."]);

        return back();
    }

    /**
     * "Tandai terkirim": the letter has gone to the candidate. The candidate is emailed the terms only if HR ticked it.
     */
    public function send(Request $request, Requisition $requisition, CandidateOffer $offer, SendOffer $sendOffer): RedirectResponse
    {
        $this->authorize('sendOffer', [$requisition, $offer]);

        $emailed = $sendOffer->handle($requisition, $offer, $request->user(), $request->boolean('notify_candidate'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Penawaran {$offer->candidate?->name} ditandai terkirim".($emailed ? ' dan dikabarkan lewat email.' : '.')]);

        return back();
    }

    /**
     * The offer letter as a print page (REC-OFR), built from the offer as it is now so it can be printed again.
     */
    public function letter(Request $request, Requisition $requisition, CandidateOffer $offer, OfferLetterComposer $composer): Response
    {
        $this->authorize('viewOfferLetter', [$requisition, $offer]);

        return Inertia::render('recruitment/offer-letter', [
            'requisition' => ['id' => $requisition->id, 'reference' => $requisition->reference(), 'positionName' => $requisition->position_name],
            'letter' => $composer->letter($requisition, $offer),
        ]);
    }

    /**
     * "Jadikan karyawan": the employee form, filled in from the hire (REC-FIL-006), asking HR only for what is not known.
     */
    public function employee(Request $request, Requisition $requisition, CandidateOffer $offer, HireProfile $profile): Response
    {
        $this->authorize('convertHire', [$requisition, $offer]);

        $offer->loadMissing('candidate');

        return Inertia::render('recruitment/hire-employee', [
            ...EmployeeFormOptions::make(),
            // The profile goes to the requisition's company, so no company is asked for.
            'companies' => null,
            'canEditSensitive' => $request->user()->can(Permission::ViewSensitiveData->value),
            'requisition' => ['id' => $requisition->id, 'reference' => $requisition->reference(), 'positionName' => $requisition->position_name],
            'offerId' => $offer->id,
            'hire' => $profile->defaults($requisition, $offer),
        ]);
    }

    /**
     * Saves the profile of the hire (REC-FIL-007): checked like any new employee, filed under the requisition's company and
     * linked to the offer, once.
     */
    public function convert(ConvertHireRequest $request, Requisition $requisition, CandidateOffer $offer, ConvertHireToEmployee $convert): RedirectResponse
    {
        $fields = $request->safe()->except(['photo', 'remove_photo', 'company_id']);

        if (! $request->user()->can(Permission::ViewSensitiveData->value)) {
            $fields = array_diff_key($fields, array_flip(Employee::SENSITIVE_FIELDS));
        }

        $employee = $convert->handle($requisition, $offer, $fields, $request->file('photo'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$employee->full_name} dijadikan karyawan."]);

        return to_route('employees.show', $employee);
    }

    /**
     * "Tarik": HR pulls an offer back before it is sent.
     */
    public function withdraw(Request $request, Requisition $requisition, CandidateOffer $offer, WithdrawOffer $withdraw): RedirectResponse
    {
        $this->authorize('withdrawOffer', [$requisition, $offer]);

        if ($offer->stage() !== OfferStage::Sent) {
            $withdraw->handle($offer, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => "Penawaran {$offer->candidate?->name} ditarik."]);

            return back();
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'notify_candidate' => ['nullable', 'boolean'],
        ], ['note.required' => 'Tulis alasan penarikan.']);

        $emailed = $withdraw->handleSent($requisition, $offer, $request->user(), $validated['note'], $request->boolean('notify_candidate'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Penawaran {$offer->candidate?->name} ditarik".($emailed ? ' dan kandidat dikabari lewat email.' : '.')]);

        return back();
    }

    /**
     * "Tandai Diterima": the candidate said yes (REC-OFS-004). Refused once the quota is full.
     */
    public function accept(Request $request, Requisition $requisition, CandidateOffer $offer, RespondToOffer $respond): RedirectResponse
    {
        $this->authorize('respondToOffer', [$requisition, $offer]);

        $respond->accept($requisition, $offer, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offer->candidate?->name} diterima ({$requisition->filledCount()} dari {$requisition->quota})."]);

        return back();
    }

    /**
     * "Menolak": the candidate said no (REC-OFS-008). The reason is required and internal; the place stays open.
     */
    public function decline(Request $request, Requisition $requisition, CandidateOffer $offer, RespondToOffer $respond): RedirectResponse
    {
        $this->authorize('respondToOffer', [$requisition, $offer]);

        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']], ['note.required' => 'Tulis alasan kandidat menolak.']);

        $respond->decline($requisition, $offer, $request->user(), $validated['note']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offer->candidate?->name} menolak penawaran; tempat masih terbuka."]);

        return back();
    }
}
