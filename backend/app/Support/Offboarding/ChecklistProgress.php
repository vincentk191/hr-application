<?php

namespace App\Support\Offboarding;

use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;

/**
 * Where a case's checklist stands, and what still stands in the way of sign-off (OFF-CHK-001, OFF-CHK-011).
 *
 * The checklist is complete when there is at least one department group, every group is settled
 * (its owner confirmed the list, or HR set it aside as not involved), and no chosen item is still open.
 */
class ChecklistProgress
{
    /**
     * @return array{items: int, done: int, groups: int, settled: int, ready: bool, blockers: list<string>}
     */
    public function for(OffboardingCase $case): array
    {
        $groups = OffboardingCaseDepartment::query()->where('offboarding_case_id', $case->id)->with('items')->get();

        $items = $groups->reject->not_involved->flatMap->items;
        $open = $items->reject->isDone()->count();
        $unsettled = $groups->reject->isSettled()->count();

        $blockers = [];

        if ($groups->isEmpty()) {
            $blockers[] = 'Checklist belum disiapkan.';
        }

        if ($unsettled > 0) {
            $blockers[] = "{$unsettled} departemen belum mengonfirmasi daftarnya.";
        }

        if ($open > 0) {
            $blockers[] = "{$open} item belum dicentang pemiliknya.";
        }

        return [
            'items' => $items->count(),
            'done' => $items->count() - $open,
            'groups' => $groups->count(),
            'settled' => $groups->count() - $unsettled,
            'ready' => $blockers === [],
            'blockers' => $blockers,
        ];
    }
}
