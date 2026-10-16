<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingItemKind;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseItem;
use App\Models\OffboardingItem;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\ChecklistProgress;
use Illuminate\Validation\ValidationException;

/**
 * What the owners of a checklist do (OFF-ITM-004, OFF-CHK-003): pick the items that apply, add one for
 * this case only, confirm the list, and tick items off as they are done. Who may do each is the
 * policy's call; the order of things is settled here: a list is chosen first, then confirmed, and only
 * then worked.
 */
class ManageChecklist
{
    public function __construct(private ChecklistProgress $progress) {}

    /**
     * @throws ValidationException
     */
    public function select(OffboardingCaseDepartment $group, OffboardingItem $item, User $by): OffboardingCaseItem
    {
        $this->assertOpen($group);

        if ($item->department_id !== $group->department_id || ! $item->is_active) {
            throw ValidationException::withMessages(['item' => 'Item ini bukan bagian dari departemen ini.']);
        }

        return OffboardingCaseItem::query()->firstOrCreate(
            ['offboarding_case_department_id' => $group->id, 'offboarding_item_id' => $item->id],
            [
                'company_id' => $group->company_id,
                'offboarding_case_id' => $group->offboarding_case_id,
                'name' => $item->name,
                'kind' => $item->kind,
                'added_by_user_id' => $by->id,
            ],
        );
    }

    /**
     * Added for this case only (OFF-CHK-006): a handover, a forwarded mailbox, a client to reassign.
     * HR and the owner may add one even after the list is confirmed; it then has to be done like the rest.
     *
     * @throws ValidationException
     */
    public function addAdhoc(OffboardingCaseDepartment $group, string $name, OffboardingItemKind $kind, User $by): OffboardingCaseItem
    {
        return OffboardingCaseItem::create([
            'company_id' => $group->company_id,
            'offboarding_case_id' => $group->offboarding_case_id,
            'offboarding_case_department_id' => $group->id,
            'name' => trim($name),
            'kind' => $kind,
            'is_adhoc' => true,
            'added_by_user_id' => $by->id,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function remove(OffboardingCaseItem $item): void
    {
        $group = $item->group;

        // Once the list is confirmed it is the record of what was agreed; only an unconfirmed one is edited.
        if ($group->isSettled() && ! $item->is_adhoc) {
            throw ValidationException::withMessages(['item' => 'Daftar sudah dikonfirmasi; item tidak bisa dihapus.']);
        }

        if ($item->isDone()) {
            throw ValidationException::withMessages(['item' => 'Item yang sudah dicentang tidak bisa dihapus.']);
        }

        $item->delete();
    }

    /**
     * "Daftar selesai": the owner is happy with the list, even when no item applies (OFF-CHK-008).
     */
    public function confirm(OffboardingCaseDepartment $group, User $by): void
    {
        $this->assertOpen($group);

        $group->update(['confirmed_at' => now(), 'confirmed_by_user_id' => $by->id]);

        app(Notifier::class)->offboardingChecklistProgress($this->caseOf($group), $by, "{$group->name}: daftar item sudah dikonfirmasi");
        $this->announceIfReady($group, $by);
    }

    /**
     * HR sets a department aside because nothing there concerns this leaver, or takes it back.
     */
    public function setNotInvolved(OffboardingCaseDepartment $group, bool $notInvolved, User $by): void
    {
        if ($notInvolved && $group->items()->exists()) {
            throw ValidationException::withMessages(['group' => 'Departemen ini sudah punya item; hapus dulu atau selesaikan.']);
        }

        $group->update(['not_involved' => $notInvolved, 'confirmed_at' => null, 'confirmed_by_user_id' => null]);

        if ($notInvolved) {
            $this->announceIfReady($group, $by);
        }
    }

    /**
     * @throws ValidationException
     */
    public function markDone(OffboardingCaseItem $item, ?string $note, User $by): void
    {
        if ($item->group->confirmed_at === null) {
            throw ValidationException::withMessages(['item' => 'Konfirmasi daftar dulu sebelum mencentang item.']);
        }

        $item->update(['done_at' => now(), 'done_by_user_id' => $by->id, 'note' => filled($note) ? trim($note) : null]);

        $this->announceIfReady($item->group, $by);
    }

    public function reopen(OffboardingCaseItem $item): void
    {
        $item->update(['done_at' => null, 'done_by_user_id' => null]);
    }

    /**
     * @throws ValidationException
     */
    private function assertOpen(OffboardingCaseDepartment $group): void
    {
        if ($group->isSettled()) {
            throw ValidationException::withMessages(['group' => 'Daftar departemen ini sudah dikonfirmasi.']);
        }
    }

    /**
     * When the last open thing is done, HR is told the case can go to sign-off.
     */
    private function announceIfReady(OffboardingCaseDepartment $group, User $by): void
    {
        $case = $this->caseOf($group);

        if ($this->progress->for($case)['ready']) {
            app(Notifier::class)->offboardingChecklistProgress($case, $by, 'Checklist lengkap: kasus siap lanjut ke sign-off');
        }
    }

    private function caseOf(OffboardingCaseDepartment $group): OffboardingCase
    {
        return OffboardingCase::acrossCompanies()->findOrFail($group->offboarding_case_id);
    }
}
