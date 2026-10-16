<?php

namespace App\Actions\Offboarding;

use App\Models\Department;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingItem;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingCatalog;
use App\Support\Offboarding\OffboardingOwners;

/**
 * Opens a case's checklist once Management has approved it (OFF-ITM-007): one group per department that
 * has items on the master list, each owned by that department's supervisor, plus one for items that
 * belong to no department, which HR Admin owns. Each owner is told to pick the items that apply.
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
        $this->catalog->ensureDefaults($case->company_id);

        $departmentIds = OffboardingItem::query()->active()->get()->pluck('department_id')->unique()->values();

        foreach ($departmentIds as $departmentId) {
            $department = $departmentId === null ? null : Department::acrossCompanies()->whereKey($departmentId)->first();

            OffboardingCaseDepartment::create([
                'company_id' => $case->company_id,
                'offboarding_case_id' => $case->id,
                'department_id' => $department?->id,
                'name' => $department === null ? 'Tanpa departemen' : $department->name,
                'owner_user_id' => $this->owners->ownerOf($department, $case->employee)?->id,
            ]);
        }

        app(Notifier::class)->offboardingChecklistStarted($case);
    }
}
