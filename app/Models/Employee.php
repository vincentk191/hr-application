<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An employee dossier record (EMP-DIR, EMP-ADD).
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $employee_number
 * @property string $full_name
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $manager_id
 * @property EmploymentStatus $employment_status
 * @property EmploymentType|null $employment_type
 * @property Carbon|null $joined_on
 * @property Carbon|null $exited_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'user_id',
    'employee_number',
    'full_name',
    'department_id',
    'position_id',
    'manager_id',
    'employment_status',
    'employment_type',
    'joined_on',
    'exited_on',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    protected $attributes = [
        'employment_status' => EmploymentStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'employment_type' => EmploymentType::class,
            'joined_on' => 'date',
            'exited_on' => 'date',
        ];
    }

    /**
     * The login this dossier record belongs to, when it has one. Warehouse staff are
     * data-only records with no account (CORE-RBA-006).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * The direct supervisor, and the first approver on anything this employee raises
     * (CORE-ORG-003).
     *
     * @return BelongsTo<self, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Records who changed which profile field, and when (EMP-PRF-003).
     *
     * Only the fields HR actually edits are logged, and only when they change, so the trail
     * stays readable. `company_id` is included deliberately: a record moving company is the
     * kind of change someone would want to be able to account for.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'company_id',
                'employee_number',
                'full_name',
                'department_id',
                'position_id',
                'manager_id',
                'employment_status',
                'employment_type',
                'joined_on',
                'exited_on',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('employment_status', EmploymentStatus::Active);
    }
}
