<?php

namespace App\Actions\Recruitment;

use App\Enums\DocumentCategory;
use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * HR makes the employee profile of a hire (REC-FIL-006/007). The fields have been checked by the same rules as any new
 * employee; this files the profile under the requisition's company and links it to the accepted offer, which is what stops
 * the same hire from being converted twice (the offer is locked while the profile is made). The hire then registers their
 * account against the employee number and NIK like anyone else (CORE-REG).
 */
class ConvertHireToEmployee
{
    /**
     * @param  array<string, mixed>  $fields  the validated employee fields, without the photo
     *
     * @throws ValidationException the hire already has a profile
     */
    public function handle(Requisition $requisition, CandidateOffer $offer, array $fields, ?UploadedFile $photo, User $by): Employee
    {
        return DB::transaction(function () use ($requisition, $offer, $fields, $photo, $by): Employee {
            $locked = CandidateOffer::acrossCompanies()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($locked->employee_id !== null) {
                throw ValidationException::withMessages(['employee_number' => 'Penawaran ini sudah dijadikan karyawan.']);
            }

            $employee = Employee::create([
                ...$fields,
                'company_id' => $requisition->company_id,
                'photo_path' => $photo?->store('employee-photos', Employee::PHOTO_DISK),
            ]);

            $locked->forceFill(['employee_id' => $employee->id])->save();

            $this->fileCandidateCv($requisition, $offer, $employee, $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate?->name, 'employee' => $employee->full_name, 'employee_number' => $employee->employee_number])
                ->log('Pelamar diterima dijadikan karyawan');

            return $employee;
        });
    }

    /**
     * Copies the candidate's CV onto the new employee's documents, restricted like any personal paper, so it stays with the
     * dossier. A candidate without a CV, or whose file is gone, files nothing; the copy never stops the conversion.
     */
    private function fileCandidateCv(Requisition $requisition, CandidateOffer $offer, Employee $employee, User $by): void
    {
        $candidate = $offer->candidate;

        if ($candidate === null || $candidate->cv_path === null || ! Storage::disk(Candidate::CV_DISK)->exists($candidate->cv_path)) {
            return;
        }

        $name = (string) ($candidate->cv_name ?: basename($candidate->cv_path));
        $path = "employee-documents/{$employee->id}/".Str::uuid().'.'.pathinfo($candidate->cv_path, PATHINFO_EXTENSION);

        Storage::disk(Candidate::CV_DISK)->copy($candidate->cv_path, $path);

        $document = $employee->documents()->create([
            'company_id' => $employee->company_id,
            'category' => DocumentCategory::Other,
            'title' => "CV — {$requisition->reference()}",
            'is_restricted' => true,
        ]);

        $document->versions()->create([
            'version_no' => 1,
            'path' => $path,
            'original_name' => $name,
            'mime_type' => Storage::disk(Candidate::CV_DISK)->mimeType($path) ?: null,
            'size_bytes' => (int) ($candidate->cv_size ?? Storage::disk(Candidate::CV_DISK)->size($path)),
            'uploaded_by_user_id' => $by->id,
            'note' => "Dari rekrutmen {$requisition->reference()}",
        ]);
    }
}
