<?php

namespace App\Actions\Recruitment;

use App\Models\Requisition;
use Illuminate\Validation\ValidationException;

/**
 * HR enters how many people applied in total, across every platform (REC-SCR). Only the number is kept. It cannot be
 * lower than the shortlist, since everyone shortlisted applied.
 */
class SaveApplicantCount
{
    /**
     * @throws ValidationException the number is below the shortlist
     */
    public function handle(Requisition $requisition, int $count): void
    {
        $shortlisted = $requisition->candidates()->count();

        if ($count < $shortlisted) {
            throw ValidationException::withMessages([
                'applicants_count' => "Jumlah pelamar tidak boleh kurang dari shortlist ({$shortlisted} orang).",
            ]);
        }

        $requisition->update(['applicants_count' => $count]);
    }
}
