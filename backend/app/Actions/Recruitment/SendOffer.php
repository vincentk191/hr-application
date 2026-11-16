<?php

namespace App\Actions\Recruitment;

use App\Enums\EmailKind;
use App\Enums\OfferStage;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Email\CompanyMailer;
use App\Support\Recruitment\OfferLetterComposer;
use App\Support\Recruitment\OfferLetterPdf;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * HR sends an approved offer to the candidate (REC-OFS-003, email automation): the offer becomes "Penawaran terkirim", with who and
 * when, and its terms are locked.
 *
 * The letter is written once, here, and kept: the saved letter is what the candidate holds, and a **PDF** is made from it and
 * kept too, so a later change to the template or the company's logo never rewrites it. When HR sent the email as well (they
 * have reviewed the draft and may have edited it), it goes from the company's own address with the PDF attached, through
 * the outbox; a failure there is shown on the offer and can be tried again, and never undoes the sending. If the PDF cannot be
 * made nothing changes, so HR can try again.
 */
class SendOffer
{
    public function __construct(
        private WorkflowEngine $engine,
        private OfferLetterComposer $letters,
        private OfferLetterPdf $pdfs,
        private CompanyMailer $mailer,
    ) {}

    /**
     * @param  array{subject: string, body: string}|null  $email  the email to send with the letter, as HR reviewed it; null sends none
     * @return bool whether an email was queued for the candidate
     *
     * @throws Throwable the PDF could not be made
     */
    public function handle(Requisition $requisition, CandidateOffer $offer, User $by, ?array $email = null): bool
    {
        $candidate = $offer->candidate;
        $emailed = $email !== null && filled($candidate->email);

        $snapshot = $this->letters->snapshot($requisition, $offer);
        $path = CandidateOffer::LETTER_DIRECTORY.'/'.$offer->id.'-'.Str::random(10).'.pdf';

        Storage::disk(CandidateOffer::DISK)->put($path, $this->pdfs->fromLetter($snapshot, $requisition));

        try {
            DB::transaction(function () use ($requisition, $offer, $by, $snapshot, $path, $email): void {
                $offer->forceFill([
                    'sent_at' => now(),
                    'sent_by_user_id' => $by->id,
                    'letter_snapshot' => $snapshot,
                    'letter_pdf_path' => $path,
                ])->save();

                $this->engine->transitionTo($offer->workflow, OfferStage::Sent, $by);

                activity('recruitment')
                    ->performedOn($requisition)
                    ->causedBy($by)
                    ->withProperties(['candidate' => $offer->candidate->name, 'emailed' => $email !== null])
                    ->log('Penawaran dikirim ke kandidat');
            });
        } catch (Throwable $exception) {
            Storage::disk(CandidateOffer::DISK)->delete($path);

            throw $exception;
        }

        if ($emailed) {
            $message = $this->mailer->queue(
                $requisition->company_id,
                EmailKind::OfferSent,
                (string) $candidate->email,
                (string) $email['subject'],
                (string) $email['body'],
                [['name' => 'Surat penawaran '.$candidate->name.'.pdf', 'mime' => 'application/pdf', 'path' => $path]],
                related: $offer,
                by: $by,
                toName: $candidate->name,
            );

            $offer->forceFill(['emailed_at' => now(), 'email_message_id' => $message->id])->save();
        }

        return $emailed;
    }
}
