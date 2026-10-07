<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A decision that an attendance issue is acceptable (ATT-ISS-004): excused, by someone, for a
 * reason. The issue itself is never stored; see the migration for why.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property Carbon $date
 * @property string $type
 * @property string $resolution
 * @property string $reason
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'employee_id', 'date', 'type', 'resolution', 'reason', 'resolved_by_user_id'])]
class AttendanceIssueResolution extends Model
{
    use BelongsToCompany;

    public const EXCUSED = 'excused';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
