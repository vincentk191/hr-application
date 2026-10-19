<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
use App\Enums\OffboardingCaseStage;
use App\Enums\OffboardingExitType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Scopes\CompanyScope;
use App\Support\ReportingLine;
use Database\Factories\OffboardingCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * One employee leaving the company (OFF-WIZ, OFF-PIP). Carries what the case is about; its stage,
 * approval chain and comments live on the workflow instance.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int|null $created_by_user_id
 * @property OffboardingExitType|null $exit_type
 * @property Carbon|null $notice_on
 * @property Carbon|null $last_working_on
 * @property string|null $reason
 * @property int $compensation_months
 * @property string|null $compensation_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $closed_at
 * @property string|null $exit_interview_reason
 * @property bool|null $exit_interview_rehire
 * @property string|null $exit_interview_notes
 * @property string|null $exit_interview_skipped_reason
 * @property Carbon|null $exit_interview_at
 * @property Carbon|null $exit_applied_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by_user_id
 * @property Carbon|null $checklist_approved_at
 * @property int|null $checklist_approved_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'employee_id',
    'created_by_user_id',
    'exit_type',
    'notice_on',
    'last_working_on',
    'reason',
    'compensation_months',
    'compensation_reason',
    'submitted_at',
    'closed_at',
    'exit_interview_reason',
    'exit_interview_rehire',
    'exit_interview_notes',
    'exit_interview_skipped_reason',
    'exit_interview_at',
    'exit_applied_at',
    'cancelled_at',
    'cancel_reason',
    'cancelled_by_user_id',
    'checklist_approved_at',
    'checklist_approved_by_user_id',
])]
class OffboardingCase extends Model
{
    /** @use HasFactory<OffboardingCaseFactory> */
    use BelongsToCompany, HasFactory, HasWorkflow;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exit_type' => OffboardingExitType::class,
            'notice_on' => 'date',
            'last_working_on' => 'date',
            'compensation_months' => 'integer',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'exit_interview_rehire' => 'boolean',
            'exit_interview_at' => 'datetime',
            'exit_applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checklist_approved_at' => 'datetime',
        ];
    }

    /**
     * The person leaving. Read across companies, because the owner of a checklist item may belong to another
     * company than the leaver and still has to see who it is (OFF-ITM-007); who may reach the case at all is
     * the policy's decision.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * The case's workflow, read across companies for the same reason.
     *
     * @return MorphOne<WorkflowInstance, $this>
     */
    public function workflow(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'subject')->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<OffboardingCaseFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(OffboardingCaseFile::class)->orderBy('id');
    }

    /**
     * @return HasMany<OffboardingCaseDepartment, $this>
     */
    public function checklistDepartments(): HasMany
    {
        return $this->hasMany(OffboardingCaseDepartment::class)->orderBy('id');
    }

    /**
     * @return HasMany<OffboardingCaseItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(OffboardingCaseItem::class)->orderBy('id');
    }

    public function reference(): string
    {
        return 'OFB-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function stage(): OffboardingCaseStage
    {
        $workflow = $this->workflow;

        return OffboardingCaseStage::from($workflow instanceof WorkflowInstance ? $workflow->current_stage : OffboardingCaseStage::Draft->value);
    }

    /**
     * The exit has been applied (the account switched off, requests cleared, scanner mapping ended), which
     * is the point after which the case can no longer be cancelled or its last working day changed.
     */
    public function exitApplied(): bool
    {
        return $this->exit_applied_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->stage() === OffboardingCaseStage::Draft;
    }

    /**
     * Whole days between the notice being given and the last working day, or null until both are set.
     */
    public function noticeDays(): ?int
    {
        if ($this->notice_on === null || $this->last_working_on === null) {
            return null;
        }

        return (int) $this->notice_on->diffInDays($this->last_working_on, absolute: false);
    }

    /**
     * The notice period check (OFF-WZ1-004): what was given against what the contract asks for.
     *
     * @return array{given: int|null, required: int|null, short: bool}
     */
    public function noticeCheck(?Employee $employee = null): array
    {
        $employee ??= $this->employee;
        $given = $this->noticeDays();
        $required = $employee?->notice_days;

        return ['given' => $given, 'required' => $required, 'short' => $given !== null && $required !== null && $given < $required];
    }

    /**
     * Cases that are not closed yet.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereHas('workflow', fn (Builder $workflow) => $workflow->whereNotIn('current_stage', [OffboardingCaseStage::Closed->value, OffboardingCaseStage::Cancelled->value]));
    }

    /**
     * Cases in which this person owns part of the checklist, in any company (OFF-ITM-007). They see only what
     * they need to cross their items off, never the case itself.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereHas('checklistDepartments', fn (Builder $group) => $group->where('owner_user_id', $user->id))
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', ['checklist', 'sign_off', 'closed']));
    }

    /**
     * How many open cases this person may see or owns part of: the badge on "Offboarding" (OFF-DSH-010).
     */
    public static function openCountFor(User $user): int
    {
        $seen = static::query()->visibleTo($user)->open()->pluck('id');
        $owned = static::query()->acrossCompanies()->ownedBy($user)->open()->pluck('id');

        return $seen->merge($owned)->unique()->count();
    }

    /**
     * The cases this person may see (CORE-RBA-004). HR sees all of them, drafts included. Management
     * and supervisors see those that were submitted: Management every one, a supervisor those of people
     * below them. The person leaving sees their own once the checklist stage has begun.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ManageOffboarding->value)) {
            return;
        }

        $submitted = ['submitted', 'checklist', 'sign_off', 'closed', 'cancelled'];
        $running = ['checklist', 'sign_off', 'closed'];
        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        $query->where(function (Builder $inner) use ($user, $own, $submitted, $running): void {
            if ($user->isA(Role::Management)) {
                $inner->orWhereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', $submitted));
            }

            if ($own !== null) {
                $below = app(ReportingLine::class)->downlineIds($own);

                $inner->orWhere(fn (Builder $mine) => $mine->whereIn('employee_id', $below)
                    ->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', $submitted)));

                $inner->orWhere(fn (Builder $self) => $self->where('employee_id', $own->id)
                    ->whereHas('workflow', fn (Builder $workflow) => $workflow->whereIn('current_stage', $running)));
            }

            // No clause above applies to someone with no reach: match nothing rather than everything.
            $inner->orWhereRaw('1 = 0');
        });
    }
}
