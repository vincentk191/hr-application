<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Support\ReportingLine;
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
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $national_id
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relation
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_holder
 * @property string|null $tax_number
 * @property string|null $payment_method
 * @property string|null $pay_group
 * @property int|null $base_salary_idr
 * @property int|null $allowances_idr
 * @property int|null $overtime_rate_idr
 * @property string|null $insurance_number
 * @property string|null $insurance_tier
 * @property string|null $blood_type
 * @property int|null $height_cm
 * @property int|null $weight_kg
 * @property Carbon|null $last_medical_check_on
 * @property string|null $declared_conditions
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
    // Contact + emergency contact (EMP-DET-002)
    'email',
    'phone',
    'address',
    'national_id',
    'emergency_contact_name',
    'emergency_contact_phone',
    'emergency_contact_relation',
    // Bank and tax (EMP-DET-003)
    'bank_name',
    'bank_account_number',
    'bank_account_holder',
    'tax_number',
    'payment_method',
    // Payroll (EMP-DET-004) — integer rupiah
    'pay_group',
    'base_salary_idr',
    'allowances_idr',
    'overtime_rate_idr',
    // Health and insurance (EMP-DET-005)
    'insurance_number',
    'insurance_tier',
    'blood_type',
    'height_cm',
    'weight_kg',
    'last_medical_check_on',
    'declared_conditions',
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
            'last_medical_check_on' => 'date',
            'base_salary_idr' => 'integer',
            'allowances_idr' => 'integer',
            'overtime_rate_idr' => 'integer',
            'height_cm' => 'integer',
            'weight_kg' => 'integer',
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
     * Every employee below this one in the reporting line, at any depth (CORE-RBA-004).
     *
     * @return list<int>
     */
    public function downlineIds(): array
    {
        return app(ReportingLine::class)->downlineIds($this);
    }

    /**
     * Limit a query to this employee's downline.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function inDownlineOf(Builder $query, self $manager): void
    {
        $query->whereIn('id', app(ReportingLine::class)->downlineIds($manager));
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
                'email',
                'phone',
                'address',
                'national_id',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relation',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'tax_number',
                'payment_method',
                'pay_group',
                'base_salary_idr',
                'allowances_idr',
                'overtime_rate_idr',
                'insurance_number',
                'insurance_tier',
                'blood_type',
                'height_cm',
                'weight_kg',
                'last_medical_check_on',
                'declared_conditions',
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
