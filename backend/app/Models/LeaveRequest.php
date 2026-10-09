<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasWorkflow;
use App\Enums\Permission;
use App\Support\Attendance\WorkingDays;
use Carbon\CarbonInterface;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $leave_type the code of its RequestType
 * @property Carbon $start_on
 * @property Carbon $end_on
 * @property string|null $start_time
 * @property string|null $end_time
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
    'start_time',
    'end_time',
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
     * The requests this person may see: all of them for roles that can see employees (the company
     * scope still applies), otherwise their own and the ones they are asked to approve.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ViewEmployees->value)) {
            return;
        }

        $query->where(function (Builder $inner) use ($user): void {
            $inner->whereHas('employee', fn (Builder $employee) => $employee->where('user_id', $user->id))
                ->orWhereHas('workflow.steps', fn (Builder $step) => $step->where('approver_user_id', $user->id));
        });
    }

    /**
     * @return BelongsTo<RequestType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'leave_type', 'code');
    }

    /**
     * The type's name as it is now. A request keeps working if its type was removed (which only
     * happens to types nobody used), falling back to the code.
     */
    public function typeLabel(): string
    {
        $type = $this->type;

        return $type instanceof RequestType ? $type->label : $this->leave_type;
    }

    /**
     * The optional time of day, "09:00–11:00", or null for a request about whole days only.
     */
    public function timeframe(): ?string
    {
        return $this->start_time !== null && $this->end_time !== null ? "{$this->start_time}–{$this->end_time}" : null;
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
     * The working days a leave covers, both ends included (REQ-WIZ-005): Monday to Friday, less the
     * holidays and company events that apply to this employee's company and department.
     *
     * It uses the same definition of a working day as the attendance calendar, so the two cannot
     * disagree. The count is fixed when the request is filed; a holiday added afterwards does not
     * change it (the `leave:recount-days` command does, on purpose).
     */
    public static function workingDays(CarbonInterface $startOn, CarbonInterface $endOn, Employee $employee): int
    {
        return app(WorkingDays::class)->between($startOn, $endOn, $employee->company_id, $employee->department_id);
    }
}
