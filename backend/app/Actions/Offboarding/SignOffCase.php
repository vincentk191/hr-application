<?php

namespace App\Actions\Offboarding;

use App\Models\OffboardingCase;
use App\Models\OffboardingSignoff;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingAudience;
use Illuminate\Validation\ValidationException;

/**
 * One person signs (OFF-SIG-005, OFF-SIG-006). Each signature stays locked until the one before it is
 * done; HR's also needs the exit interview recorded, or skipped with a reason, because HR attests that
 * everything was received.
 */
class SignOffCase
{
    public function __construct(private OffboardingAudience $audience) {}

    /**
     * @throws ValidationException
     */
    public function handle(OffboardingSignoff $signoff, User $by, ?string $note): void
    {
        $case = OffboardingCase::acrossCompanies()->with('employee', 'creator')->findOrFail($signoff->offboarding_case_id);

        if ($signoff->isSigned()) {
            throw ValidationException::withMessages(['signoff' => 'Tanda tangan ini sudah diberikan.']);
        }

        $previousOpen = $case->signoffs()->where('position', '<', $signoff->position)->whereNull('signed_at')->exists();

        if ($previousOpen) {
            throw ValidationException::withMessages(['signoff' => 'Menunggu tanda tangan sebelumnya.']);
        }

        if ($signoff->user_id === null && $case->exit_interview_at === null) {
            throw ValidationException::withMessages(['signoff' => 'Catat wawancara keluar dulu, atau lewati dengan alasan.']);
        }

        $signoff->update(['signed_at' => now(), 'signed_by_user_id' => $by->id, 'note' => filled($note) ? trim($note) : null]);

        $next = $case->signoffs()->whereNull('signed_at')->first();

        if ($next === null) {
            app(Notifier::class)->offboardingEvent($case, $this->audience->everyone($case), $by, "{$case->reference()}: semua tanda tangan lengkap", 'Kasus bisa ditutup setelah hari kerja terakhir.');
        } else {
            app(Notifier::class)->offboardingEvent($case, [$case->creator], $by, "{$case->reference()}: {$by->name} sudah menandatangani", "Giliran berikutnya: {$next->label}.");
        }
    }
}
