<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One request the sync agent made, and what came of it (ATT-DEV-004). Written for every request
 * that reaches the endpoint, successful or not, so a feed that has gone quiet or started failing
 * can be diagnosed from here.
 *
 * @property int $id
 * @property int|null $attendance_agent_id
 * @property string $status
 * @property int $rows
 * @property int $accepted
 * @property int $duplicates
 * @property int $unmapped
 * @property string|null $message
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
#[Fillable(['attendance_agent_id', 'status', 'rows', 'accepted', 'duplicates', 'unmapped', 'message', 'ip', 'created_at'])]
class AttendanceIngestRequest extends Model
{
    public const OK = 'ok';

    public const REJECTED = 'rejected';

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<AttendanceAgent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(AttendanceAgent::class, 'attendance_agent_id');
    }
}
