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
 * this case only, ask HR to lock the list, and tick items off as they are done. Locking is HR's: the owner
 * asks, and while the request is waiting, or once the list is locked, the owner cannot add to it; they ask
 * HR to open it again. Who may do each is the policy's call; the order of things is settled here: a list
 * is chosen first, then locked, and only then worked.
 */
class ManageChecklist
{
    public function __construct(private ChecklistProgress $progress) {}

    /**
     * @throws ValidationException
     */
    public function select(OffboardingCaseDepartment $group, OffboardingItem $item, User $by): OffboardingCaseItem
    {
        $this->assertOpenForOwner($group);

        if (! in_array($item->id, $group->item_ids ?? [], strict: true) || ! $item->is_active) {
            throw ValidationException::withMessages(['item' => 'Item ini bukan bagian dari daftar Anda.']);
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
     * HR may add one to any list, locked or not; the owner only while the list is theirs to change (the
     * policy decides). It then has to be done like the rest.
     *
     * @throws ValidationException
     */
    public function addAdhoc(OffboardingCaseDepartment $group, string $name, OffboardingItemKind $kind, User $by): OffboardingCaseItem
    {
        $this->revokeApproval($group);

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

        // Once the list is locked it is the record of what was agreed; only an unlocked one is edited.
        if ($group->isSettled() && ! $item->is_adhoc) {
            throw ValidationException::withMessages(['item' => 'Daftar sudah dikunci; item tidak bisa dihapus.']);
        }

        if ($item->isDone()) {
            throw ValidationException::withMessages(['item' => 'Item yang sudah dicentang tidak bisa dihapus.']);
        }

        $item->delete();
        $this->revokeApproval($group);
    }

    /**
     * "Kunci Checklist", pressed by HR: the list is settled, even when no item applies (OFF-CHK-008). It answers the
     * owner's request when there was one.
     */
    public function lock(OffboardingCaseDepartment $group, User $by): void
    {
        $this->assertNotSettled($group);

        $group->update([
            'confirmed_at' => now(),
            'confirmed_by_user_id' => $by->id,
            'lock_requested_at' => null,
            'unlock_requested_at' => null,
        ]);

        app(Notifier::class)->offboardingLockAnswered($this->caseOf($group), $group, $by, "{$group->name}: daftar dikunci HR; sekarang centang item satu per satu.");
        $this->announceIfReady($group, $by);
    }

    /**
     * The owner has chosen what applies and asks HR to lock the list. Until HR answers, nobody but HR changes it.
     *
     * @throws ValidationException
     */
    public function requestLock(OffboardingCaseDepartment $group, User $by): void
    {
        $this->assertOpenForOwner($group);

        $group->update(['lock_requested_at' => now()]);

        app(Notifier::class)->offboardingLockRequested($this->caseOf($group), $group, $by, unlock: false);
    }

    /**
     * HR sends the list back to its owner to change, instead of locking it.
     */
    public function declineLock(OffboardingCaseDepartment $group, User $by): void
    {
        $group->update(['lock_requested_at' => null]);

        app(Notifier::class)->offboardingLockAnswered($this->caseOf($group), $group, $by, "{$group->name}: HR mengembalikan daftar untuk diperbaiki; ubah lalu ajukan kunci lagi.");
    }

    /**
     * The owner of a locked list asks HR to open it again, to add something.
     *
     * @throws ValidationException
     */
    public function requestUnlock(OffboardingCaseDepartment $group, User $by): void
    {
        if ($group->confirmed_at === null) {
            throw ValidationException::withMessages(['group' => 'Daftar ini belum dikunci.']);
        }

        if ($group->unlock_requested_at !== null) {
            throw ValidationException::withMessages(['group' => 'Permintaan buka kunci sudah diajukan.']);
        }

        $group->update(['unlock_requested_at' => now()]);

        app(Notifier::class)->offboardingLockRequested($this->caseOf($group), $group, $by, unlock: true);
    }

    /**
     * HR opens a locked list again. Items already ticked stay ticked; the list is unsettled until it is locked again, and the
     * approval of the checklist as a whole is taken back.
     */
    public function unlock(OffboardingCaseDepartment $group, User $by): void
    {
        $group->update([
            'confirmed_at' => null,
            'confirmed_by_user_id' => null,
            'lock_requested_at' => null,
            'unlock_requested_at' => null,
        ]);
        $this->revokeApproval($group);

        app(Notifier::class)->offboardingLockAnswered($this->caseOf($group), $group, $by, "{$group->name}: kunci dibuka HR; Anda bisa menambah item, lalu ajukan kunci lagi.");
    }

    /**
     * HR turns down a request to open a locked list; it stays locked.
     */
    public function declineUnlock(OffboardingCaseDepartment $group, User $by): void
    {
        $group->update(['unlock_requested_at' => null]);

        app(Notifier::class)->offboardingLockAnswered($this->caseOf($group), $group, $by, "{$group->name}: permintaan buka kunci ditolak HR; daftar tetap terkunci.");
    }

    /**
     * HR sets a department aside because nothing there concerns this leaver, or takes it back.
     */
    public function setNotInvolved(OffboardingCaseDepartment $group, bool $notInvolved, User $by): void
    {
        if ($notInvolved && $group->items()->exists()) {
            throw ValidationException::withMessages(['group' => 'Departemen ini sudah punya item; hapus dulu atau selesaikan.']);
        }

        $group->update(['not_involved' => $notInvolved, 'confirmed_at' => null, 'confirmed_by_user_id' => null, 'lock_requested_at' => null, 'unlock_requested_at' => null]);
        $this->revokeApproval($group);

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
            throw ValidationException::withMessages(['item' => 'Daftar harus dikunci HR dulu sebelum item dicentang.']);
        }

        $item->update(['done_at' => now(), 'done_by_user_id' => $by->id, 'note' => filled($note) ? trim($note) : null]);

        $this->announceIfReady($item->group, $by);
    }

    public function reopen(OffboardingCaseItem $item): void
    {
        $item->update(['done_at' => null, 'done_by_user_id' => null]);
        $this->revokeApproval($item->group);
    }

    /**
     * HR's approval of the checklist stands for what was there when it was given; a change takes it back.
     */
    private function revokeApproval(OffboardingCaseDepartment $group): void
    {
        OffboardingCase::acrossCompanies()->whereKey($group->offboarding_case_id)->update(['checklist_approved_at' => null, 'checklist_approved_by_user_id' => null]);
    }

    /**
     * @throws ValidationException
     */
    private function assertNotSettled(OffboardingCaseDepartment $group): void
    {
        if ($group->isSettled()) {
            throw ValidationException::withMessages(['group' => 'Daftar departemen ini sudah dikunci.']);
        }
    }

    /**
     * The list is still the owner's to change: not locked, and not waiting for HR to lock it.
     *
     * @throws ValidationException
     */
    private function assertOpenForOwner(OffboardingCaseDepartment $group): void
    {
        $this->assertNotSettled($group);

        if ($group->lock_requested_at !== null) {
            throw ValidationException::withMessages(['group' => 'Daftar ini menunggu persetujuan HR; tidak bisa diubah.']);
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
