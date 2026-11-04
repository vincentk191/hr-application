<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use Illuminate\Support\Facades\Storage;

/**
 * HR takes someone off the shortlist (REC-SCR): the row and the CV file go, since nobody is kept who was not chosen.
 */
class RemoveCandidate
{
    public function handle(Candidate $candidate): void
    {
        if ($candidate->cv_path !== null) {
            Storage::disk(Candidate::CV_DISK)->delete($candidate->cv_path);
        }

        $candidate->delete();
    }
}
