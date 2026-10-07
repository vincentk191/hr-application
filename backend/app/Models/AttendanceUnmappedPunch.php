<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A punch whose PIN has no employee mapping yet. Held, never dropped, and moved into the real
 * punch table as soon as HR maps the PIN. Not company-tagged: until it is mapped nobody can say
 * which company it belongs to.
 *
 * @property int $id
 * @property string $device_sn
 * @property string $pin
 * @property Carbon $scan_at
 * @property int|null $verify_mode
 * @property int|null $inout_mode
 * @property string $source
 * @property int|null $import_id
 * @property Carbon|null $created_at
 */
#[Fillable(['device_sn', 'pin', 'scan_at', 'verify_mode', 'inout_mode', 'source', 'import_id'])]
class AttendanceUnmappedPunch extends Model
{
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
}
