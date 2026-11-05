<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\AttendanceSource;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\Zona;
use App\Support\Access\Entrance;
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
 * @property string|null $photo_path
 * @property int $branch_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $manager_id
 * @property EmploymentStatus $employment_status
 * @property EmploymentType|null $employment_type
 * @property int|null $notice_days
 * @property Zona $zona sets the rate of attendance deductions; Hijau unless HR says otherwise
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
 * @property Gender|null $gender
 * @property string|null $birth_place
 * @property Carbon|null $birth_date
 * @property Religion|null $religion
 * @property MaritalStatus|null $marital_status
 * @property string|null $education_level
 * @property string|null $job_title the sheet's Jabatan (Staff, Supervisor ...); `position_id` is its Posisi
 * @property string|null $residence_village
 * @property string|null $residence_district
 * @property string|null $residence_city
 * @property string|null $residence_province
 * @property string|null $residence_postal_code
 * @property string|null $id_address
 * @property string|null $id_block
 * @property string|null $id_house_number
 * @property string|null $id_rt
 * @property string|null $id_rw
 * @property string|null $id_village
 * @property string|null $id_district
 * @property string|null $id_city
 * @property string|null $id_province
 * @property string|null $id_postal_code
 * @property string|null $family_card_number
 * @property string|null $mother_name
 * @property PtkpStatus|null $ptkp_status
 * @property string|null $bank_branch
 * @property string|null $bpjs_employment_number
 * @property string|null $bpjs_health_number
 * @property string|null $employee_insurance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'user_id',
    'employee_number',
    'full_name',
    'photo_path',
    'branch_id',
    'department_id',
    'position_id',
    'manager_id',
    'employment_status',
    'employment_type',
    'notice_days',
    'zona',
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
    // Personal, home and KTP address (the data sheet)
    ...self::PERSONAL_FIELDS,
    ...self::RESIDENCE_FIELDS,
    ...self::ID_ADDRESS_FIELDS,
    ...self::IDENTITY_FIELDS,
    ...self::COVERAGE_FIELDS,
    'bank_branch',
    'ptkp_status',
])]
class Employee extends Model
{
    /** @var list<string> */
    public const PERSONAL_FIELDS = [
        'gender', 'birth_place', 'birth_date', 'religion', 'marital_status', 'education_level', 'job_title',
    ];

    /** The rest of the home address; `address` holds its street. @var list<string> */
    public const RESIDENCE_FIELDS = [
        'residence_village', 'residence_district', 'residence_city', 'residence_province', 'residence_postal_code',
    ];

    /** @var list<string> */
    public const ID_ADDRESS_FIELDS = [
        'id_address', 'id_block', 'id_house_number', 'id_rt', 'id_rw', 'id_village', 'id_district', 'id_city',
        'id_province', 'id_postal_code',
    ];

    /** Who the person is to the state and their family: sensitive. @var list<string> */
    public const IDENTITY_FIELDS = ['family_card_number', 'mother_name'];

    /**
     * The "Kesehatan & asuransi" section: the two BPJS numbers and nothing else, since the rest of a person's cover is
     * on the BPJS site. Sensitive.
     *
     * @var list<string>
     */
    public const COVERAGE_FIELDS = ['bpjs_employment_number', 'bpjs_health_number'];

    /**
     * The bank and payroll fields (EMP-DET-003/004), and the identity numbers, tax status and BPJS numbers that
     * came with the data sheet. Seeing or changing them needs the sensitive-data permission on top of access to the
     * record (EMP-DET-006).
     *
     * @var list<string>
     */
    public const SENSITIVE_FIELDS = [
        'bank_name', 'bank_account_number', 'bank_account_holder', 'bank_branch', 'tax_number', 'ptkp_status',
        'payment_method',
        'pay_group', 'base_salary_idr', 'allowances_idr', 'overtime_rate_idr',
        'family_card_number', 'mother_name',
        'bpjs_employment_number', 'bpjs_health_number',
    ];

    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    protected $attributes = [
        'employment_status' => EmploymentStatus::Active->value,
        'zona' => Zona::Hijau->value,
    ];

    /**
     * The name on a person's login is the one on their employee record: a change to the record (an HR edit, an
     * approved change request, an import) carries over to the account. Nobody edits the account's name itself.
     */
    protected static function booted(): void
    {
        static::updated(function (self $employee): void {
            if ($employee->user_id !== null && $employee->wasChanged('full_name')) {
                User::query()->whereKey($employee->user_id)->update(['name' => $employee->full_name]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'employment_type' => EmploymentType::class,
            'zona' => Zona::class,
            'joined_on' => 'date',
            'exited_on' => 'date',
            'gender' => Gender::class,
            'religion' => Religion::class,
            'marital_status' => MaritalStatus::class,
            'ptkp_status' => PtkpStatus::class,
            'birth_date' => 'date',
            'base_salary_idr' => 'integer',
            'allowances_idr' => 'integer',
            'overtime_rate_idr' => 'integer',
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
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * The office this employee works from.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    /**
     * The public URL of the profile picture, or null when the record has none.
     */
    /** The private disk profile pictures are kept on (CORE-ACC-008). */
    public const PHOTO_DISK = 'local';

    public function photoUrl(): ?string
    {
        // Pictures are private (CORE-ACC-008): served by an authorised route on the private entrance only, so
        // the public entrance shows initials.
        if ($this->photo_path === null || app(Entrance::class)->isPublic()) {
            return null;
        }

        return route('employees.photo', $this, absolute: false).'?v='.substr(md5($this->photo_path), 0, 8);
    }

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Whether this person's Cabang sends its attendance as a rekapitulasi. They have no scanner to
     * record daily check-ins, so there is no per-day attendance to show for them.
     */
    public function attendsViaRekap(): bool
    {
        return $this->branch?->attendance_source === AttendanceSource::Rekap;
    }

    /**
     * What the directory says about the account behind this record (EMP-DIR-008): active only once
     * the employee has registered it (or an administrator made one) and it is switched on; a record
     * nobody has claimed yet, and warehouse staff who never will, read "Belum terdaftar".
     */
    public function accountLabel(): string
    {
        if ($this->user_id === null) {
            return 'Belum terdaftar';
        }

        if ($this->user?->awaiting_activation === true) {
            return 'Menunggu aktivasi';
        }

        return $this->user?->is_active === false ? 'Dinonaktifkan' : 'Aktif';
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
     * Limit a query to the people this user may see: their own downline and themselves, unless
     * they hold the company-wide permission (CORE-RBA-004, CORE-RBA-008).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $ids = app(ReportingLine::class)->visibleIds($user);

        if ($ids !== null) {
            $query->whereIn($query->getModel()->qualifyColumn('id'), $ids);
        }
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
                'branch_id',
                'department_id',
                'position_id',
                'manager_id',
                'employment_status',
                'employment_type',
                'notice_days',
                'zona',
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
                ...self::PERSONAL_FIELDS,
                ...self::RESIDENCE_FIELDS,
                ...self::ID_ADDRESS_FIELDS,
                ...self::IDENTITY_FIELDS,
                ...self::COVERAGE_FIELDS,
                'bank_branch',
                'ptkp_status',
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
