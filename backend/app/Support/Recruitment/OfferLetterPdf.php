<?php

namespace App\Support\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Requisition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * The offer letter as a PDF file (email automation): what goes to the candidate as an attachment, to sign and scan. It is
 * drawn from the same letter the print page shows ({@see OfferLetterComposer}), so the two cannot disagree. The letter HR
 * sees before the offer is sent follows the template as it is now; the one made when it is sent is made from the saved letter
 * and kept, so it stays exactly what the candidate holds.
 */
class OfferLetterPdf
{
    /** The logo formats the PDF library can draw. */
    private const LOGO_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    public function __construct(private OfferLetterComposer $letters) {}

    /**
     * The letter as it reads for this offer: the saved one once sent, the current one before.
     */
    public function render(Requisition $requisition, CandidateOffer $offer): string
    {
        return $this->fromLetter($this->letters->letter($requisition, $offer), $requisition);
    }

    /**
     * A letter already written, as a PDF.
     *
     * @param  array<string, mixed>  $letter  see OfferLetterComposer::snapshot()
     */
    public function fromLetter(array $letter, Requisition $requisition): string
    {
        $requisition->loadMissing('company');

        return Pdf::loadView('pdf.offer-letter', ['letter' => $letter, 'logo' => $this->logoOf($requisition)])
            ->setPaper('a4', 'portrait')
            // Only the letters the text uses are put in the file: the whole font makes it close to a megabyte.
            ->setOption(['enable_font_subsetting' => true])
            ->output();
    }

    /**
     * The company's logo as data inside the page, so the PDF library never has to fetch anything. A logo it cannot draw (an SVG)
     * is left out rather than breaking the letter.
     */
    private function logoOf(Requisition $requisition): ?string
    {
        $path = $requisition->company->logo_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $type = self::LOGO_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        return $type === null ? null : 'data:'.$type.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
