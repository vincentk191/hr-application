<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
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
 * @property int|null $employee_id
 * @property int|null $proposed_by_user_id
 * @property Carbon|null $proposed_at
 * @property Carbon|null $sent_at
 * @property int|null $sent_by_user_id
 * @property Carbon|null $emailed_at
 * @property Carbon|null $responded_at
 * @property string|null $decline_reason
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
    'employee_id',
    'proposed_by_user_id',
    'proposed_at',
    'sent_at',
    'sent_by_user_id',
    'emailed_at',
    'responded_at',
    'decline_reason',
])]
class CandidateOffer extends Model
{
    /** @use HasFactory<CandidateOfferFactory> */
    use BelongsToCompany, HasFactory, HasWorkflow;

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
            'responded_at' => 'datetime',
        ];
    }

    /**
     * The terms the letter needs are all there: the salary from Management, and the start date and probation from HR.
     */
    public function hasTerms(): bool
    {
        return $this->salary !== null && $this->start_on !== null && $this->probation_months !== null;
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
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
