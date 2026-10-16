<?php

namespace App\Actions\Offboarding;

use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingAudience;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * HR changes the last working day after Management has approved it: an extension, or an early release.
 * It needs no new approval, but Management and the others in the case are told, and the change is written
 * into the thread. Not possible once the exit has been applied.
 */
class ChangeLastWorkingDay
{
    public function __construct(private WorkflowEngine $engine, private OffboardingAudience $audience) {}

    /**
     * @throws ValidationException
     */
    public function handle(OffboardingCase $case, string $date, ?string $note, User $by): void
    {
        $new = CarbonImmutable::parse($date);
        $today = CarbonImmutable::parse(CarbonImmutable::now((string) config('attendance.device_timezone'))->format('Y-m-d'));

        if ($case->exitApplied()) {
            throw ValidationException::withMessages(['last_working_on' => 'Keluarnya sudah diterapkan; hari terakhir tidak bisa diubah.']);
        }

        if ($new < $today || ($case->notice_on !== null && $new < $case->notice_on)) {
            throw ValidationException::withMessages(['last_working_on' => 'Hari terakhir tidak boleh sebelum hari ini atau sebelum tanggal pemberitahuan.']);
        }

        $old = $case->last_working_on?->toDateString();

        if ($old === $new->toDateString()) {
            return;
        }

        $case->update(['last_working_on' => $new->toDateString()]);

        $text = "Hari kerja terakhir diubah dari {$old} menjadi {$new->toDateString()}.".(filled($note) ? ' Alasan: '.trim((string) $note) : '');
        $this->engine->comment($case->workflow, $by, $text);

        app(Notifier::class)->offboardingEvent($case, $this->audience->everyone($case), $by, "{$case->reference()}: hari terakhir diubah", $text);
    }
}
