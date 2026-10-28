<?php

namespace App\Actions\Recruitment;

use App\Enums\EmploymentStatus;
use App\Enums\RequisitionStage;
use App\Http\Requests\SaveRequisitionRequest;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\RequisitionApprovers;
use App\Support\Recruitment\RequisitionTemplate;
use App\Workflows\WorkflowEngine;
use Illuminate\Validation\ValidationException;

/**
 * "Ajukan untuk Persetujuan" (REC-WZ3-002): checks the requisition follows the template and builds its approval
 * chain, Management then HR, which moves it to 01 Diajukan.
 *
 * A requisition sent back by a rejection returns to Draft; sending it again builds a fresh chain, and what was
 * said before stays in the comment thread (WorkflowEngine::reopen).
 */
class SubmitRequisition
{
    public function __construct(private WorkflowEngine $engine, private RequisitionApprovers $approvers) {}

    /**
     * @throws ValidationException
     */
    public function handle(Requisition $requisition, User $by): Requisition
    {
        $this->assertComplete($requisition);

        $instance = $requisition->workflow;
        $chain = $this->approvers->chainFor($requisition);
        $wasRejected = $this->engine->wasRejected($instance);

        if ($wasRejected) {
            $this->engine->reopen($instance, $chain, RequisitionStage::Submitted, $by);
        } else {
            $this->engine->setChain($instance, $chain);
            $this->engine->transitionTo($instance->refresh(), RequisitionStage::Submitted, $by);
        }

        $requisition->update(['submitted_at' => now()]);

        app(Notifier::class)->requisitionAwaiting($requisition->refresh(), $by, resubmitted: $wasRejected);

        return $requisition;
    }

    /**
     * What the template asks before anyone is asked to approve (REC-WZ1-001..004, REC-WZ2-001/002): every field of
     * step 01, the posting's Tentang Posisi and at least one Tanggung Jawab Utama with a title and a line each, and a
     * line in each required section of the checklist (Kualifikasi, Skills). Benefit & Fasilitas and Nilai Plus may be
     * empty. A start date that has passed since the draft was saved is caught here too, so it is never sent.
     *
     * @throws ValidationException
     */
    private function assertComplete(Requisition $requisition): void
    {
        $problems = [];

        if (trim($requisition->position_name) === '') {
            $problems['position_name'] = 'Isi nama posisi.';
        }

        if ($requisition->quota === null || $requisition->quota < 1) {
            $problems['quota'] = 'Isi jumlah kuota, minimal 1.';
        }

        if ($requisition->employment_type === null) {
            $problems['employment_type'] = 'Pilih tipe kontrak.';
        } elseif (Requisition::typeNeedsMonths($requisition->employment_type) && $requisition->contract_months === null) {
            $problems['contract_months'] = 'Isi lama kontrak dalam bulan.';
        }

        if ($requisition->manager_employee_id === null) {
            $problems['manager_employee_id'] = 'Pilih atasan langsung.';
        } elseif (! Employee::acrossCompanies()->whereKey($requisition->manager_employee_id)->where('company_id', $requisition->company_id)
            ->where('employment_status', EmploymentStatus::Active)->exists()) {
            $problems['manager_employee_id'] = 'Atasan langsung sudah tidak aktif. Pilih yang lain.';
        }

        if ($requisition->target_start_on === null) {
            $problems['target_start_on'] = 'Isi target mulai kerja.';
        } elseif ($requisition->target_start_on->toDateString() < SaveRequisitionRequest::today()->toDateString()) {
            $problems['target_start_on'] = 'Target mulai kerja sudah lewat. Pilih tanggal hari ini atau sesudahnya.';
        }

        if (trim((string) $requisition->reason) === '') {
            $problems['reason'] = 'Isi alasan permintaan.';
        }

        if (trim((string) $requisition->about) === '') {
            $problems['about'] = 'Isi Tentang Posisi.';
        }

        $problems = [...$problems, ...$this->responsibilityProblems($requisition), ...$this->checklistProblems($requisition)];

        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }
    }

    /**
     * @return array<string, string>
     */
    private function responsibilityProblems(Requisition $requisition): array
    {
        $subtitles = $requisition->responsibilities ?? [];

        if ($subtitles === []) {
            return ['responsibilities' => 'Tambahkan minimal satu subtitle Tanggung Jawab Utama dengan satu baris.'];
        }

        foreach ($subtitles as $subtitle) {
            if (trim($subtitle['title']) === '') {
                return ['responsibilities' => 'Setiap subtitle Tanggung Jawab Utama perlu judul.'];
            }

            if ($subtitle['items'] === []) {
                return ['responsibilities' => "Subtitle \"{$subtitle['title']}\" belum punya baris. Isi satu baris atau hapus subtitle itu."];
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function checklistProblems(Requisition $requisition): array
    {
        $checklist = $requisition->checklist ?? [];
        $problems = [];

        foreach (RequisitionTemplate::REQUIRED as $section => $definition) {
            $lines = 0;

            foreach ($definition['keys'] as $key) {
                $lines += count($checklist[$key] ?? []);
            }

            if ($lines === 0) {
                $problems[$section] = "{$definition['label']} wajib: isi minimal satu baris.";
            }
        }

        return $problems;
    }
}
