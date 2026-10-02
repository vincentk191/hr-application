<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A department within one company (CORE-ORG-001).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int|null $supervisor_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'name', 'supervisor_employee_id'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }
}
