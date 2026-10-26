<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One load of scanner punches into the dashboard, and what became of each row. Not
 * company-tagged: a scanner is a group-level device, and so is its log.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $source
 * @property string|null $original_name
 * @property string $device_sn
 * @property int $rows_total
 * @property int $accepted
 * @property int $duplicates
 * @property int $unmapped
 * @property int $rejected
 * @property int $auto_mapped
 * @property Carbon|null $first_scan_at
 * @property Carbon|null $last_scan_at
 * @property list<string>|null $errors
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'source', 'original_name', 'device_sn', 'rows_total', 'accepted', 'duplicates',
    'unmapped', 'rejected', 'auto_mapped', 'first_scan_at', 'last_scan_at', 'errors',
])]
class AttendanceImport extends Model
{
    /**
     * Where a load of punches came from.
     */
    public const SOURCE_FILE = 'manual-file';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_scan_at' => 'datetime',
            'last_scan_at' => 'datetime',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
