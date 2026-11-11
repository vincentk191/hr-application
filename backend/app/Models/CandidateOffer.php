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
 * @property int|null $proposed_by_user_id
 * @property Carbon|null $proposed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'requisition_id',
    'candidate_id',
    'salary',
    'proposed_by_user_id',
    'proposed_at',
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
            'proposed_at' => 'datetime',
        ];
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
        $query->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', array_map(fn (OfferStage $stage): string => $stage->value, OfferStage::live())));
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
     * @return BelongsTo<User, $this>
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }
}
