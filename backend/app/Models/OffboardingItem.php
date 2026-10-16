<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\OffboardingItemKind;
use Database\Factories\OffboardingItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item on the master list of what is handed back or switched off when someone leaves (OFF-ITM-001,
 * OFF-TPL-002). It belongs to a department, and that department's supervisor owns it on a case.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property OffboardingItemKind $kind
 * @property int|null $department_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'name', 'kind', 'department_id', 'is_active', 'sort_order'])]
class OffboardingItem extends Model
{
    /** @use HasFactory<OffboardingItemFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => OffboardingItemKind::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withoutGlobalScopes();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
