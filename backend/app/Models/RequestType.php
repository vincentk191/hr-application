<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A kind of request (jenis pengajuan): "Cuti sakit", "Cuti tahunan", whatever the administrators add.
 *
 * Deliberately not company-tagged: the same kinds apply across the group. Requests refer to a type by
 * its `code`, which is made once from the first label and never changes, so renaming a type never
 * breaks the requests already filed under it.
 *
 * @property int $id
 * @property string $code
 * @property string $label
 * @property int|null $max_days
 * @property int|null $max_back_days
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'label', 'max_days', 'max_back_days', 'is_active', 'sort_order'])]
class RequestType extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (self $type): void {
            if (blank($type->code)) {
                $type->code = self::uniqueCodeFor($type->label);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_days' => 'integer',
            'max_back_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * A code from a label ("Izin keluar" becomes "izin-keluar"), with a number added if it is taken.
     */
    public static function uniqueCodeFor(string $label): string
    {
        $base = Str::slug($label) ?: 'pengajuan';
        $code = $base;
        $n = 2;

        while (self::query()->where('code', $code)->exists()) {
            $code = "{$base}-{$n}";
            $n++;
        }

        return $code;
    }

    /**
     * Whether any request, in any company, was filed under this type. A type in use is retired, not
     * deleted, so those requests keep their label.
     */
    public function isInUse(): bool
    {
        return LeaveRequest::acrossCompanies()->where('leave_type', $this->code)->exists();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('label');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'label', 'max_days', 'max_back_days', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('request-types');
    }
}
