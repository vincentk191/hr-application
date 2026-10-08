<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * The on-site sync agent (ATT-DEV-002): a named holder of API tokens, not a person.
 *
 * Its tokens carry one ability, posting punches, so a stolen agent token can add scans and nothing
 * else. Creating, rotating and revoking them is done from the admin panel.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $sync_requested_at
 * @property string|null $last_ip
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'last_seen_at', 'last_heartbeat_at', 'sync_requested_at', 'last_ip'])]
class AttendanceAgent extends Model
{
    use HasApiTokens;

    /**
     * The only thing an agent token may do.
     */
    public const ABILITY = 'attendance:ingest';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'sync_requested_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AttendanceIngestRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(AttendanceIngestRequest::class);
    }

    /**
     * Issue a new token, revoking every earlier one: rotation, not accumulation. The plain text is
     * returned once and is not stored anywhere readable.
     */
    public function issueToken(): string
    {
        $this->tokens()->delete();

        return $this->createToken('sync-agent', [self::ABILITY])->plainTextToken;
    }

    protected static function booted(): void
    {
        // A deleted agent must not leave a working token behind.
        static::deleting(fn (self $agent) => $agent->tokens()->delete());
    }
}
