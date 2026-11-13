<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\GoogleConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The Google account a company's interview invitations come from (REC-INT): HR connected it once, and the app creates
 * the interviews as events on its calendar, with a Meet link, so Google sends the invitations from that address.
 *
 * @property int $id
 * @property int $company_id
 * @property string $google_email
 * @property string $refresh_token
 * @property string|null $access_token
 * @property Carbon|null $access_token_expires_at
 * @property int|null $connected_by_user_id
 * @property Carbon $connected_at
 * @property Carbon|null $needs_reconnect_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'google_email',
    'refresh_token',
    'access_token',
    'access_token_expires_at',
    'connected_by_user_id',
    'connected_at',
    'needs_reconnect_at',
    'last_synced_at',
])]
class GoogleConnection extends Model
{
    /** @use HasFactory<GoogleConnectionFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'needs_reconnect_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Whether invitations can be made through this account now: Google has not refused it.
     */
    public function isUsable(): bool
    {
        return $this->needs_reconnect_at === null;
    }

    /**
     * Google refused the refresh token, so nothing can be done until HR connects again.
     *
     * @return bool whether this is the first time it was noticed, so the people to tell are told once
     */
    public function markNeedsReconnect(): bool
    {
        if ($this->needs_reconnect_at !== null) {
            return false;
        }

        $this->forceFill(['needs_reconnect_at' => now(), 'access_token' => null, 'access_token_expires_at' => null])->save();

        return true;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }
}
