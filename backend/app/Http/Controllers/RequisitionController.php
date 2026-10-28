<?php

namespace App\Http\Controllers;

use App\Actions\Recruitment\AdvanceToPosting;
use App\Actions\Recruitment\DecideRequisition;
use App\Actions\Recruitment\SaveRequisitionDraft;
use App\Actions\Recruitment\SaveSalaryExpectation;
use App\Actions\Recruitment\SubmitRequisition;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Http\Requests\DecideRequisitionRequest;
use App\Http\Requests\SaveRequisitionRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\RequisitionDepartments;
use App\Support\Recruitment\RequisitionPresenter;
use App\Support\Recruitment\RequisitionTemplate;
use App\Support\Workflow\WorkflowDiscussion;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruitment, first slice: a supervisor or HR raises a requisition, Management and then HR approve it, and HR
 * moves it on to posting (REC-DSH, REC-WIZ, REC-PIP, REC-APR). The later stages come with their own slices.
 */
class RequisitionController extends Controller
{
    private const STATUSES = ['open', 'draft', 'submitted', 'posting', 'screening', 'interview', 'offer', 'filled', 'cancelled', 'all'];

    public function __construct(private RequisitionPresenter $presenter) {}

    /**
     * The recruitment dashboard (REC-DSH): the requisitions this person may see, in the company in view
     * (REC-DSH-009, through the company scope).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Requisition::class);

        $user = $request->user();
        $filters = $this->filters($request);
        $visible = fn (): Builder => Requisition::query()->visibleTo($user);

        $requisitions = $visible()
            ->with(['department:id,name', 'branch:id,name', 'creator:id,name', 'workflow.steps'])
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(! in_array($filters['status'], ['open', 'all'], true), fn (Builder $query) => $query->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', $filters['status'])))
            ->when($filters['department'] !== null, fn (Builder $query) => $query->whereHas('department', fn (Builder $department) => $department->where('name', $filters['department'])))
            ->latest('id')
            ->get();

        $open = $visible()->with('workflow')->open()->get();

        return Inertia::render('recruitment/index', [
            'requisitions' => $requisitions->map(fn (Requisition $requisition): array => $this->presenter->row($requisition))->values(),
            'filters' => $filters,
            'summary' => [
                'open' => $open->count(),
                'awaitingApproval' => $open->filter(fn (Requisition $requisition): bool => $requisition->stage() === RequisitionStage::Submitted)->count(),
                'quota' => (int) $open->sum('quota'),
                'drafts' => Requisition::query()->where('created_by_user_id', $user->id)
                    ->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', RequisitionStage::Draft->value))
                    ->count(),
            ],
            'departments' => Department::query()->whereIn('id', $visible()->select('department_id'))->orderBy('name')->pluck('name')->values(),
            'canCreate' => $user->can('create', Requisition::class),
        ]);
    }

    /**
     * @return array{status: string, department: string|null}
     */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'status' => in_array($status, self::STATUSES, true) ? $status : 'open',
            'department' => trim($request->string('department')->toString()) ?: null,
        ];
    }

    /**
     * The wizard for a new requisition (REC-WIZ).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Requisition::class);

        return Inertia::render('recruitment/form', [
            'requisition' => null,
            ...$this->formOptions($request->user()),
        ]);
    }

    public function store(SaveRequisitionRequest $request, SaveRequisitionDraft $save, SubmitRequisition $submit): RedirectResponse
    {
        $requisition = $save->handle(null, $request->requisitionFields(), $request->user());

        if ($request->boolean('send')) {
            return $this->send($request->user(), $requisition, $submit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Draft {$requisition->reference()} disimpan."]);

        return to_route('recruitment.index');
    }

    /**
     * Continue a draft at the step it was left on (REC-WIZ-004), or correct one already sent that nobody has
     * decided yet (REC-WIZ-005). A rejected requisition comes back here as a draft.
     */
    public function edit(Request $request, Requisition $requisition): Response|RedirectResponse
    {
        $this->authorize('view', $requisition);

        if ($request->user()->cannot('update', $requisition)) {
            return to_route('recruitment.show', $requisition);
        }

        return Inertia::render('recruitment/form', [
            'requisition' => $this->presenter->detail($requisition, $request->user()),
            // A requisition sent back is corrected here, so the reason is a click away.
            'discussion' => fn (): array => $this->discussion($requisition, $request->user()),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(SaveRequisitionRequest $request, Requisition $requisition, SaveRequisitionDraft $save, SubmitRequisition $submit): RedirectResponse
    {
        $save->handle($requisition, $request->requisitionFields(), $request->user());

        if ($request->boolean('send') && $requisition->isDraft()) {
            return $this->send($request->user(), $requisition, $submit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $requisition->isDraft() ? 'Draft disimpan.' : 'Perubahan disimpan.']);

        return $requisition->isDraft() ? to_route('recruitment.index') : to_route('recruitment.show', $requisition);
    }

    /**
     * Send what was just saved for approval. A requisition that is not complete stays a saved draft, and the
     * wizard reopens on it with what is missing, so nothing typed is lost.
     */
    private function send(User $by, Requisition $requisition, SubmitRequisition $submit): RedirectResponse
    {
        try {
            $submit->handle($requisition, $by);
        } catch (ValidationException $e) {
            return to_route('recruitment.edit', $requisition)->withErrors($e->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} diajukan untuk persetujuan."]);

        return to_route('recruitment.show', $requisition);
    }

    public function destroy(Requisition $requisition): RedirectResponse
    {
        $this->authorize('delete', $requisition);

        $requisition->workflow?->delete();
        $requisition->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft dihapus.']);

        return to_route('recruitment.index');
    }

    /**
     * The requisition on its pipeline (REC-PIP), opening on its current stage (REC-PIP-005). A draft opens the
     * wizard instead (REC-DSH-005).
     */
    public function show(Request $request, Requisition $requisition): Response|RedirectResponse
    {
        $this->authorize('view', $requisition);

        if ($requisition->isDraft() && $request->user()->can('update', $requisition)) {
            return to_route('recruitment.edit', $requisition);
        }

        return Inertia::render('recruitment/show', [
            'requisition' => $this->presenter->detail($requisition, $request->user()),
            // Its own prop, so the drawer can poll for new comments without reloading the whole requisition.
            'discussion' => fn (): array => $this->discussion($requisition, $request->user()),
        ]);
    }

    public function decide(DecideRequisitionRequest $request, Requisition $requisition, DecideRequisition $decide): RedirectResponse
    {
        if ($request->isApproval()) {
            $decide->approve(
                $requisition,
                $request->user(),
                $request->input('note'),
                $request->filled('salary_min') ? $request->integer('salary_min') : null,
                $request->filled('salary_max') ? $request->integer('salary_max') : null,
            );

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} disetujui."]);
        } else {
            $decide->reject($requisition, $request->user(), (string) $request->input('note'));

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} ditolak dan dikembalikan ke pemohon."]);
        }

        return back();
    }

    /**
     * "Simpan": Management keeps the salary expectation without approving yet (REC-APR-007).
     */
    public function salary(Request $request, Requisition $requisition, SaveSalaryExpectation $save): RedirectResponse
    {
        $this->authorize('saveSalary', $requisition);

        $validated = $request->validate(DecideRequisitionRequest::salaryRules(), DecideRequisitionRequest::salaryMessages());

        $save->handle($requisition, $request->user(), $validated['salary_min'] ?? null, $validated['salary_max'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ekspektasi gaji disimpan.']);

        return back();
    }

    /**
     * "Lanjut ke Posting ›" (REC-APR-003), refused while an approval is missing.
     */
    public function advance(Request $request, Requisition $requisition, AdvanceToPosting $advance): RedirectResponse
    {
        $this->authorize('advance', $requisition);

        try {
            $advance->handle($requisition, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['advance' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} lanjut ke tahap Posting."]);

        return back();
    }

    /**
     * "Komentar": the thread between the requester, Management and HR (REC-APR-009).
     */
    public function comment(Request $request, Requisition $requisition, WorkflowEngine $engine, Notifier $notifier): RedirectResponse
    {
        $this->authorize('comment', $requisition);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Tulis komentar.']);

        $comment = $engine->comment($requisition->workflow, $request->user(), $validated['body']);
        $notifier->requisitionCommented($requisition, $comment, $request->user());
        app(WorkflowDiscussion::class)->markRead($requisition->workflow, $request->user());

        return back();
    }

    /**
     * The person opened the discussion: everything written so far counts as seen.
     */
    public function markCommentsRead(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('view', $requisition);

        app(WorkflowDiscussion::class)->markRead($requisition->workflow, $request->user());

        return back();
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread: int, unreadByStage: array<string, int>}
     */
    private function discussion(Requisition $requisition, User $viewer): array
    {
        return app(WorkflowDiscussion::class)->present(
            $requisition->workflow,
            $viewer,
            collect(RequisitionStage::cases())->mapWithKeys(fn (RequisitionStage $stage): array => [$stage->value => $stage->label()])->all(),
            fn (User $author): ?string => match (true) {
                $author->id === $requisition->created_by_user_id => 'PEMOHON',
                $author->isA(Role::Management) => 'MANAJEMEN',
                $author->isA(Role::HrAdmin) => 'HR',
                default => null,
            },
        );
    }

    /**
     * What the wizard offers: the departments this person may ask for, the Cabang and the people who can be the
     * direct superior in their companies, the contract types, the template's starting lines, and today's date
     * (in the company's timezone, which is what the start date is judged against).
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $departments = app(RequisitionDepartments::class)->for($user);
        $companies = $departments->pluck('company_id')->unique()->all();

        return [
            'today' => SaveRequisitionRequest::today()->toDateString(),
            'maxStart' => SaveRequisitionRequest::today()->addYears(SaveRequisitionRequest::MAX_YEARS_AHEAD)->toDateString(),
            'template' => app(RequisitionTemplate::class)->startingContent(),
            'employmentTypes' => array_map(fn (EmploymentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'needsMonths' => Requisition::typeNeedsMonths($type),
            ], EmploymentType::cases()),
            'ownEmployeeId' => Employee::acrossCompanies()->where('user_id', $user->id)->value('id'),
            'managers' => Employee::acrossCompanies()->whereIn('company_id', $companies)->where('employment_status', EmploymentStatus::Active)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number', 'company_id'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'number' => $employee->employee_number,
                    'company_id' => $employee->company_id,
                ])->values(),
            'departments' => $departments->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'company_id' => $department->company_id,
            ])->values(),
            'branches' => Branch::query()->whereIn('company_id', $companies)->orderBy('name')
                ->get(['id', 'name', 'company_id'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name, 'company_id' => $branch->company_id])
                ->values(),
        ];
    }
}
