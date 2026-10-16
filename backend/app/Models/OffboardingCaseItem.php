<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\OffboardingItemKind;
use Database\Factories\OffboardingCaseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item on one case's checklist: picked from the master list, or added for this case only. Ticked
 * by its owner when done, with a note on the condition or what was handed over (OFF-CHK-003).
 *
 * @property int $id
 * @property int $company_id
 * @property int $offboarding_case_id
 * @property int $offboarding_case_department_id
 * @property int|null $offboarding_item_id
 * @property string $name
 * @property OffboardingItemKind $kind
 * @property bool $is_adhoc
 * @property Carbon|null $done_at
 * @property int|null $done_by_user_id
 * @property string|null $note
 * @property int|null $added_by_user_id
 */
#[Fillable(['company_id', 'offboarding_case_id', 'offboarding_case_department_id', 'offboarding_item_id', 'name', 'kind', 'is_adhoc', 'done_at', 'done_by_user_id', 'note', 'added_by_user_id'])]
class OffboardingCaseItem extends Model
{
    /** @use HasFactory<OffboardingCaseItemFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => OffboardingItemKind::class, 'is_adhoc' => 'boolean', 'done_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OffboardingCaseDepartment, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OffboardingCaseDepartment::class, 'offboarding_case_department_id');
    }

    /**
     * @return BelongsTo<OffboardingCase, $this>
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
