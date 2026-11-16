<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\EmailKind;
use App\Enums\EmailStatus;
use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An email the app sends on a company's behalf, kept as the log and as the outbox (email automation): the text as it goes out,
 * when it is to be sent, and what came of it. Made by CompanyMailer, which is also what sends it.
 *
 * @property int $id
 * @property int $company_id
 * @property EmailKind $kind
 * @property string $to_email
 * @property string|null $to_name
 * @property string $subject
 * @property string $body
 * @property list<array{name: string, mime: string, path?: string, data?: string}>|null $attachments
 * @property EmailStatus $status
 * @property Carbon $send_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property string|null $error
 * @property int $attempts
 * @property string|null $related_type
 * @property int|null $related_id
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'kind',
    'to_email',
    'to_name',
    'subject',
    'body',
    'attachments',
    'status',
    'send_at',
    'sent_at',
    'failed_at',
    'error',
    'attempts',
    'related_type',
    'related_id',
    'created_by_user_id',
])]
class EmailMessage extends Model
{
    /** @use HasFactory<EmailMessageFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EmailKind::class,
            'status' => EmailStatus::class,
            'attachments' => 'array',
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * What the email is about (an offer, a candidate), when it is about something.
     *
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
