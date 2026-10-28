<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\RequisitionApprovers;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A decision on the open step of a requisition at 01 Diajukan (REC-APR-002, REC-APR-006, REC-APR-008).
 *
 * Management approves first and must have set the salary expectation by then, here or with "Simpan" earlier.
 * HR approves after. Approving the last step leaves the requisition at Diajukan, ready for HR's "Lanjut ke
 * Posting" (REC-APR-003). A rejection needs a reason and returns it to its requester as a draft.
 */
class DecideRequisition
{
    public function __construct(private WorkflowEngine $engine, private SaveSalaryExpectation $salary) {}

    /**
     * @throws ValidationException Management approving without a salary expectation
     */
    public function approve(Requisition $requisition, User $by, ?string $note, ?int $salaryMin = null, ?int $salaryMax = null): void
    {
        $instance = $requisition->workflow;
        $step = $this->engine->waitingOn($instance);

        DB::transaction(function () use ($requisition, $by, $note, $salaryMin, $salaryMax, $instance, $step): void {
            if ($step->label === RequisitionApprovers::MANAGEMENT_LABEL) {
                if ($salaryMin !== null || $salaryMax !== null) {
                    $this->salary->handle($requisition, $by, $salaryMin, $salaryMax);
                }

                if (! $requisition->refresh()->hasSalaryExpectation()) {
                    throw ValidationException::withMessages([
                        'salary_min' => 'Isi ekspektasi gaji (minimal dan maksimal) sebelum menyetujui.',
                    ]);
                }
            }

            $this->engine->approve($step, $by, $note);
            $this->remember($instance, $by, $note);
        });

        $notifier = app(Notifier::class);

        if ($this->engine->chainIsApproved($instance->refresh())) {
            $notifier->requisitionDecided($requisition->refresh(), true, $note, $by);
        } else {
            $notifier->requisitionAwaiting($requisition->refresh(), $by);
        }
    }

    public function reject(Requisition $requisition, User $by, string $note): void
    {
        $instance = $requisition->workflow;
        $step = $this->engine->waitingOn($instance);

        $this->engine->reject($step, $by, $note);
        $this->remember($instance, $by, $note);
        $this->engine->transitionTo($instance->refresh(), RequisitionStage::Draft, $by);

        app(Notifier::class)->requisitionDecided($requisition->refresh(), false, $note, $by);
    }

    /**
     * A note given with a decision is also a comment, so it shows in the thread at once and stays with the
     * stage it was written in (REC-APR-009).
     */
    private function remember(WorkflowInstance $instance, User $by, ?string $note): void
    {
        if (trim((string) $note) !== '') {
            $this->engine->comment($instance, $by, (string) $note);
        }
    }
}
