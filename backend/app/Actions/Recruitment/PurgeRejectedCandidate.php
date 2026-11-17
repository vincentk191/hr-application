<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\EmailMessage;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the whole record of a rejected candidate once the retention period is over: the candidate, their evaluations and
 * interviewers, their offers with the offer's approval chain and the letter files, the emails written about them, and the CV.
 * A candidate who became an employee is never touched; the profile has its own dossier. The audit trail keeps the plain
 * words of what happened, since it is the account of who did what.
 */
class PurgeRejectedCandidate
{
    /**
     * @return bool whether the record was deleted (false for a candidate who became an employee)
     */
    public function handle(Candidate $candidate): bool
    {
        $offers = CandidateOffer::acrossCompanies()->where('candidate_id', $candidate->id)->get();

        if ($offers->contains(fn (CandidateOffer $offer): bool => $offer->employee_id !== null)) {
            return false;
        }

        $files = [
            [Candidate::CV_DISK, $candidate->cv_path],
            ...$offers->flatMap(fn (CandidateOffer $offer): array => [
                [CandidateOffer::DISK, $offer->letter_pdf_path],
                [CandidateOffer::DISK, $offer->signed_letter_path],
            ])->all(),
        ];

        DB::transaction(function () use ($candidate, $offers): void {
            EmailMessage::acrossCompanies()
                ->where(fn ($emails) => $emails
                    ->where(fn ($own) => $own->where('related_type', $candidate->getMorphClass())->where('related_id', $candidate->id))
                    ->orWhere(fn ($ofOffers) => $ofOffers->where('related_type', (new CandidateOffer)->getMorphClass())->whereIn('related_id', $offers->modelKeys())))
                ->delete();

            WorkflowInstance::acrossCompanies()
                ->where('subject_type', (new CandidateOffer)->getMorphClass())
                ->whereIn('subject_id', $offers->modelKeys())
                ->delete();

            $candidate->delete();
        });

        foreach ($files as [$disk, $path]) {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }
        }

        return true;
    }
}
