<?php

namespace App\Actions\Offboarding;

use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingItem;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingCatalog;
use App\Support\Offboarding\OffboardingOwners;
use Illuminate\Support\Facades\DB;

/**
 * "Perbarui dari daftar induk" (OFF-ITM-008): a checklist is shared out when it opens, so an item added to the
 * master list afterwards never reaches a case that is already running. HR can bring the case up to date.
 *
 * Only items the case has not been given yet are added, each to the group of its owner: into that owner's
 * list if they have not confirmed it, otherwise into a new group for them ("tambahan"), so nothing already
 * chosen, confirmed or ticked is ever changed. An owner who gets something new is told. A case whose
 * checklist was never opened (it was approved before checklists existed) is opened by this too.
 */
class RefreshChecklist
{
    public function __construct(private OffboardingCatalog $catalog, private OffboardingOwners $owners) {}

    /**
     * The master items this case has not been given: active, and not in any of its groups.
     *
     * @return list<OffboardingItem>
     */
    public function missingItems(OffboardingCase $case): array
    {
        $given = OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)->get()
            ->flatMap(fn (OffboardingCaseDepartment $group): array => $group->item_ids ?? [])
            ->all();

        return array_values(OffboardingItem::query()->active()->whereNotIn('id', $given)->orderBy('sort_order')->get()->all());
    }

    /**
     * @return int how many items were added
     */
    public function handle(OffboardingCase $case, User $by): int
    {
        $case->loadMissing('employee.user');
        $this->catalog->ensureDefaults();

        $added = 0;
        $told = [];

        DB::transaction(function () use ($case, &$added, &$told): void {
            foreach ($this->missingItems($case) as $item) {
                $owner = $this->owners->ownerOf($item, $case->employee);
                $group = $this->groupFor($case, $item, $owner);

                $group->update(['item_ids' => [...($group->item_ids ?? []), $item->id]]);
                $added++;

                if ($owner !== null) {
                    $told[$owner->id] = ['owner' => $owner, 'count' => ($told[$owner->id]['count'] ?? 0) + 1];
                }
            }
        });

        if ($added > 0) {
            $case->update(['checklist_approved_at' => null, 'checklist_approved_by_user_id' => null]);
        }

        foreach ($told as $entry) {
            app(Notifier::class)->offboardingEvent(
                $case,
                [$entry['owner']],
                $by,
                "Offboarding {$case->employee->full_name}: {$entry['count']} item baru untuk Anda",
                'Buka kasus, pilih item yang berlaku, lalu konfirmasi daftar.',
            );
        }

        return $added;
    }

    /**
     * The group an item goes into: its owner's, if they have not confirmed it, else a new one.
     */
    private function groupFor(OffboardingCase $case, OffboardingItem $item, ?User $owner): OffboardingCaseDepartment
    {
        $about = $owner === null ? null : $this->owners->describe($owner);
        $name = ($about['department'] ?? null) ?: ($item->department_name ?: 'Tanpa departemen');

        $mine = OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)
            ->when($owner === null, fn ($query) => $query->whereNull('owner_user_id')->whereRaw('lower(name) = ?', [mb_strtolower($name)]),
                fn ($query) => $query->where('owner_user_id', $owner->id))
            ->orderBy('id')->get();

        $open = $mine->first(fn (OffboardingCaseDepartment $group): bool => ! $group->isSettled());

        if ($open !== null) {
            return $open;
        }

        return OffboardingCaseDepartment::create([
            'company_id' => $case->company_id,
            'offboarding_case_id' => $case->id,
            'name' => $mine->isEmpty() ? $name : "{$name} (tambahan)",
            'owner_user_id' => $owner?->id,
            'owner_title' => $about['title'] ?? null,
            'owner_department' => $about['department'] ?? null,
            'item_ids' => [],
        ]);
    }
}
