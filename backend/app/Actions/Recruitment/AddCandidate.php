<?php

namespace App\Actions\Recruitment;

use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Files\UploadFiler;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * HR shortlists a candidate from what they read on the platform (REC-SCR): name, phone and/or email, where they came
 * from, a note and optionally the CV. The same phone number or email already shortlisted anywhere in the company is
 * reported first, so nobody is invited twice; HR can confirm it is a different person and save anyway.
 */
class AddCandidate
{
    public function __construct(private UploadFiler $filer) {}

    /**
     * @param  array{name: string, phone: string|null, email: string|null, source: string, source_name: string|null, note: string|null}  $fields
     *
     * @throws ValidationException the person looks already shortlisted and it was not confirmed
     */
    public function handle(Requisition $requisition, array $fields, User $by, ?UploadedFile $cv = null, bool $confirmedNotDuplicate = false): Candidate
    {
        $phoneKey = Candidate::phoneKey($fields['phone']);
        $email = filled($fields['email']) ? mb_strtolower(trim((string) $fields['email'])) : null;

        if (! $confirmedNotDuplicate) {
            $this->refuseDuplicate($phoneKey, $email);
        }

        $source = CandidateSource::from($fields['source']);
        $stored = $cv === null ? null : $this->filer->store($cv, Candidate::CV_DIRECTORY.'/'.$requisition->id, Candidate::CV_DISK);

        $candidate = Candidate::query()->create([
            'company_id' => $requisition->company_id,
            'requisition_id' => $requisition->id,
            'name' => trim($fields['name']),
            'phone' => filled($fields['phone']) ? trim((string) $fields['phone']) : null,
            'phone_key' => $phoneKey,
            'email' => $email,
            'source' => $source,
            'source_name' => $source === CandidateSource::Other ? $fields['source_name'] : null,
            'note' => filled($fields['note']) ? trim((string) $fields['note']) : null,
            'cv_path' => $stored['path'] ?? null,
            'cv_name' => $cv?->getClientOriginalName(),
            'cv_size' => $stored['size'] ?? null,
            'added_by_user_id' => $by->id,
        ]);

        // Everyone shortlisted applied, so a total entered earlier cannot stay below the shortlist.
        $shortlisted = $requisition->candidates()->count();

        if ($requisition->applicants_count !== null && $requisition->applicants_count < $shortlisted) {
            $requisition->update(['applicants_count' => $shortlisted]);
        }

        return $candidate;
    }

    /**
     * @throws ValidationException
     */
    private function refuseDuplicate(?string $phoneKey, ?string $email): void
    {
        if ($phoneKey === null && $email === null) {
            return;
        }

        $same = Candidate::query()
            ->with('requisition:id,position_name')
            ->where(function ($query) use ($phoneKey, $email): void {
                $query->when($phoneKey !== null, fn ($inner) => $inner->where('phone_key', $phoneKey))
                    ->when($email !== null, fn ($inner) => $inner->orWhere('email', $email));
            })
            ->first();

        if (! $same instanceof Candidate) {
            return;
        }

        $where = $same->requisition instanceof Requisition ? "{$same->requisition->reference()} ({$same->requisition->position_name})" : 'lowongan lain';

        throw ValidationException::withMessages([
            'duplicate' => "{$same->name} sudah ada di shortlist {$where} dengan nomor telepon atau email yang sama.",
        ]);
    }
}
