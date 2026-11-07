<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\CandidateSource;
use Carbon\CarbonImmutable;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone HR shortlisted for a requisition (REC-SCR). Applicants who were not shortlisted are never stored, so a
 * row here is a person HR chose to take further. The CV, when given, is on the private disk.
 *
 * @property int $id
 * @property int $company_id
 * @property int $requisition_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $phone_key
 * @property string|null $email
 * @property CandidateSource $source
 * @property string|null $source_name
 * @property string|null $note
 * @property string|null $cv_path
 * @property string|null $cv_name
 * @property int|null $cv_size
 * @property int|null $added_by_user_id
 * @property string|null $invite_token_hash
 * @property Carbon|null $invited_at
 * @property Carbon|null $invite_expires_at
 * @property int|null $invited_by_user_id
 * @property Carbon|null $interview_at
 * @property string|null $interview_mode
 * @property string|null $interview_place
 * @property string|null $invitation_subject
 * @property string|null $invitation_message
 * @property bool $invitation_emailed
 * @property Carbon|null $candidate_reminded_at
 * @property Carbon|null $interviewers_reminded_at
 * @property Carbon|null $form_submitted_at
 * @property array<string, mixed>|null $form_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'requisition_id',
    'name',
    'phone',
    'phone_key',
    'email',
    'source',
    'source_name',
    'note',
    'cv_path',
    'cv_name',
    'cv_size',
    'added_by_user_id',
    'invite_token_hash',
    'invited_at',
    'invite_expires_at',
    'invited_by_user_id',
    'interview_at',
    'interview_mode',
    'interview_place',
    'invitation_subject',
    'invitation_message',
    'invitation_emailed',
    'candidate_reminded_at',
    'interviewers_reminded_at',
    'form_submitted_at',
    'form_data',
])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use BelongsToCompany, HasFactory;

    /** The private disk and folder the CVs are kept on. */
    public const CV_DISK = 'local';

    public const CV_DIRECTORY = 'candidate-cvs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CandidateSource::class,
            'cv_size' => 'integer',
            'invited_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'interview_at' => 'datetime',
            'form_submitted_at' => 'datetime',
            'invitation_emailed' => 'boolean',
            'candidate_reminded_at' => 'datetime',
            'interviewers_reminded_at' => 'datetime',
            'form_data' => 'array',
        ];
    }

    /**
     * Where the invitation stands: not invited, invited and waiting for the form, the link has lapsed, or the form was
     * filled in.
     *
     * @return 'none'|'invited'|'expired'|'submitted'
     */
    public function inviteState(): string
    {
        return match (true) {
            $this->form_submitted_at !== null => 'submitted',
            $this->invited_at === null => 'none',
            $this->invite_expires_at === null || $this->invite_expires_at->isPast() => 'expired',
            default => 'invited',
        };
    }

    /**
     * The form opens on the morning of the interview day (company time) and not before.
     */
    public function formOpensAt(): ?CarbonImmutable
    {
        return $this->interview_at === null
            ? null
            : CarbonImmutable::instance($this->interview_at)->setTimezone((string) config('attendance.device_timezone'))->startOfDay();
    }

    /**
     * What is kept of an invitation link: its hash, so a copy of the database holds no working link.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * What the interviewers wrote after the interview (REC-INT), oldest first.
     *
     * @return HasMany<CandidateEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CandidateEvaluation::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * The source as the pages say it: its name, or the one HR typed for "Lainnya".
     */
    public function sourceLabel(): string
    {
        return $this->source === CandidateSource::Other
            ? ($this->source_name ?: $this->source->label())
            : $this->source->label();
    }

    /**
     * A phone number as bare digits in 62… form, so "0812-3456-7890", "+62 812 3456 7890" and "62812…" are one number.
     * Null when it is empty or cannot be a phone number (fewer than 8 or more than 15 digits).
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        $key = match (true) {
            $digits === '' => null,
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };

        return $key !== null && strlen($key) >= 8 && strlen($key) <= 15 ? $key : null;
    }
}
