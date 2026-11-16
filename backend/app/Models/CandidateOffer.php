<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
use App\Enums\EmailKind;
use App\Enums\OfferStage;
use Database\Factories\CandidateOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An offer to a candidate the direct superior chose (REC-OFS): HR proposes it, Management approves it and sets the
 * starting salary. Its stage and approval chain live on its workflow instance, so this holds only what it is about.
 *
 * @property int $id
 * @property int $company_id
 * @property int $requisition_id
 * @property int $candidate_id
 * @property int|null $salary
 * @property Carbon|null $start_on
 * @property int|null $probation_months
 * @property string|null $letter_number
 * @property string|null $letter_note
 * @property array<string, mixed>|null $letter_snapshot
 * @property string|null $letter_pdf_path the letter as a PDF, made when the offer was sent
 * @property int|null $employee_id
 * @property int|null $proposed_by_user_id
 * @property Carbon|null $proposed_at
 * @property Carbon|null $sent_at
 * @property int|null $sent_by_user_id
 * @property Carbon|null $emailed_at
 * @property int|null $email_message_id the email that carried the letter
 * @property Carbon|null $responded_at
 * @property string|null $decline_reason
 * @property string|null $signed_letter_path the letter signed by the candidate and scanned, filed by HR
 * @property string|null $signed_letter_name
 * @property int|null $signed_letter_size
 * @property Carbon|null $signed_letter_at
 * @property int|null $signed_letter_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'requisition_id',
    'candidate_id',
    'salary',
    'start_on',
    'probation_months',
    'letter_number',
    'letter_note',
    'letter_snapshot',
    'letter_pdf_path',
    'employee_id',
    'proposed_by_user_id',
    'proposed_at',
    'sent_at',
    'sent_by_user_id',
    'emailed_at',
    'email_message_id',
    'responded_at',
    'decline_reason',
    'signed_letter_path',
    'signed_letter_name',
    'signed_letter_size',
    'signed_letter_at',
    'signed_letter_by_user_id',
])]
class CandidateOffer extends Model
{
    /** @use HasFactory<CandidateOfferFactory> */
    use BelongsToCompany, HasFactory, HasWorkflow;

    /** The private disk the letter PDFs and the signed scans are kept on, and the folders within it. */
    public const DISK = 'local';

    public const LETTER_DIRECTORY = 'offer-letters';

    public const SIGNED_DIRECTORY = 'offer-signed-letters';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary' => 'integer',
            'start_on' => 'date',
            'probation_months' => 'integer',
            'proposed_at' => 'datetime',
            'sent_at' => 'datetime',
            'emailed_at' => 'datetime',
            'letter_snapshot' => 'array',
            'responded_at' => 'datetime',
            'signed_letter_size' => 'integer',
            'signed_letter_at' => 'datetime',
        ];
    }

    /**
     * The terms the letter needs are all there: the salary from Management, and the start date and probation from HR.
     */
    public function hasTerms(): bool
    {
        return $this->salary !== null && $this->start_on !== null && $this->probation_months !== null;
    }

    /**
     * HR has filed the letter the candidate signed and sent back.
     */
    /**
     * The welcome email sent to the hire when the offer was accepted, the latest if it was sent again; null when none was.
     */
    public function welcomeEmail(): ?EmailMessage
    {
        return EmailMessage::acrossCompanies()
            ->where('related_type', $this->getMorphClass())
            ->where('related_id', $this->getKey())
            ->where('kind', EmailKind::OnboardingWelcome->value)
            ->latest('id')
            ->first();
    }

    public function hasSignedLetter(): bool
    {
        return $this->signed_letter_path !== null;
    }

    public function stage(): OfferStage
    {
        $workflow = $this->workflow;

        return OfferStage::from($workflow instanceof WorkflowInstance ? $workflow->current_stage : OfferStage::Proposed->value);
    }

    /**
     * Still in play: proposed, approved or out with the candidate.
     */
    public function isLive(): bool
    {
        return in_array($this->stage(), OfferStage::live(), true);
    }

    /**
     * @param  Builder<CandidateOffer>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $this->inStages($query, OfferStage::live());
    }

    /**
     * Offers the candidate has: sent or accepted.
     *
     * @param  Builder<CandidateOffer>  $query
     */
    #[Scope]
    protected function out(Builder $query): void
    {
        $this->inStages($query, OfferStage::out());
    }

    /**
     * Offers that still hold their candidate: live or accepted.
     *
     * @param  Builder<CandidateOffer>  $query
     */
    #[Scope]
    protected function standing(Builder $query): void
    {
        $this->inStages($query, OfferStage::standing());
    }

    /**
     * Offers the candidate accepted: the hires.
     *
     * @param  Builder<CandidateOffer>  $query
     */
    #[Scope]
    protected function accepted(Builder $query): void
    {
        $this->inStages($query, [OfferStage::Accepted]);
    }

    /**
     * Offers the candidate turned down.
     *
     * @param  Builder<CandidateOffer>  $query
     */
    #[Scope]
    protected function declined(Builder $query): void
    {
        $this->inStages($query, [OfferStage::Declined]);
    }

    /**
     * @param  Builder<CandidateOffer>  $query
     * @param  list<OfferStage>  $stages
     */
    private function inStages(Builder $query, array $stages): void
    {
        $query->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', array_map(fn (OfferStage $stage): string => $stage->value, $stages)));
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * The employee profile made from this hire (REC-FIL-006), once HR has converted it.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /**
     * The email that carried the letter to the candidate, for whether it went.
     *
     * @return BelongsTo<EmailMessage, $this>
     */
    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signedLetterBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_letter_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
