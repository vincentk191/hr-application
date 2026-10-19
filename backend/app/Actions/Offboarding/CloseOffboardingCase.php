<?php

namespace App\Actions\Offboarding;

use App\Enums\EmploymentStatus;
use App\Enums\OffboardingCaseStage;
use App\Enums\OffboardingFileKind;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseFile;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingAudience;
use App\Support\Offboarding\OffboardingFiles;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HR closes the case, which is archived (OFF-SIG-008). The guards on the workflow decide whether it may
 * close at all: the exit details filled in, the last working day behind us, the leaver's reports moved on.
 * Closing then makes the exit final: the employee record becomes inactive and locked, the exit documents
 * are filed on the dossier as restricted documents, and the exit is applied if the daily job has not
 * already done so.
 */
class CloseOffboardingCase
{
    /** The case files that belong on the dossier as well (OFF-SIG-004). */
    private const DOSSIER_KINDS = [OffboardingFileKind::ResignationLetter, OffboardingFileKind::ExitInterviewNotes, OffboardingFileKind::ReferenceLetter];

    public function __construct(private WorkflowEngine $engine, private ApplyOffboardingExit $exit, private OffboardingAudience $audience) {}

    public function handle(OffboardingCase $case, User $by): void
    {
        $audience = $this->audience->everyone($case);

        DB::transaction(function () use ($case, $by): void {
            $this->engine->transitionTo($case->workflow, OffboardingCaseStage::Closed, $by);

            $this->exit->handle($case->refresh());

            Employee::acrossCompanies()->whereKey($case->employee_id)->firstOrFail()->update([
                'employment_status' => EmploymentStatus::Inactive,
                'exited_on' => $case->last_working_on?->toDateString(),
            ]);

            $this->fileOnDossier($case, $by);

            $case->update(['closed_at' => now()]);
        });

        app(Notifier::class)->offboardingEvent($case, $audience, $by, "{$case->reference()}: kasus ditutup", 'Profil karyawan dikunci dan akunnya dinonaktifkan.');
    }

    /**
     * Copies the resignation letter, exit-interview notes and reference letter onto the employee's
     * documents, restricted, so they stay with the dossier after the case is archived.
     */
    private function fileOnDossier(OffboardingCase $case, User $by): void
    {
        $files = OffboardingCaseFile::acrossCompanies()->where('offboarding_case_id', $case->id)
            ->whereIn('kind', array_map(fn (OffboardingFileKind $kind): string => $kind->value, self::DOSSIER_KINDS))->get();

        foreach ($files as $file) {
            $document = Employee::acrossCompanies()->findOrFail($case->employee_id)->documents()->create([
                'company_id' => $case->company_id,
                'category' => 'other',
                'title' => "{$file->kind->label()} — {$case->reference()}",
                'is_restricted' => true,
            ]);

            $path = "employee-documents/{$case->employee_id}/".Str::uuid().'.'.pathinfo($file->original_name, PATHINFO_EXTENSION);
            Storage::disk(OffboardingFiles::DISK)->copy($file->path, $path);

            $document->versions()->create([
                'version_no' => 1,
                'path' => $path,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'uploaded_by_user_id' => $by->id,
                'note' => "Dari kasus offboarding {$case->reference()}",
            ]);
        }
    }
}
