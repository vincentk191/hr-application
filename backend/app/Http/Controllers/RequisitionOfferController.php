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
use App\Support\Email\CompanyMailer;
use App\Support\Employees\EmployeeFormOptions;
use App\Support\Files\InlineImage;
use App\Support\Files\UploadFiler;
use App\Support\Recruitment\HireProfile;
use App\Support\Recruitment\OfferLetterComposer;
use App\Support\Recruitment\OfferLetterPdf;
use App\Support\Recruitment\OnboardingEmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $email = null;

        // HR reviewed the draft of the email and chose to send it with the letter: its subject and text are what they left in it.
        if ($request->boolean('notify_candidate') && filled($offer->candidate?->email)) {
            $validated = $request->validate([
                'email_subject' => ['required', 'string', 'max:300'],
                'email_body' => ['required', 'string', 'max:10000'],
            ], ['email_subject.required' => 'Isi subjek email.', 'email_body.required' => 'Isi teks email.']);

            // Said now, not found out later in the log: the company's mailbox has to be set up and on.
            if (! app(CompanyMailer::class)->isReady($requisition->company_id)) {
                throw ValidationException::withMessages(['notify_candidate' => 'Email pengirim perusahaan belum diaktifkan oleh admin, jadi emailnya belum bisa dikirim. Tandai terkirim tanpa email, lalu kirim suratnya sendiri.']);
            }

            $email = ['subject' => (string) $validated['email_subject'], 'body' => (string) $validated['email_body']];
        }

        $emailed = $sendOffer->handle($requisition, $offer, $request->user(), $email);

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
     * The offer letter as a PDF, to look at before it is sent and to open again after: the letter as it was sent once it is,
     * as it would read now before.
     */
    public function letterPdf(Request $request, Requisition $requisition, CandidateOffer $offer, OfferLetterPdf $pdfs): HttpResponse
    {
        $this->authorize('viewOfferLetter', [$requisition, $offer]);

        $kept = $offer->letter_pdf_path !== null && Storage::disk(CandidateOffer::DISK)->exists($offer->letter_pdf_path);
        $pdf = $kept ? (string) Storage::disk(CandidateOffer::DISK)->get((string) $offer->letter_pdf_path) : $pdfs->render($requisition, $offer);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Surat penawaran '.str_replace(['"', '/', '\\'], '', (string) $offer->candidate?->name).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * HR files the letter the candidate signed and sent back (a scan or a photo of it). A second one replaces the first.
     */
    public function fileSignedLetter(Request $request, Requisition $requisition, CandidateOffer $offer, UploadFiler $filer): RedirectResponse
    {
        $this->authorize('fileSignedLetter', [$requisition, $offer]);

        $request->validate(
            ['signed_letter' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
            [
                'signed_letter.required' => 'Pilih berkas surat yang sudah ditandatangani.',
                'signed_letter.mimes' => 'Surat harus berupa PDF atau gambar (JPG, PNG).',
                'signed_letter.max' => 'Berkas terlalu besar (maksimal 5 MB).',
            ],
        );

        $file = $request->file('signed_letter');
        $stored = $filer->store($file, CandidateOffer::SIGNED_DIRECTORY.'/'.$requisition->id, CandidateOffer::DISK);
        $before = $offer->signed_letter_path;

        $offer->forceFill([
            'signed_letter_path' => $stored['path'],
            'signed_letter_name' => $file->getClientOriginalName(),
            'signed_letter_size' => $stored['size'],
            'signed_letter_at' => now(),
            'signed_letter_by_user_id' => $request->user()->id,
        ])->save();

        if ($before !== null) {
            Storage::disk(CandidateOffer::DISK)->delete($before);
        }

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($request->user())
            ->withProperties(['candidate' => $offer->candidate?->name, 'file' => $file->getClientOriginalName()])
            ->log('Surat penawaran bertanda tangan diarsipkan');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Surat bertanda tangan {$offer->candidate?->name} disimpan."]);

        return back();
    }

    /**
     * The signed letter, for HR.
     */
    public function signedLetter(Request $request, Requisition $requisition, CandidateOffer $offer): StreamedResponse
    {
        $this->authorize('viewSignedLetter', [$requisition, $offer]);

        abort_unless(Storage::disk(CandidateOffer::DISK)->exists((string) $offer->signed_letter_path), 404);

        return InlineImage::serve($request, CandidateOffer::DISK, (string) $offer->signed_letter_path, (string) $offer->signed_letter_name);
    }

    /**
     * HR takes the signed letter away (a wrong file).
     */
    public function removeSignedLetter(Request $request, Requisition $requisition, CandidateOffer $offer): RedirectResponse
    {
        $this->authorize('fileSignedLetter', [$requisition, $offer]);

        if ($offer->signed_letter_path !== null) {
            Storage::disk(CandidateOffer::DISK)->delete($offer->signed_letter_path);
        }

        $offer->forceFill([
            'signed_letter_path' => null,
            'signed_letter_name' => null,
            'signed_letter_size' => null,
            'signed_letter_at' => null,
            'signed_letter_by_user_id' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Surat bertanda tangan dihapus.']);

        return back();
    }

    /**
     * "Kirim ulang email": the offer's email failed, so it is tried again from the start. The offer itself is untouched.
     */
    public function resendEmail(Request $request, Requisition $requisition, CandidateOffer $offer, CompanyMailer $mailer): RedirectResponse
    {
        $this->authorize('resendOfferEmail', [$requisition, $offer]);

        $mailer->resend($offer->emailMessage);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email dikirim ulang.']);

        return back();
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

        if ($request->boolean('notify_candidate') && filled($offer->candidate?->email) && ! app(CompanyMailer::class)->isReady($requisition->company_id)) {
            throw ValidationException::withMessages(['notify_candidate' => 'Email pengirim perusahaan belum diaktifkan oleh admin, jadi kandidat belum bisa dikabari lewat email.']);
        }

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

        $email = null;

        // HR reviewed the welcome email and left it ticked: what they left in it is what goes, once the time and place are filled in.
        if ($request->boolean('notify_candidate') && filled($offer->candidate?->email)) {
            $draft = $request->validate([
                'email_subject' => ['required', 'string', 'max:300'],
                'email_body' => ['required', 'string', 'max:10000'],
            ], ['email_subject.required' => 'Isi subjek email.', 'email_body.required' => 'Isi teks email.']);

            if (OnboardingEmailTemplate::hasBlank((string) $draft['email_body']) || OnboardingEmailTemplate::hasBlank((string) $draft['email_subject'])) {
                throw ValidationException::withMessages(['email_body' => 'Isi waktu dan tempat hari pertama di teks email.']);
            }

            if (! app(CompanyMailer::class)->isReady($requisition->company_id)) {
                throw ValidationException::withMessages(['notify_candidate' => 'Email pengirim perusahaan belum diaktifkan oleh admin, jadi emailnya belum bisa dikirim. Hapus centangnya dan kabari kandidat sendiri.']);
            }

            $email = ['subject' => (string) $draft['email_subject'], 'body' => (string) $draft['email_body']];
        }

        $emailed = $respond->accept($requisition, $offer, $request->user(), $email);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offer->candidate?->name} diterima ({$requisition->filledCount()} dari {$requisition->quota}).".($emailed ? ' Email sambutan dikirim.' : '')]);

        return back();
    }

    /**
     * "Kirim ulang": the welcome email failed, so it is tried again from the start. The acceptance is untouched.
     */
    public function resendWelcomeEmail(Request $request, Requisition $requisition, CandidateOffer $offer, CompanyMailer $mailer): RedirectResponse
    {
        $this->authorize('resendWelcomeEmail', [$requisition, $offer]);

        $mailer->resend($offer->welcomeEmail());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email dikirim ulang.']);

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
