<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One rekapitulasi file from a Cabang without a scanner: the counts for a period, per employee.
 * A draft until HR has checked the preview and confirmed it; a confirmed one is replaced when a
 * newer file for the same Cabang and dates is confirmed.
 *
 * @property int $id
 * @property int $company_id
 * @property int $branch_id
 * @property int|null $user_id
 * @property string|null $original_name
 * @property string $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string|null $period_label the file's own "Periode" text, e.g. "OKTOBER 2026" or "SETAHUN"
 * @property string|null $file_branch_name the Cabang the file says it is for
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id', 'branch_id', 'user_id', 'original_name', 'status', 'period_start', 'period_end',
    'period_label', 'file_branch_name', 'confirmed_at',
])]
class AttendanceRekap extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SUPERSEDED = 'superseded';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AttendanceRekapRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(AttendanceRekapRow::class)->orderBy('row_number');
    }
}
