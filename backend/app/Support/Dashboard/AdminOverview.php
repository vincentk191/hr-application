<?php

namespace App\Support\Dashboard;

use App\Enums\LeaveRequestStage;
use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Models\CalendarEvent;
use App\Models\Candidate;
use App\Models\LeaveRequest;
use App\Models\OffboardingCase;
use App\Models\ProfileChangeRequest;
use App\Models\Requisition;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Attendance\WorkSchedule;
use App\Support\CompanyContext;
use App\Support\Recruitment\InvitationMessage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The cards the dashboard shows the people who run HR, on top of their own overview: what is waiting for their
 * decision, a month calendar of leave, interviews and holidays, and how recruitment is going.
 *
 * Each is drawn through the same scopes as the page it links to (the leave queue, the requisition list, the attendance
 * calendar's events), so a number here is the number there.
 */
class AdminOverview
{
    /** How many interviews and lapsed links each list shows before pointing at the full page. */
    public const LIST_LIMIT = 5;

    /** The stages a running requisition can be at, in pipeline order. */
    private const OPEN_STAGES = [
        RequisitionStage::Submitted,
        RequisitionStage::Posting,
        RequisitionStage::Screening,
        RequisitionStage::Interview,
        RequisitionStage::Offer,
    ];

    /**
     * Who gets the calendar, and the decisions card even when it is empty: the people who run the HR process, HR and
     * Management. A System Admin sees everyone too, but runs accounts and setup, not leave or hiring.
     */
    public static function seesCalendar(User $user): bool
    {
        return $user->can(Permission::ManageRecruitment->value) || $user->isA(Role::Management);
    }

    /**
     * What waits on this person's decision, by kind. Null when nothing does and they are not HR or Management, so a
     * supervisor sees it only when something is waiting, and most employees never.
     *
     * Each kind also lists its first few items, the ones that have waited longest, so they can be opened from here; the
     * count is of all of them.
     *
     * @return array{total: int, items: list<array{key: string, label: string, count: int, entries: list<array{id: int, title: string, chip: string|null, detail: string|null, subdetail: string|null, waiting: string, href: string}>}>}|null
     */
    public function waiting(User $user): ?array
    {
        $items = [
            $this->stepItem('leave', 'Pengajuan', WorkflowStep::waitingOn($user, LeaveRequest::class), fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'title' => (string) $leave->employee?->full_name,
                'chip' => $leave->typeLabel(),
                'detail' => $leave->days.' hari',
                'subdetail' => self::dateRange($leave->start_on, $leave->end_on),
                'href' => route('leave-requests.index', ['status' => 'waiting', 'open' => $leave->id], absolute: false),
            ]),
            $this->stepItem('requisition', 'Lowongan', WorkflowStep::waitingOn($user, Requisition::class), fn (Requisition $requisition): array => [
                'id' => $requisition->id,
                'title' => $requisition->position_name,
                'chip' => $requisition->reference(),
                'detail' => $requisition->department?->name,
                'subdetail' => null,
                'href' => route('recruitment.show', $requisition->id, absolute: false),
            ]),
            $this->stepItem('offboarding', 'Offboarding', WorkflowStep::waitingOn($user, OffboardingCase::class), fn (OffboardingCase $case): array => [
                'id' => $case->id,
                'title' => (string) $case->employee?->full_name,
                'chip' => $case->exit_type?->label() ?? $case->reference(),
                'detail' => $case->last_working_on === null ? null : self::dateRange($case->last_working_on, $case->last_working_on),
                'subdetail' => $case->last_working_on === null ? null : 'hari terakhir',
                'href' => route('offboarding.show', $case, absolute: false),
            ]),
            $this->profileItem($user),
        ];

        $total = array_sum(array_column($items, 'count'));

        if ($total === 0 && ! self::seesCalendar($user)) {
            return null;
        }

        return ['total' => $total, 'items' => $items];
    }

    /**
     * One kind of waiting item that is a workflow step: how many there are, and the first few with the subject they
     * are about, longest waiting first.
     *
     * @param  Builder<WorkflowStep>  $steps
     * @param  Closure(Model): array<string, mixed>  $entry  the subject as one line of the list
     * @return array<string, mixed>
     */
    private function stepItem(string $key, string $label, Builder $steps, Closure $entry): array
    {
        $count = (clone $steps)->count();

        $entries = $steps->with('instance.subject')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->filter(fn (WorkflowStep $step): bool => $step->instance?->subject !== null)
            ->map(fn (WorkflowStep $step): array => [...$entry($step->instance->subject), 'waiting' => self::waitingFor($step->updated_at)])
            ->values()
            ->all();

        return ['key' => $key, 'label' => $label, 'count' => $count, 'entries' => $entries];
    }

    /**
     * The data changes waiting on this person. As the menu badge counts them: someone's change to their own record is
     * decided by another HR Admin. They are not workflow steps, so they wait from the day they were asked for.
     *
     * @return array<string, mixed>
     */
    private function profileItem(User $user): array
    {
        $item = ['key' => 'profile', 'label' => 'Perubahan data', 'count' => 0, 'entries' => []];

        if (! $user->can('viewAny', ProfileChangeRequest::class)) {
            return $item;
        }

        $changes = ProfileChangeRequest::query()->pending()
            ->whereHas('employee', fn (Builder $employee) => $employee->where('user_id', '!=', $user->id)->orWhereNull('user_id'));

        $item['count'] = (clone $changes)->count();
        $item['entries'] = $changes->with('employee:id,full_name')
            ->oldest()
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (ProfileChangeRequest $change): array => [
                'id' => $change->id,
                'title' => (string) $change->employee?->full_name,
                'chip' => $change->reference(),
                'detail' => count($change->changes).' data',
                'subdetail' => null,
                'waiting' => self::waitingFor($change->created_at),
                'href' => route('profile-changes.index', absolute: false),
            ])
            ->all();

        return $item;
    }

    /**
     * How long something has waited, as "4 hari 20 jam", "3 jam 53 mnt" or "22 mnt".
     */
    public static function waitingFor(CarbonInterface $since): string
    {
        $minutes = max(0, intdiv(now()->getTimestamp() - $since->getTimestamp(), 60));
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $rest = $minutes % 60;

        return match (true) {
            $days > 0 => $days.' hari'.($hours > 0 ? ' '.$hours.' jam' : ''),
            $hours > 0 => $hours.' jam'.($rest > 0 ? ' '.$rest.' mnt' : ''),
            default => max(1, $rest).' mnt',
        };
    }

    /**
     * "5 Nov", "5–6 Nov" or "30 Okt – 2 Nov" for the days from `$start` to `$end`.
     */
    private static function dateRange(CarbonInterface $start, CarbonInterface $end): string
    {
        $from = CarbonImmutable::instance($start)->locale('id');
        $to = CarbonImmutable::instance($end)->locale('id');

        return match (true) {
            $from->isSameDay($to) => $from->translatedFormat('j M'),
            $from->isSameMonth($to) => $from->translatedFormat('j').'–'.$to->translatedFormat('j M'),
            default => $from->translatedFormat('j M').' – '.$to->translatedFormat('j M'),
        };
    }

    /**
     * One month of what HR plans around: leave (waiting and approved, on working days), interviews, and the holidays
     * and company events this person may see. Each entry is one person, or one event, on one date.
     *
     * @return array{month: string, label: string, prev: string, next: string, entries: list<array<string, mixed>>}
     */
    public function calendar(User $user, ?string $asked = null): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $month = $asked !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $asked) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $asked.'-01', $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfMonth();
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();

        $entries = [
            ...$this->events($user, $from, $to),
            ...$this->leaveEntries($user, $from, $to),
            ...($user->can(Permission::ManageRecruitment->value) ? $this->interviewEntries($from, $to, $timezone) : []),
        ];

        usort($entries, fn (array $a, array $b): int => [$a['date'], $a['order'], $a['time'] ?? '', $a['title']] <=> [$b['date'], $b['order'], $b['time'] ?? '', $b['title']]);

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->settings(['locale' => 'id'])->translatedFormat('F Y'),
            'prev' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'entries' => array_map(function (array $entry): array {
                unset($entry['order']);

                return $entry;
            }, $entries),
        ];
    }

    /**
     * Running requisitions by stage, the interviews coming up, and the invitation links that lapsed before the
     * candidate filled the form, so HR can send a new one. Only for HR, who see every candidate.
     *
     * @return array<string, mixed>|null
     */
    public function recruitment(User $user): ?array
    {
        if (! $user->can(Permission::ManageRecruitment->value)) {
            return null;
        }

        $open = Requisition::query()
            ->visibleTo($user)
            ->open()
            ->with('workflow')
            ->get();

        $now = now();
        $weekEnd = CarbonImmutable::now((string) config('attendance.device_timezone'))->endOfWeek()->utc();

        $upcoming = Candidate::query()
            ->inProcess()
            ->with('requisition.workflow')
            ->whereNotNull('interview_at')
            ->where('interview_at', '>=', $now)
            ->whereIn('requisition_id', $open->modelKeys())
            ->orderBy('interview_at')
            ->get();

        $lapsed = Candidate::query()
            ->inProcess()
            ->with('requisition')
            ->whereNotNull('invited_at')
            ->whereNull('form_submitted_at')
            ->where('invite_expires_at', '<', $now)
            ->whereIn('requisition_id', $open->modelKeys())
            ->orderByDesc('invite_expires_at')
            ->get();

        return [
            'open' => $open->count(),
            'stages' => array_map(fn (RequisitionStage $stage): array => [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $open->filter(fn (Requisition $requisition): bool => $requisition->stage() === $stage)->count(),
            ], self::OPEN_STAGES),
            'interviewsThisWeek' => $upcoming->filter(fn (Candidate $candidate): bool => $candidate->interview_at !== null && $candidate->interview_at->lte($weekEnd))->count(),
            'upcoming' => $upcoming->take(self::LIST_LIMIT)->map(fn (Candidate $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'when' => $candidate->interview_at === null ? null : InvitationMessage::whenLabel($candidate->interview_at),
                'requisitionId' => $candidate->requisition_id,
                'positionName' => $candidate->requisition?->position_name,
            ])->values()->all(),
            'lapsedLinks' => $lapsed->count(),
            'lapsed' => $lapsed->take(self::LIST_LIMIT)->map(fn (Candidate $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'expiredOn' => $candidate->invite_expires_at === null ? null : InvitationMessage::dayLabel($candidate->invite_expires_at, false),
                'requisitionId' => $candidate->requisition_id,
                'positionName' => $candidate->requisition?->position_name,
            ])->values()->all(),
        ];
    }

    /**
     * Holidays and company events in the month, among those this person may see: group-wide ones and those for the
     * company being viewed (or their own, if they are bound to one). One entry per date covered.
     *
     * @return list<array<string, mixed>>
     */
    private function events(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $company = app(CompanyContext::class)->companyId() ?? ($user->isParentWide() ? null : $user->company_id);
        $entries = [];

        CalendarEvent::query()
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->when($company !== null, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('company_id')->orWhere('company_id', $company),
            ))
            ->get()
            ->each(function (CalendarEvent $event) use (&$entries, $from, $to): void {
                foreach ($this->dates($event->starts_on->toDateString(), $event->ends_on->toDateString(), $from, $to) as $date) {
                    $entries[] = [
                        'date' => $date,
                        'kind' => $event->kind === 'holiday' ? 'holiday' : 'event',
                        'title' => $event->title,
                        'detail' => $event->kind === 'holiday' ? 'Hari libur' : 'Acara perusahaan',
                        'time' => null,
                        'status' => null,
                        'href' => null,
                        'order' => 0,
                    ];
                }
            });

        return $entries;
    }

    /**
     * Leave still waiting or approved, through the same visibility as the leave queue, on the working days it
     * covers (a leave over a weekend does not fill the weekend). Rejected leave asks for nothing, so it is left out.
     *
     * @return list<array<string, mixed>>
     */
    private function leaveEntries(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $schedule = WorkSchedule::current();
        $entries = [];

        LeaveRequest::query()
            ->visibleTo($user)
            ->with(['type', 'workflow', 'employee:id,full_name'])
            ->whereHas('workflow', fn (Builder $query) => $query->whereIn('current_stage', [LeaveRequestStage::Submitted->value, LeaveRequestStage::Approved->value]))
            ->whereDate('start_on', '<=', $to->toDateString())
            ->whereDate('end_on', '>=', $from->toDateString())
            ->get()
            ->each(function (LeaveRequest $leave) use (&$entries, $from, $to, $schedule): void {
                $stage = LeaveRequestStage::from((string) $leave->workflow?->current_stage);

                foreach ($this->dates($leave->start_on->toDateString(), $leave->end_on->toDateString(), $from, $to) as $date) {
                    if (! $schedule->isWorkday(CarbonImmutable::parse($date))) {
                        continue;
                    }

                    $entries[] = [
                        'date' => $date,
                        'kind' => 'leave',
                        'title' => (string) $leave->employee?->full_name,
                        'detail' => $leave->typeLabel().($leave->timeframe() !== null ? ' · '.$leave->timeframe() : ''),
                        'time' => null,
                        'status' => $stage->value,
                        'href' => route('employees.show', ['employee' => $leave->employee_id, 'tab' => 'requests'], absolute: false),
                        'order' => 2,
                    ];
                }
            });

        return $entries;
    }

    /**
     * Interviews in the month, on requisitions that are not cancelled, in company time.
     *
     * @return list<array<string, mixed>>
     */
    private function interviewEntries(CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        return array_values(Candidate::query()
            ->inProcess()
            ->with('requisition.workflow')
            ->whereBetween('interview_at', [$from->utc(), $to->endOfDay()->utc()])
            ->get()
            ->filter(fn (Candidate $candidate): bool => $candidate->requisition !== null && $candidate->requisition->stage() !== RequisitionStage::Cancelled)
            ->map(function (Candidate $candidate) use ($timezone): array {
                $local = CarbonImmutable::instance($candidate->interview_at)->setTimezone($timezone);

                return [
                    'date' => $local->toDateString(),
                    'kind' => 'interview',
                    'title' => $candidate->name,
                    'detail' => $local->format('H:i').' · '.$candidate->requisition?->position_name.' · '.($candidate->interview_mode === 'online' ? 'Daring' : 'Tatap muka'),
                    'time' => $local->format('H:i'),
                    'status' => null,
                    'href' => route('recruitment.show', $candidate->requisition_id, absolute: false),
                    'order' => 1,
                ];
            })
            ->all());
    }

    /**
     * The dates from `$start` to `$end` that fall inside the month.
     *
     * @return list<string>
     */
    private function dates(string $start, string $end, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $from->getTimezone();
        $first = CarbonImmutable::parse($start, $timezone)->max($from);
        $last = CarbonImmutable::parse($end, $timezone)->min($to);
        $dates = [];

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }
}
