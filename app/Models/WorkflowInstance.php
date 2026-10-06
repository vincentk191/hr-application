<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use App\Workflows\WorkflowRegistry;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One subject moving through one workflow (CORE-WFL-001).
 *
 * The stage is stored as its enum's backed value, and `stage()` hydrates it through the
 * definition. Keeping the raw value in the column means a module can rename a stage's label
 * without a migration, and an unknown value fails loudly rather than silently becoming null.
 *
 * @property int $id
 * @property int $company_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $definition
 * @property string $current_stage
 * @property Carbon $stage_entered_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'definition',
    'current_stage',
    'stage_entered_at',
    'completed_at',
])]
class WorkflowInstance extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage_entered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }

    /**
     * @return HasMany<WorkflowTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<WorkflowComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(WorkflowComment::class)->orderBy('created_at');
    }

    public function definition(): WorkflowDefinition
    {
        return app(WorkflowRegistry::class)->get($this->definition);
    }

    public function stage(): WorkflowStage&BackedEnum
    {
        return app(WorkflowRegistry::class)->stage($this->definition, $this->current_stage);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * How long the instance has been in its current stage (CORE-WFL-006).
     */
    public function timeInCurrentStage(): \DateInterval
    {
        return $this->stage_entered_at->diff(now());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forDefinition(Builder $query, WorkflowDefinition|string $definition): void
    {
        $query->where('definition', $definition instanceof WorkflowDefinition ? $definition->key() : $definition);
    }
}
