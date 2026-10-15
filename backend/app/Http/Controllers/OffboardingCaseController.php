<?php

namespace App\Http\Controllers;

use App\Actions\Offboarding\DecideOffboardingCase;
use App\Actions\Offboarding\SaveOffboardingDraft;
use App\Actions\Offboarding\SubmitOffboardingCase;
use App\Enums\EmploymentStatus;
use App\Enums\OffboardingCaseStage;
use App\Enums\OffboardingExitType;
use App\Enums\OffboardingFileKind;
use App\Enums\Role;
use App\Http\Requests\DecideOffboardingCaseRequest;
use App\Http\Requests\SaveOffboardingCaseRequest;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseFile;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Offboarding\OffboardingFiles;
use App\Support\Offboarding\OffboardingPresenter;
use App\Support\OrganisationNames;
use App\Support\ReportingLine;
use App\Support\Workflow\WorkflowDiscussion;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Offboarding: HR starts a case, Management approves it, and the checklist and sign-off follow
 * (OFF-DSH, OFF-WIZ, OFF-PIP, OFF-APR).
 */
class OffboardingCaseController extends Controller
{
    public function __construct(private OffboardingPresenter $presenter) {}

    /**
     * The dashboard (OFF-DSH): the cases this person may see, by stage.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OffboardingCase::class);

        $user = $request->user();
        $filters = $this->filters($request);

        $visible = fn (): Builder => OffboardingCase::query()->visibleTo($user);
        $with = ['employee.position:id,name', 'employee.department:id,name', 'creator:id,name', 'workflow'];

        $cases = $visible()->with($with)
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(in_array($filters['status'], ['draft', 'submitted', 'checklist', 'sign_off', 'closed'], true),
                fn (Builder $query) => $query->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', $filters['status'])))
            ->when($filters['department'] !== null, fn (Builder $query) => $query->whereHas('employee.department', fn (Builder $department) => $department->where('name', $filters['department'])))
            ->when($filters['lastDay'] !== null, fn (Builder $query) => $this->lastDayRange($query, $filters['lastDay']))
            ->latest('id')
            ->get();

        $open = $visible()->with('workflow')->open()->get();
        $oldest = $open->filter(fn (OffboardingCase $case): bool => $case->created_at !== null)->map(fn (OffboardingCase $case): int => (int) $case->created_at->diffInDays(now()))->max();

        return Inertia::render('offboarding/index', [
            'cases' => $cases->map(fn (OffboardingCase $case): array => $this->presenter->row($case))->values(),
            'filters' => $filters,
            'summary' => [
                'open' => $open->count(),
                'perStage' => collect(OffboardingCaseStage::cases())
                    ->filter(fn (OffboardingCaseStage $stage): bool => $stage !== OffboardingCaseStage::Closed)
                    ->mapWithKeys(fn (OffboardingCaseStage $stage): array => [$stage->value => $open->filter(fn (OffboardingCase $case): bool => $case->stage() === $stage)->count()])
                    ->all(),
                'oldestDays' => $oldest,
            ],
            'departments' => OrganisationNames::departments($user),
            'canCreate' => $user->can('create', OffboardingCase::class),
        ]);
    }

    /**
     * @return array{status: string, department: string|null, lastDay: string|null}
     */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $lastDay = $request->string('last_day')->toString();

        return [
            'status' => in_array($status, ['open', 'draft', 'submitted', 'checklist', 'sign_off', 'closed', 'all'], true) ? $status : 'open',
            'department' => trim($request->string('department')->toString()) ?: null,
            'lastDay' => in_array($lastDay, ['week', 'month', 'quarter'], true) ? $lastDay : null,
        ];
    }

    /**
     * @param  Builder<OffboardingCase>  $query
     */
    private function lastDayRange(Builder $query, string $range): void
    {
        $today = CarbonImmutable::now((string) config('attendance.device_timezone'))->startOfDay();
        $end = match ($range) {
            'week' => $today->endOfWeek(),
            'month' => $today->endOfMonth(),
            default => $today->addMonths(3),
        };

        $query->whereBetween('last_working_on', [$today->toDateString(), $end->toDateString()]);
    }

    /**
     * The wizard for a new case (OFF-WIZ).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', OffboardingCase::class);

        return Inertia::render('offboarding/form', [
            'case' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(SaveOffboardingCaseRequest $request, SaveOffboardingDraft $save, SubmitOffboardingCase $submit): RedirectResponse
    {
        $case = $save->handle(null, $request->employee(), $request->caseFields(), $request->uploads(), [], $request->user());

        if ($request->boolean('send')) {
            return $this->send($request, $case, $submit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Draft {$case->reference()} disimpan."]);

        return to_route('offboarding.edit', $case);
    }

    /**
     * Send what was just saved for approval. A case that is not complete stays a saved draft, and the
     * wizard reopens on it with what is missing, so nothing typed is lost.
     */
    private function send(Request $request, OffboardingCase $case, SubmitOffboardingCase $submit): RedirectResponse
    {
        try {
            $submit->handle($case, $request->user());
        } catch (ValidationException $e) {
            return to_route('offboarding.edit', $case)->withErrors($e->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$case->reference()} dikirim untuk persetujuan Manajemen."]);

        return to_route('offboarding.show', $case);
    }

    /**
     * Finish a draft (OFF-WIZ-002), which is also where a rejected case comes back to be corrected.
     */
    public function edit(Request $request, OffboardingCase $offboardingCase): Response|RedirectResponse
    {
        $this->authorize('view', $offboardingCase);

        if (! $request->user()->can('update', $offboardingCase)) {
            return to_route('offboarding.show', $offboardingCase);
        }

        return Inertia::render('offboarding/form', [
            'case' => $this->presenter->detail($offboardingCase, $request->user()),
            // A case sent back by Management is corrected here, so the reason is a click away.
            'discussion' => fn (): array => $this->discussion($offboardingCase, $request->user()),
            ...$this->formOptions($request),
        ]);
    }

    public function update(SaveOffboardingCaseRequest $request, OffboardingCase $offboardingCase, SaveOffboardingDraft $save, SubmitOffboardingCase $submit): RedirectResponse
    {
        $save->handle(
            $offboardingCase,
            null,
            $request->caseFields(),
            $request->uploads(),
            array_map('intval', (array) $request->input('remove_files', [])),
            $request->user(),
        );

        if ($request->boolean('send')) {
            return $this->send($request, $offboardingCase, $submit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft disimpan.']);

        return to_route('offboarding.edit', $offboardingCase);
    }

    public function destroy(Request $request, OffboardingCase $offboardingCase, OffboardingFiles $files): RedirectResponse
    {
        $this->authorize('delete', $offboardingCase);

        $files->remove($offboardingCase, $offboardingCase->files()->pluck('id')->all());
        $offboardingCase->workflow?->delete();
        $offboardingCase->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft dihapus.']);

        return to_route('offboarding.index');
    }

    /**
     * "Kirim untuk persetujuan": check the case is complete and send it to Management (OFF-WZ2-003).
     */
    public function submit(Request $request, OffboardingCase $offboardingCase, SubmitOffboardingCase $submit): RedirectResponse
    {
        $this->authorize('update', $offboardingCase);

        $submit->handle($offboardingCase, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offboardingCase->reference()} dikirim untuk persetujuan Manajemen."]);

        return to_route('offboarding.show', $offboardingCase);
    }

    /**
     * The case on its current stage (OFF-PIP). A draft opens the wizard instead (OFF-DSH-008).
     */
    public function show(Request $request, OffboardingCase $offboardingCase): Response|RedirectResponse
    {
        $this->authorize('view', $offboardingCase);

        if ($offboardingCase->isDraft() && $request->user()->can('update', $offboardingCase)) {
            return to_route('offboarding.edit', $offboardingCase);
        }

        return Inertia::render('offboarding/show', [
            'case' => $this->presenter->detail($offboardingCase, $request->user()),
            // Its own prop, so the drawer can poll for new comments without reloading the whole case.
            'discussion' => fn (): array => $this->discussion($offboardingCase, $request->user()),
        ]);
    }

    public function decide(DecideOffboardingCaseRequest $request, OffboardingCase $offboardingCase, DecideOffboardingCase $decide): RedirectResponse
    {
        if ($request->isApproval()) {
            $decide->approve($offboardingCase, $request->user(), $request->input('note'));

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$offboardingCase->reference()} disetujui. Checklist dimulai."]);
        } else {
            $decide->reject($offboardingCase, $request->user(), (string) $request->input('note'));

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$offboardingCase->reference()} ditolak dan dikembalikan ke HR."]);
        }

        return back();
    }

    /**
     * "Komentar & feedback": the thread between the requester and Management (OFF-APR-005).
     */
    public function comment(Request $request, OffboardingCase $offboardingCase, WorkflowEngine $engine, Notifier $notifier): RedirectResponse
    {
        $this->authorize('comment', $offboardingCase);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Tulis komentar.']);

        $comment = $engine->comment($offboardingCase->workflow, $request->user(), $validated['body']);
        $notifier->offboardingCommented($offboardingCase, $comment, $request->user());
        app(WorkflowDiscussion::class)->markRead($offboardingCase->workflow, $request->user());

        return back();
    }

    /**
     * The person opened the discussion: everything written so far counts as seen.
     */
    public function markCommentsRead(Request $request, OffboardingCase $offboardingCase): RedirectResponse
    {
        $this->authorize('view', $offboardingCase);

        app(WorkflowDiscussion::class)->markRead($offboardingCase->workflow, $request->user());

        return back();
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread: int, unreadByStage: array<string, int>}
     */
    private function discussion(OffboardingCase $case, User $viewer): array
    {
        $case->loadMissing('employee');
        $leaverManagerChain = fn (User $author): bool => ($own = Employee::acrossCompanies()->where('user_id', $author->id)->first()) !== null
            && app(ReportingLine::class)->manages($own, $case->employee);

        return app(WorkflowDiscussion::class)->present(
            $case->workflow,
            $viewer,
            collect(OffboardingCaseStage::cases())->mapWithKeys(fn (OffboardingCaseStage $stage): array => [$stage->value => $stage->label()])->all(),
            fn (User $author): ?string => match (true) {
                $author->id === $case->created_by_user_id => 'PEMOHON',
                $author->isA(Role::Management) => 'MANAJEMEN',
                $leaverManagerChain($author) => 'ATASAN',
                default => null,
            },
        );
    }

    /**
     * More files on a case that is already running (OFF-PIP-006).
     */
    public function addFiles(SaveOffboardingCaseRequest $request, OffboardingCase $offboardingCase, OffboardingFiles $files): RedirectResponse
    {
        foreach ($request->uploads() as $upload) {
            $files->store($offboardingCase, $upload['kind'], $upload['files'], $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berkas ditambahkan.']);

        return back();
    }

    public function downloadFile(OffboardingCaseFile $file): StreamedResponse
    {
        $this->authorize('view', $file->offboardingCase);

        abort_unless(Storage::disk(OffboardingFiles::DISK)->exists($file->path), 404);

        return Storage::disk(OffboardingFiles::DISK)->download($file->path, $file->original_name);
    }

    /**
     * What the wizard offers: the people who can be offboarded, the exit types and the kinds of file.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $user = $request->user();

        return [
            'employees' => Employee::query()
                ->where('employment_status', EmploymentStatus::Active)
                ->whereDoesntHave('user', fn (Builder $account) => $account->whereKey($user->id))
                ->whereNotIn('id', OffboardingCase::query()->open()->select('employee_id'))
                ->with(['position:id,name', 'department:id,name', 'manager:id,full_name'])
                ->orderBy('full_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'number' => $employee->employee_number,
                    'position' => $employee->position?->name,
                    'department' => $employee->department?->name,
                    'manager' => $employee->manager?->full_name,
                    'joinedOn' => $employee->joined_on?->toDateString(),
                    'noticeDays' => $employee->notice_days,
                ])->values(),
            'exitTypes' => array_map(fn (OffboardingExitType $type): array => ['value' => $type->value, 'label' => $type->label()], OffboardingExitType::cases()),
            'fileKinds' => array_map(fn (OffboardingFileKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], OffboardingFileKind::cases()),
        ];
    }
}
