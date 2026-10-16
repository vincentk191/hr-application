<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\OffboardingCaseDepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One department's part of a case's checklist (OFF-CHK-002). Its owner is the department's supervisor
 * (or HR Admin when there is none): they pick the items that apply, confirm the list with "Daftar
 * selesai", and tick the items off. HR can mark a department as not involved.
 *
 * @property int $id
 * @property int $company_id
 * @property int $offboarding_case_id
 * @property int|null $department_id
 * @property string $name
 * @property int|null $owner_user_id
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by_user_id
 * @property bool $not_involved
 */
#[Fillable(['company_id', 'offboarding_case_id', 'department_id', 'name', 'owner_user_id', 'confirmed_at', 'confirmed_by_user_id', 'not_involved'])]
class OffboardingCaseDepartment extends Model
{
    /** @use HasFactory<OffboardingCaseDepartmentFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'not_involved' => 'boolean'];
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<OffboardingCaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OffboardingCaseItem::class)->orderBy('id');
    }

    /**
     * The list is settled: confirmed by its owner, or set aside as not involved.
     */
    public function isSettled(): bool
    {
        return $this->not_involved || $this->confirmed_at !== null;
    }
}
