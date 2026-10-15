<?php

namespace App\Actions\Offboarding;

use App\Enums\EmploymentStatus;
use App\Enums\OffboardingCaseStage;
use App\Enums\OffboardingExitType;
use App\Enums\OffboardingFileKind;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingApprovers;
use App\Workflows\WorkflowEngine;
use Illuminate\Validation\ValidationException;

/**
 * Sends a draft case to Management for approval (OFF-WZ2-003). Sending checks the case is complete
 * and builds the approval chain: Management, always.
 *
 * A case Management rejected returns to Draft; sending it again builds a fresh chain, and what was
 * said before stays in the comment thread (WorkflowEngine::reopen).
 */
class SubmitOffboardingCase
{
    public function __construct(private WorkflowEngine $engine, private OffboardingApprovers $approvers) {}

    /**
     * @throws ValidationException
     */
    public function handle(OffboardingCase $case, User $by): OffboardingCase
    {
        $this->assertComplete($case);

        $instance = $case->workflow;
        $chain = $this->approvers->chainFor($case->employee);
        $wasRejected = $instance->steps()->where('status', 'rejected')->exists();

        if ($wasRejected) {
            $this->engine->reopen($instance, $chain, OffboardingCaseStage::Submitted, $by);
        } else {
            $this->engine->setChain($instance, $chain);
            $this->engine->transitionTo($instance->refresh(), OffboardingCaseStage::Submitted, $by);
        }

        $case->update(['submitted_at' => now()]);

        app(Notifier::class)->offboardingSubmitted($case->refresh(), $by, resubmitted: $wasRejected);

        return $case;
    }

    /**
     * @throws ValidationException
     */
    private function assertComplete(OffboardingCase $case): void
    {
        $problems = [];

        if ($case->employee->employment_status !== EmploymentStatus::Active) {
            $problems['employee_id'] = 'Karyawan ini sudah tidak aktif.';
        }

        if ($case->exit_type === null) {
            $problems['exit_type'] = 'Pilih jenis keluar.';
        }

        if ($case->notice_on === null) {
            $problems['notice_on'] = 'Isi tanggal pemberitahuan.';
        }

        if ($case->last_working_on === null) {
            $problems['last_working_on'] = 'Isi hari kerja terakhir.';
        }

        if (trim((string) $case->reason) === '') {
            $problems['reason'] = 'Isi alasan.';
        }

        if ($case->compensation_months > 0 && trim((string) $case->compensation_reason) === '') {
            $problems['compensation_reason'] = 'Tulis alasan kompensasi; Manajemen membacanya sebelum memutuskan.';
        }

        if ($case->exit_type === OffboardingExitType::Resign
            && ! $case->files()->where('kind', OffboardingFileKind::ResignationLetter->value)->exists()) {
            $problems['file_resignation_letter'] = 'Surat pengunduran diri wajib diunggah.';
        }

        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }
    }

    public function waitingStep(OffboardingCase $case): ?WorkflowStep
    {
        return $this->engine->waitingOn($case->workflow);
    }
}
