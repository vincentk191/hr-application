<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cabang: one of the offices a company runs, usually in its own city. Set up by the System Admin
 * when the company is created, and added to when a new office opens.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'name'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
