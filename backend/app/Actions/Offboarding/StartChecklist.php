<?php

namespace App\Actions\Offboarding;

use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingItem;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingCatalog;
use App\Support\Offboarding\OffboardingOwners;

/**
 * Opens a case's checklist once Management has approved it (OFF-ITM-007): the active master items are
 * shared out by owner. Each owner (a named person, or the supervisor of the item's department, in the
 * leaver's company) gets one group holding the items that are theirs, and is told to pick what applies.
 * Items nobody can own go to HR Admin, one group per department name.
 *
 * A group remembers its owner, their job title and department as they were, and which master items it
 * was given, so later changes to the list or to people do not move work that is under way.
 */
class StartChecklist
{
    public function __construct(private OffboardingCatalog $catalog, private OffboardingOwners $owners) {}

    public function handle(OffboardingCase $case): void
    {
        if (OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)->exists()) {
            return;
        }

        $case->loadMissing('employee.user');
        $this->catalog->ensureDefaults();

        $groups = [];

        foreach (OffboardingItem::query()->active()->orderBy('sort_order')->get() as $item) {
            $owner = $this->owners->ownerOf($item, $case->employee);
            $key = $owner === null ? 'hr|'.mb_strtolower((string) $item->department_name) : 'u'.$owner->id;

            $about = $owner === null ? null : $this->owners->describe($owner);

            $groups[$key] ??= [
                'owner' => $owner,
                'name' => ($about['department'] ?? null) ?: ($item->department_name ?: 'Tanpa departemen'),
                'title' => $about['title'] ?? null,
                'department' => $about['department'] ?? null,
                'items' => [],
            ];
            $groups[$key]['items'][] = $item->id;
        }

        foreach ($groups as $group) {
            OffboardingCaseDepartment::create([
                'company_id' => $case->company_id,
                'offboarding_case_id' => $case->id,
                'name' => $group['name'],
                'owner_user_id' => $group['owner']?->id,
                'owner_title' => $group['title'],
                'owner_department' => $group['department'],
                'item_ids' => $group['items'],
            ]);
        }

        app(Notifier::class)->offboardingChecklistStarted($case);
    }
}
