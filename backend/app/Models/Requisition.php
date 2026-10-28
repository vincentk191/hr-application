<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
use App\Enums\EmploymentType;
use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\Scopes\CompanyScope;
use Database\Factories\RequisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request to hire for a role (REC-WIZ, REC-PIP): raised by a supervisor or HR, approved by Management and
 * then HR. Carries what is being asked for; its stage, approval chain and comments live on the workflow
 * instance.
 *
 * @property int $id
 * @property int $company_id
 * @property int $department_id
 * @property int|null $branch_id
 * @property int|null $created_by_user_id
 * @property string $position_name
 * @property int|null $quota
 * @property string|null $reason
 * @property Carbon|null $target_start_on
 * @property EmploymentType|null $employment_type
 * @property int|null $contract_months
 * @property int|null $manager_employee_id
 * @property string|null $about
 * @property list<array{title: string, items: list<string>}>|null $responsibilities
 * @property list<string>|null $benefits
 * @property array<string, list<string>>|null $checklist
 * @property int $draft_step
 * @property int|null $salary_min
 * @property int|null $salary_max
 * @property int|null $salary_set_by_user_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'department_id',
    'branch_id',
    'created_by_user_id',
    'position_name',
    'quota',
    'reason',
    'target_start_on',
    'employment_type',
    'contract_months',
    'manager_employee_id',
    'about',
    'responsibilities',
    'benefits',
    'checklist',
    'draft_step',
    'salary_min',
    'salary_max',
    'salary_set_by_user_id',
    'submitted_at',
])]
class Requisition extends Model
{
    /** @use HasFactory<RequisitionFactory> */
    use BelongsToCompany, HasFactory, HasWorkflow;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota' => 'integer',
            'target_start_on' => 'date',
            'employment_type' => EmploymentType::class,
            'contract_months' => 'integer',
            'responsibilities' => 'array',
            'benefits' => 'array',
            'checklist' => 'array',
            'draft_step' => 'integer',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The person the new hire will report to (Atasan langsung), read across companies like an offboarding case's
     * employee: the policy and the request decide who may see or choose them.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id')->withoutGlobalScope(CompanyScope::class);
    }

    public function reference(): string
    {
        return 'REQ-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * The contract as the pages say it: "Tetap", or "Kontrak 1 tahun" and "Magang 6 bulan" where a length applies.
     */
    public function contractLabel(): ?string
    {
        if ($this->employment_type === null) {
            return null;
        }

        $months = $this->contract_months;

        if ($months === null || ! self::typeNeedsMonths($this->employment_type)) {
            return $this->employment_type->label();
        }

        $length = $months % 12 === 0 ? ($months / 12).' tahun' : "{$months} bulan";

        return "{$this->employment_type->label()} {$length}";
    }

    /**
     * Whether a contract of this type has a length in months: a fixed-term contract and an internship do.
     */
    public static function typeNeedsMonths(EmploymentType $type): bool
    {
        return $type === EmploymentType::Contract || $type === EmploymentType::Intern;
    }

    public function stage(): RequisitionStage
    {
        $workflow = $this->workflow;

        return RequisitionStage::from($workflow instanceof WorkflowInstance ? $workflow->current_stage : RequisitionStage::Draft->value);
    }

    public function isDraft(): bool
    {
        return $this->stage() === RequisitionStage::Draft;
    }

    /**
     * Management has said what the role may pay (REC-APR-008).
     */
    public function hasSalaryExpectation(): bool
    {
        return $this->salary_min !== null && $this->salary_max !== null;
    }

    /**
     * Requisitions still running: sent, and neither filled nor cancelled.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereHas('workflow', fn (Builder $workflow) => $workflow->whereNotIn('current_stage', [
            RequisitionStage::Draft->value, RequisitionStage::Filled->value, RequisitionStage::Cancelled->value,
        ]));
    }

    /**
     * The requisitions this person may see (CORE-RBA-004). A draft is its requester's alone. Once sent: HR sees
     * every one in the company, Management every one too (they approve them), and a department's head those for
     * their department. The requester always sees their own.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $sent = fn (Builder $inner) => $inner->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', '!=', RequisitionStage::Draft->value));
        $own = Employee::acrossCompanies()->where('user_id', $user->id)->value('id');
        $headed = $own === null ? [] : Department::query()->where('supervisor_employee_id', $own)->pluck('id')->all();
        $seesEverySent = $user->can(Permission::ManageRecruitment->value) || $user->isA(Role::Management);

        $query->where(function (Builder $inner) use ($user, $sent, $headed, $seesEverySent): void {
            $inner->where('created_by_user_id', $user->id);

            if ($seesEverySent) {
                $inner->orWhere(fn (Builder $all) => $sent($all));
            } elseif ($headed !== []) {
                $inner->orWhere(fn (Builder $mine) => $sent($mine->whereIn('department_id', $headed)));
            }
        });
    }
}
