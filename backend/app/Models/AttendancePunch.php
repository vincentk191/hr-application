<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $reason
 * @property int|null $created_by_user_id
 * @property Carbon|null $voided_at
 * @property int|null $voided_by_user_id
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 */
#[Fillable([
    'company_id', 'employee_id', 'device_sn', 'pin', 'scan_at', 'verify_mode', 'inout_mode',
    'source', 'import_id', 'reason', 'created_by_user_id', 'voided_at', 'voided_by_user_id', 'void_reason',
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
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Whether a person added this punch by hand rather than the scanner recording it.
     */
    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    /**
     * Punches that still count. A voided punch is kept for the record but ignored by everything
     * derived from the log.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function counted(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
