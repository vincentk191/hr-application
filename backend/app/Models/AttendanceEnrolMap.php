<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Which employee a scanner enrol-ID (PIN) belongs to, on which device (ATT-DEV-005).
 *
 * The optional dates matter because scanners reuse PINs: when someone leaves and their number is
 * given to a new hire, the old row is closed with a `valid_to` and a new one opened, so old
 * punches stay with the person who made them.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string $device_sn
 * @property string $pin
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'employee_id', 'device_sn', 'pin', 'valid_from', 'valid_to'])]
class AttendanceEnrolMap extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
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
