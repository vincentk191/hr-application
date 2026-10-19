<?php

namespace App\Actions\Offboarding;

use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingOwners;
use Illuminate\Validation\ValidationException;

/**
 * HR hands one department's part of a case's checklist to someone else, or takes it back (OFF-CHK-003): the
 * owner is away, has left, or the work belongs elsewhere this time. It changes this case only, never the
 * master list. The new owner may belong to another company.
 */
class ReassignGroupOwner
{
    public function __construct(private OffboardingOwners $owners) {}

    /**
     * @param  int|null  $userId  the new owner, or null for HR Admin
     *
     * @throws ValidationException
     */
    public function handle(OffboardingCaseDepartment $group, ?int $userId, User $by): void
    {
        $case = OffboardingCase::acrossCompanies()->with('employee.user')->findOrFail($group->offboarding_case_id);
        $owner = null;

        if ($userId !== null) {
            $owner = User::query()->whereKey($userId)->where('is_active', true)->first();
            $hasRecord = $owner !== null && Employee::acrossCompanies()->where('user_id', $owner->id)->exists();

            if ($owner === null || ! $hasRecord || $owner->is($case->employee->user)) {
                throw ValidationException::withMessages(['user_id' => 'Pemilik baru harus karyawan aktif dengan akun, dan bukan orang yang keluar.']);
            }
        }

        $about = $owner === null ? null : $this->owners->describe($owner);

        $group->update([
            'owner_user_id' => $owner?->id,
            'owner_title' => $about['title'] ?? null,
            'owner_department' => $about['department'] ?? null,
        ]);

        if ($owner !== null) {
            app(Notifier::class)->offboardingEvent($case, [$owner], $by, "Offboarding {$case->employee->full_name}: Anda ditunjuk untuk {$group->name}", 'Pilih item yang berlaku, konfirmasi daftar, lalu centang saat selesai.');
        }
    }
}
