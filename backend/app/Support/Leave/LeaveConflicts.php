<?php

namespace App\Support\Leave;

use App\Enums\LeaveRequestStage;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\Attendance\WorkingDays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Double booking: the person's requests, waiting or approved, that a new one would land on top of.
 *
 * Two requests clash when they share a working day and, if both name a time of day, those times overlap. A rejected request
 * asks for nothing, so it never clashes, and neither does a stretch they share that is only a weekend or a holiday. A request
 * for whole days clashes with any other on the day, whatever its hours.
 */
class LeaveConflicts
{
    public function __construct(private WorkingDays $workingDays) {}

    /**
     * The existing requests the given one would double-book.
     *
     * @param  string|null  $startTime  "09:00" when the new request names a time of day
     * @param  int|null  $exceptId  a request to leave out: the one being corrected and sent again
     * @return Collection<int, LeaveRequest>
     */
    public function for(Employee $employee, CarbonInterface $startOn, CarbonInterface $endOn, ?string $startTime = null, ?string $endTime = null, ?int $exceptId = null): Collection
    {
        return LeaveRequest::acrossCompanies()
            ->where('employee_id', $employee->id)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereHas('workflow', fn ($query) => $query->whereIn('current_stage', [LeaveRequestStage::Submitted->value, LeaveRequestStage::Approved->value]))
            ->whereDate('start_on', '<=', $endOn->toDateString())
            ->whereDate('end_on', '>=', $startOn->toDateString())
            ->with(['type', 'workflow'])
            ->orderBy('start_on')
            ->get()
            ->filter(fn (LeaveRequest $existing): bool => $this->hoursClash($existing, $startTime, $endTime) && $this->sharesWorkingDay($existing, $employee, $startOn, $endOn))
            ->values();
    }

    /**
     * One clash as the form and the error say it.
     *
     * @return array{id: int, reference: string, type: string, dates: string, timeframe: string|null, status: string, statusLabel: string}
     */
    public function describe(LeaveRequest $leave): array
    {
        $stage = LeaveRequestStage::from((string) $leave->workflow?->current_stage);

        return [
            'id' => $leave->id,
            'reference' => $leave->reference(),
            'type' => $leave->typeLabel(),
            'dates' => $this->dates($leave->start_on, $leave->end_on),
            'timeframe' => $leave->timeframe(),
            'status' => $stage->value,
            'statusLabel' => $stage === LeaveRequestStage::Submitted ? 'menunggu persetujuan' : 'disetujui',
        ];
    }

    /**
     * The sentence that refuses a request because of the clashes, naming the first.
     *
     * @param  Collection<int, LeaveRequest>  $clashes
     */
    public function message(Collection $clashes): string
    {
        $first = $this->describe($clashes->firstOrFail());
        $detail = $first['dates'].($first['timeframe'] !== null ? ' '.$first['timeframe'] : '');
        $more = $clashes->count() > 1 ? ' dan '.($clashes->count() - 1).' lainnya' : '';

        return "Bentrok dengan {$first['reference']}{$more} ({$first['type']}, {$detail}, {$first['statusLabel']}). Pilih tanggal atau jam lain.";
    }

    /**
     * Two requests that name hours clash only if the hours overlap; if either is for whole days, they clash.
     */
    private function hoursClash(LeaveRequest $existing, ?string $startTime, ?string $endTime): bool
    {
        if ($existing->start_time === null || $existing->end_time === null || $startTime === null || $endTime === null) {
            return true;
        }

        return $startTime < $existing->end_time && $existing->start_time < $endTime;
    }

    /**
     * Whether the days the two share include a working day for this employee.
     */
    private function sharesWorkingDay(LeaveRequest $existing, Employee $employee, CarbonInterface $startOn, CarbonInterface $endOn): bool
    {
        $from = CarbonImmutable::instance($existing->start_on)->max(CarbonImmutable::instance($startOn))->startOfDay();
        $to = CarbonImmutable::instance($existing->end_on)->min(CarbonImmutable::instance($endOn))->startOfDay();

        return $this->workingDays->between($from, $to, $employee->company_id, $employee->department_id, $employee->branch_id) > 0;
    }

    /**
     * "5 Nov 2026", "5–6 Nov 2026" or "30 Okt – 2 Nov 2026".
     */
    private function dates(CarbonInterface $start, CarbonInterface $end): string
    {
        $from = CarbonImmutable::instance($start)->settings(['locale' => 'id']);
        $to = CarbonImmutable::instance($end)->settings(['locale' => 'id']);

        return match (true) {
            $from->isSameDay($to) => $from->translatedFormat('j M Y'),
            $from->isSameMonth($to) => $from->translatedFormat('j').'–'.$to->translatedFormat('j M Y'),
            default => $from->translatedFormat('j M').' – '.$to->translatedFormat('j M Y'),
        };
    }
}
