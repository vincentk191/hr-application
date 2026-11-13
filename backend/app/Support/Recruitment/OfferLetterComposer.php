<?php

namespace App\Support\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Requisition;

/**
 * Builds the offer letter and the email to the candidate from HR's wording ({@see OfferLetterTemplate}) and the offer
 * (REC-OFR). Until the offer is sent the letter follows the offer and the template as they are now; "Tandai terkirim"
 * saves it, and from then on it is that saved letter, so changing the template never rewrites what the candidate holds.
 */
class OfferLetterComposer
{
    public function __construct(private OfferLetterTemplate $template) {}

    /**
     * What the print page shows: the saved letter once the offer is sent, the letter as it would read now before.
     *
     * @return array<string, mixed>
     */
    public function letter(Requisition $requisition, CandidateOffer $offer): array
    {
        $requisition->loadMissing('company');

        $saved = $offer->letter_snapshot;

        if (is_array($saved) && $offer->sent_at !== null) {
            return [...$saved, 'logoUrl' => $requisition->company->logoUrl(), 'sent' => true];
        }

        return [...$this->write($requisition, $offer), 'logoUrl' => $requisition->company->logoUrl(), 'sent' => $offer->sent_at !== null];
    }

    /**
     * The letter as written now, to keep with the offer when it is sent. The logo is left out: it is the company's, and
     * the file it points to may change.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Requisition $requisition, CandidateOffer $offer): array
    {
        return $this->write($requisition, $offer);
    }

    public function emailSubject(Requisition $requisition, CandidateOffer $offer): string
    {
        return PlaceholderText::fill($this->template->current()['email_subject'], $this->values($requisition, $offer));
    }

    public function emailBody(Requisition $requisition, CandidateOffer $offer): string
    {
        return PlaceholderText::fill($this->template->current()['email_body'], [
            ...$this->values($requisition, $offer),
            'ketentuan' => $this->termsList($requisition, $offer),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function write(Requisition $requisition, CandidateOffer $offer): array
    {
        $requisition->loadMissing('company', 'department', 'branch', 'manager');
        $offer->loadMissing('candidate');

        $wording = $this->template->current();
        $values = $this->values($requisition, $offer);

        return [
            'company' => $requisition->company->name,
            'title' => PlaceholderText::fill($wording['title'], $values),
            'number' => filled($offer->letter_number) ? $offer->letter_number : null,
            'date' => $values['tanggal_surat'],
            'subject' => PlaceholderText::fill($wording['subject'], $values),
            'candidate' => $offer->candidate->name,
            'position' => $requisition->position_name,
            'department' => $requisition->department?->name,
            'branch' => $requisition->branch?->name,
            'manager' => $requisition->manager?->full_name,
            'contract' => $requisition->contractLabel(),
            'startOn' => $offer->start_on === null ? null : $values['tanggal_mulai'],
            'probation' => $offer->probation_months === null ? null : $values['masa_percobaan'],
            'salary' => $offer->salary === null ? null : $values['gaji'],
            'opening' => $this->paragraphs(PlaceholderText::fill($wording['opening'], $values)),
            'note' => filled($offer->letter_note) ? $offer->letter_note : null,
            'closing' => $this->paragraphs(PlaceholderText::fill($wording['closing'], $values)),
            'signer' => $this->template->signerFor($requisition->company_id),
        ];
    }

    /**
     * What each placeholder stands for in this offer; one that has no value here is empty, so its line collapses.
     *
     * @return array<string, string>
     */
    private function values(Requisition $requisition, CandidateOffer $offer): array
    {
        $requisition->loadMissing('company', 'department', 'branch', 'manager');
        $offer->loadMissing('candidate');

        return [
            'nama' => $offer->candidate->name,
            'posisi' => $requisition->position_name,
            'perusahaan' => $requisition->company->name,
            'departemen' => $requisition->department->name ?? '',
            'cabang' => $requisition->branch->name ?? '',
            'atasan' => $requisition->manager->full_name ?? '',
            'status_kerja' => $requisition->contractLabel() ?? '',
            'tanggal_mulai' => $offer->start_on === null ? '-' : InvitationMessage::dayLabel($offer->start_on, false),
            'masa_percobaan' => $offer->probation_months === null ? '-' : InvitationMessage::probationLabel($offer->probation_months),
            'gaji' => $offer->salary === null ? '-' : InvitationMessage::rupiah($offer->salary),
            'tanggal_surat' => InvitationMessage::dayLabel($offer->sent_at ?? now(), false),
        ];
    }

    /**
     * The terms as the email lists them, one per line.
     */
    private function termsList(Requisition $requisition, CandidateOffer $offer): string
    {
        $values = $this->values($requisition, $offer);

        return implode("\n", array_filter([
            'Posisi            : '.$values['posisi'],
            $values['status_kerja'] === '' ? null : 'Status kerja      : '.$values['status_kerja'],
            'Tanggal mulai     : '.$values['tanggal_mulai'],
            'Masa percobaan    : '.$values['masa_percobaan'],
            'Gaji pokok/bulan  : '.$values['gaji'],
        ], fn (?string $line): bool => $line !== null));
    }

    /**
     * A text cut into paragraphs at its blank lines.
     *
     * @return list<string>
     */
    private function paragraphs(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split("/\n{2,}/", $text) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
    }
}
