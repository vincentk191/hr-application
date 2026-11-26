<?php

namespace App\Actions\Recruitment;

use App\Enums\DocumentCategory;
use App\Models\CandidateOffer;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies the offer letter the candidate signed and sent back onto the employee's documents (Kontrak, restricted like any
 * personal paper), so the agreement stays with the dossier. Done when the profile is made from the hire and, if the letter
 * is filed after that, when it is filed. The offer keeps its own copy.
 *
 * A second letter for the same offer is a new version of the same document, not a second document. A letter that is not
 * there (none filed, or its file gone) files nothing, and the copy never stops what called it.
 */
class FileSignedLetterOnEmployee
{
    public function handle(Requisition $requisition, CandidateOffer $offer, Employee $employee, User $by): void
    {
        $disk = Storage::disk(CandidateOffer::DISK);

        if ($offer->signed_letter_path === null || ! $disk->exists($offer->signed_letter_path)) {
            return;
        }

        $document = $employee->documents()->firstOrCreate(
            ['title' => "Surat penawaran bertanda tangan — {$requisition->reference()}", 'category' => DocumentCategory::Contract->value],
            ['company_id' => $employee->company_id, 'is_restricted' => true],
        );

        $path = "employee-documents/{$employee->id}/".Str::uuid().'.'.pathinfo($offer->signed_letter_path, PATHINFO_EXTENSION);

        $disk->copy($offer->signed_letter_path, $path);

        $document->versions()->create([
            'version_no' => ((int) $document->versions()->max('version_no')) + 1,
            'path' => $path,
            'original_name' => (string) ($offer->signed_letter_name ?: basename($offer->signed_letter_path)),
            'mime_type' => $disk->mimeType($path) ?: null,
            'size_bytes' => (int) ($offer->signed_letter_size ?? $disk->size($path)),
            'uploaded_by_user_id' => $by->id,
            'note' => "Dari rekrutmen {$requisition->reference()}",
        ]);
    }
}
