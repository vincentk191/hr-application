<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\DocumentCategory;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A document filed on an employee's dossier (EMP-DOC).
 *
 * The document is the stable record — what it is, whether it is restricted, when it lapses —
 * and each uploaded file is a version of it, so replacing a file never loses the old one.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property DocumentCategory $category
 * @property string $title
 * @property bool $is_restricted
 * @property Carbon|null $expires_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['company_id', 'employee_id', 'category', 'title', 'is_restricted', 'expires_on'])]
class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * Days before expiry at which a document starts to count as expiring (EMP-DOC-002).
     */
    public const EXPIRING_WITHIN_DAYS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'is_restricted' => 'boolean',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<EmployeeDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(EmployeeDocumentVersion::class)->orderByDesc('version_no');
    }

    /**
     * @return HasOne<EmployeeDocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(EmployeeDocumentVersion::class)->ofMany('version_no', 'max');
    }

    /**
     * 'expired', 'expiring' (within the warning window), or null when there is nothing to flag.
     */
    public function expiryStatus(?CarbonInterface $today = null): ?string
    {
        if ($this->expires_on === null) {
            return null;
        }

        $today ??= now();

        if ($this->expires_on->startOfDay()->lt($today->startOfDay())) {
            return 'expired';
        }

        return $this->expires_on->startOfDay()->lte($today->startOfDay()->addDays(self::EXPIRING_WITHIN_DAYS))
            ? 'expiring'
            : null;
    }

    /**
     * Documents already past their date or inside the warning window.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function needingAttention(Builder $query): void
    {
        $query->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', now()->addDays(self::EXPIRING_WITHIN_DAYS));
    }

    /**
     * Who filed, re-filed or changed what is on the trail. The file itself is never logged, only
     * the metadata that decides access and lifetime.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['employee_id', 'category', 'title', 'is_restricted', 'expires_on'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('documents');
    }
}
