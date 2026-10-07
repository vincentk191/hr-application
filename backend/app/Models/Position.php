<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job role in the org chart (CORE-ORG-002).
 *
 * Not an RBAC role: this is the job title an employee holds, and what role SOPs will attach
 * to when the Knowledge Hub is built (context.md Appendix B).
 *
 * @property int $id
 * @property int $company_id
 * @property int $department_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'department_id', 'name'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
