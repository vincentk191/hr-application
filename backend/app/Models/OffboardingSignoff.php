<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One signature in the sequence that ends an offboarding case (OFF-SIG-005): the leaver's Atasan, then HR.
 * Each stays locked until the one before it has signed.
 *
 * @property int $id
 * @property int $company_id
 * @property int $offboarding_case_id
 * @property int $position
 * @property string $label
 * @property int|null $user_id the person bound to sign it, or null for "any HR Admin"
 * @property Carbon|null $signed_at
 * @property int|null $signed_by_user_id
 * @property string|null $note
 */
#[Fillable(['company_id', 'offboarding_case_id', 'position', 'label', 'user_id', 'signed_at', 'signed_by_user_id', 'note'])]
class OffboardingSignoff extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'position' => 'integer'];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }
}
