<?php

namespace App\Models;

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
 * OFF-TPL-002). The list belongs to the whole group, not to a company: a department such as IT can serve
 * every company.
 *
 * Who owns an item on a case: the person named here, if there is one and they can act; otherwise the
 * supervisor of the department it names, in the leaver's company; otherwise HR Admin. The person's job
 * title and department are read from their own record.
 *
 * @property int $id
 * @property string $name
 * @property OffboardingItemKind $kind
 * @property string|null $department_name the department whose supervisor owns it when no one is named
 * @property int|null $owner_employee_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'kind', 'department_name', 'owner_employee_id', 'is_active', 'sort_order'])]
class OffboardingItem extends Model
{
    /** @use HasFactory<OffboardingItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => OffboardingItemKind::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * The person named as owner, from any company.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_employee_id')->withoutGlobalScopes();
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
