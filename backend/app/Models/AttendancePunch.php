<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One raw scan of one finger, face or palm, already tied to an employee.
 *
 * Append-only: nothing edits or deletes a punch. Daily records, first-in/last-out and status flags
 * are derived from these elsewhere, so a correction is a new derived value, never a changed scan.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string $device_sn
 * @property string $pin
 * @property Carbon $scan_at
 * @property int|null $verify_mode
 * @property int|null $inout_mode
 * @property string $source
 * @property int|null $import_id
 * @property Carbon|null $created_at
 */
#[Fillable([
    'company_id', 'employee_id', 'device_sn', 'pin', 'scan_at', 'verify_mode', 'inout_mode',
    'source', 'import_id',
])]
class AttendancePunch extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scan_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
