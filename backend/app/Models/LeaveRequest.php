<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
use App\Enums\LeaveType;
use Carbon\CarbonInterface;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A leave request (REQ-WIZ, REQ-DSH, REQ-DLG).
 *
 * Carries only what the request is about. Its stage, approval chain, comments and history all
 * live on the workflow instance, so nothing here duplicates the engine.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property LeaveType $leave_type
 * @property Carbon $start_on
 * @property Carbon $end_on
 * @property int $days
 * @property string|null $reason
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'employee_id',
    'leave_type',
    'start_on',
    'end_on',
    'days',
    'reason',
    'created_by_user_id',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use BelongsToCompany, HasFactory, HasWorkflow;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'start_on' => 'date',
            'end_on' => 'date',
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
     * @return HasMany<LeaveRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The reference shown to users, e.g. REQ-0042 in the design.
     */
    public function reference(): string
    {
        return 'REQ-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Whole days inclusive of both ends.
     *
     * Calendar days for now. The design counts *working* days, excluding weekends and the
     * holidays set on the attendance calendar (REQ-WIZ-005) — that needs ATT-WIZ, which does
     * not exist yet, so this is deliberately the simpler rule until it does.
     */
    public static function countDays(CarbonInterface $startOn, CarbonInterface $endOn): int
    {
        return (int) $startOn->startOfDay()->diffInDays($endOn->startOfDay()) + 1;
    }
}
