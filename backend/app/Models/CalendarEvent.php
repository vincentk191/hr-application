<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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
 * `appliesTo()` says whether it covers a given company, department and Cabang, and who may see or
 * change an event is decided by the controller and the form request.
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $department_id
 * @property int|null $branch_id
 * @property string $title
 * @property string $kind
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $series_id
 * @property string|null $repeat
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'department_id', 'branch_id', 'title', 'kind', 'starts_on', 'ends_on', 'series_id', 'repeat', 'created_by_user_id'])]
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

    /** How an event can repeat (ATT-WIZ-002), with the label the form shows. */
    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const REPEATS = [
        self::WEEKLY => 'Setiap minggu',
        self::MONTHLY => 'Setiap bulan',
        self::YEARLY => 'Setiap tahun',
    ];

    /**
     * No series may be longer than this many occurrences, so a repeat with a far-off end date cannot
     * write thousands of rows.
     */
    public const MAX_OCCURRENCES = 120;

    /**
     * The days an event occupies each time it happens, from the first one up to the end date of the
     * repeat. Each occurrence keeps the length of the first. Counted from the first occurrence every
     * time, so "the 31st, monthly" lands on the last day of a short month and goes back to the 31st
     * afterwards instead of drifting.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function occurrences(CarbonImmutable $starts, CarbonImmutable $ends, ?string $repeat, ?CarbonImmutable $until): array
    {
        if ($repeat === null || $until === null) {
            return [[$starts, $ends]];
        }

        $length = (int) $starts->diffInDays($ends);
        $found = [];

        for ($i = 0; $i <= self::MAX_OCCURRENCES; $i++) {
            $start = match ($repeat) {
                self::WEEKLY => $starts->addWeeks($i),
                self::MONTHLY => $starts->addMonthsNoOverflow($i),
                default => $starts->addYearsNoOverflow($i),
            };

            if ($start > $until) {
                break;
            }

            $found[] = [$start, $start->addDays($length)];
        }

        return $found;
    }

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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScopes();
    }

    /**
     * Whether this event covers someone in the given company, department and Cabang. Each of the
     * three narrows it only when the event names one; an event naming a Cabang does not cover
     * someone whose Cabang is not known.
     */
    public function appliesTo(?int $companyId, ?int $departmentId, ?int $branchId): bool
    {
        if ($this->company_id !== null && $this->company_id !== $companyId) {
            return false;
        }

        if ($this->department_id !== null && $this->department_id !== $departmentId) {
            return false;
        }

        return $this->branch_id === null || $this->branch_id === $branchId;
    }

    /**
     * Holidays and changes to them are rare and affect everyone's attendance, so each is logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'department_id', 'branch_id', 'title', 'kind', 'starts_on', 'ends_on', 'repeat'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('calendar');
    }
}
