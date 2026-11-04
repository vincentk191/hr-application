<?php

namespace App\Models;

use App\Enums\JobPlatform;
use Database\Factories\RequisitionPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A listing HR put up on one job platform for a requisition (REC-PST): where, when, and the link to it. A record
 * only; nothing is sent to the platform.
 *
 * @property int $id
 * @property int $requisition_id
 * @property JobPlatform $platform
 * @property string|null $platform_name
 * @property string|null $url
 * @property Carbon $posted_on
 * @property int|null $posted_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requisition_id', 'platform', 'platform_name', 'url', 'posted_on', 'posted_by_user_id'])]
class RequisitionPosting extends Model
{
    /** @use HasFactory<RequisitionPostingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => JobPlatform::class,
            'posted_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /**
     * The platform as the pages say it: its name, or the one HR typed for "Lainnya".
     */
    public function platformLabel(): string
    {
        return $this->platform === JobPlatform::Other
            ? ($this->platform_name ?: $this->platform->label())
            : $this->platform->label();
    }
}
