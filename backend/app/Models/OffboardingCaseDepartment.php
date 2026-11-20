<?php

namespace App\Models;

use Database\Factories\OffboardingCaseDepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One department's part of a case's checklist (OFF-CHK-002). Its owner is the department's supervisor
 * (or HR Admin when there is none): they pick the items that apply, ask HR to lock the list ("Kunci Checklist"
 * is HR's to press), and tick the items off. A locked list is opened again only by HR, on the owner's request.
 * HR can mark a department as not involved.
 *
 * @property int $id
 * @property int $company_id
 * @property int $offboarding_case_id
 * @property int|null $department_id
 * @property string $name
 * @property int|null $owner_user_id
 * @property string|null $owner_title
 * @property string|null $owner_department
 * @property list<int>|null $item_ids the master items this group was given
 * @property Carbon|null $confirmed_at when HR locked the list
 * @property int|null $confirmed_by_user_id
 * @property Carbon|null $lock_requested_at the owner has asked HR to lock the list
 * @property Carbon|null $unlock_requested_at the owner has asked HR to open the locked list again
 * @property bool $not_involved
 */
#[Fillable(['company_id', 'offboarding_case_id', 'department_id', 'name', 'owner_user_id', 'owner_title', 'owner_department', 'item_ids', 'confirmed_at', 'confirmed_by_user_id', 'lock_requested_at', 'unlock_requested_at', 'not_involved'])]
class OffboardingCaseDepartment extends Model
{
    /**
     * Not company-scoped: a group is always reached through its case, which has been authorised, and its
     * owner may belong to another company (OFF-ITM-007).
     *
     * @use HasFactory<OffboardingCaseDepartmentFactory>
     */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'lock_requested_at' => 'datetime', 'unlock_requested_at' => 'datetime', 'not_involved' => 'boolean', 'item_ids' => 'array'];
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
     * The list is settled: locked by HR, or set aside as not involved.
     */
    public function isSettled(): bool
    {
        return $this->not_involved || $this->confirmed_at !== null;
    }

    /**
     * The owner has asked for the list to be locked and HR has not answered: nobody but HR changes it meanwhile.
     */
    public function isAwaitingLock(): bool
    {
        return ! $this->isSettled() && $this->lock_requested_at !== null;
    }

    /**
     * The list is locked and its owner has asked for it to be opened again.
     */
    public function isAwaitingUnlock(): bool
    {
        return $this->confirmed_at !== null && $this->unlock_requested_at !== null;
    }

    /**
     * The owner may still choose and add items: the list is neither locked nor waiting for HR to lock it.
     */
    public function isOpenForOwner(): bool
    {
        return ! $this->isSettled() && $this->lock_requested_at === null;
    }
}
