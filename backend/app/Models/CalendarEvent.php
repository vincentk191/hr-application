<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A holiday or company event: a day, or a run of days, on which attendance is not checked
 * (ATT-WIZ-001).
 *
 * Not company-scoped by the usual trait, because a group-wide holiday has no company. Instead
 * `appliesTo()` says whether it covers a given company and department, and who may see or change
 * an event is decided by the controller and the form request.
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $department_id
 * @property string $title
 * @property string $kind
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'department_id', 'title', 'kind', 'starts_on', 'ends_on', 'created_by_user_id'])]
class CalendarEvent extends Model
{
    use LogsActivity;

    public const HOLIDAY = 'holiday';

    public const EVENT = 'event';

    /**
     * No single event may cover more than this many days: it would be a typo, and it is also the
     * bound on how much the attendance derivation expands each event.
     */
    public const MAX_DAYS = 31;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withoutGlobalScopes();
    }

    /**
     * Whether this event covers someone in the given company and department.
     */
    public function appliesTo(?int $companyId, ?int $departmentId): bool
    {
        if ($this->company_id !== null && $this->company_id !== $companyId) {
            return false;
        }

        return $this->department_id === null || $this->department_id === $departmentId;
    }

    /**
     * Holidays and changes to them are rare and affect everyone's attendance, so each is logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'department_id', 'title', 'kind', 'starts_on', 'ends_on'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('calendar');
    }
}
