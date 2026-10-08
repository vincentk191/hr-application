<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Permission;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentVersion;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\Attendance\DailyAttendance;
use App\Support\Attendance\EmployeeTimesheet;
use App\Support\CsvDownload;
use App\Support\Employees\TeamSlice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    /**
     * The employee directory (EMP-DIR), with the grid and org-chart views behind the same
     * query (EMP-GRD, EMP-CHT).
     *
     * Every list here is company-scoped by CompanyScope, so there is no company clause to
     * forget — switching company in the header changes what this returns.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $filters = $this->filtersFrom($request);

        $employees = $this->filteredQuery($filters)
            ->with(['department:id,name', 'position:id,name', 'manager:id,full_name'])
            ->get();

        return Inertia::render('employees/index', [
            'employees' => $employees->map(fn (Employee $employee): array => $this->summarise($employee)),
            'filters' => $filters,
            'total' => Employee::count(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'statuses' => array_map(
                fn (EmploymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                EmploymentStatus::cases(),
            ),
            'canCreate' => $request->user()->can('create', Employee::class),
        ]);
    }

    /**
     * The directory's filters as read from the query string, shared by the list and its export so
     * the file always matches what is on screen.
     *
     * @return array{search: string, departments: array<int, int>, positions: array<int, int>, statuses: list<mixed>, sort: string, view: string}
     */
    private function filtersFrom(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'departments' => array_filter(array_map('intval', (array) $request->input('departments', []))),
            'positions' => array_filter(array_map('intval', (array) $request->input('positions', []))),
            'statuses' => array_values(array_intersect(
                (array) $request->input('statuses', []),
                array_column(EmploymentStatus::cases(), 'value'),
            )),
            'sort' => in_array($request->string('sort')->toString(), ['name', 'joined'], true)
                ? $request->string('sort')->toString()
                : 'name',
            'view' => in_array($request->string('view')->toString(), ['list', 'grid', 'chart'], true)
                ? $request->string('view')->toString()
                : 'list',
        ];
    }

    /**
     * @param  array{search: string, departments: array<int, int>, positions: array<int, int>, statuses: list<mixed>, sort: string, view: string}  $filters
     * @return Builder<Employee>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Employee::query()
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('full_name', 'like', "%{$filters['search']}%")
                    ->orWhere('employee_number', 'like', "%{$filters['search']}%"),
            ))
            ->when($filters['departments'] !== [], fn (Builder $query) => $query->whereIn('department_id', $filters['departments']))
            ->when($filters['positions'] !== [], fn (Builder $query) => $query->whereIn('position_id', $filters['positions']))
            ->when($filters['statuses'] !== [], fn (Builder $query) => $query->whereIn('employment_status', $filters['statuses']))
            ->when($filters['sort'] === 'joined', fn (Builder $query) => $query->orderByDesc('joined_on'))
            ->when($filters['sort'] === 'name', fn (Builder $query) => $query->orderBy('full_name'));
    }

    /**
     * Download the filtered directory as CSV (EMP-DIR-011): the same columns the import reads, and
     * never the bank, payroll or health fields. The download is on the audit trail.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->filteredQuery($this->filtersFrom($request))
            ->with(['department:id,name', 'position:id,name', 'manager:id,employee_number'])
            ->get();

        activity('employee')
            ->causedBy($request->user())
            ->withProperties(['rows' => $employees->count()])
            ->log('Ekspor direktori karyawan ke CSV');

        $rows = $employees->map(fn (Employee $employee): array => [
            $employee->employee_number,
            $employee->full_name,
            $employee->department?->name,
            $employee->position?->name,
            $employee->manager?->employee_number,
            $employee->employment_status->label(),
            $employee->employment_type?->label(),
            $employee->joined_on?->toDateString(),
            $employee->exited_on?->toDateString(),
            $employee->email,
            $employee->phone,
            $employee->address,
            $employee->national_id,
        ]);

        return CsvDownload::stream('karyawan-'.now()->format('Y-m-d').'.csv', [EmployeeImportController::COLUMNS, ...$rows]);
    }

    /**
     * The employee profile (EMP-PRF) and its Overview tab (EMP-DET).
     */
    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load(['department:id,name', 'position:id,name', 'manager:id,full_name', 'company:id,code,name']);

        $user = $request->user();

        return Inertia::render('employees/show', [
            ...$this->profile($request, $employee),
            'documentCategories' => array_map(
                fn (DocumentCategory $category): array => ['value' => $category->value, 'label' => $category->label()],
                DocumentCategory::cases(),
            ),
            'canManageDocuments' => $user->can('create', EmployeeDocument::class),
            'canRestrictDocuments' => $user->can(Permission::ViewSensitiveData->value),
            // Loaded only when the tab is opened (router.reload with `only`), so the profile does
            // not pay for a month of punches on every visit.
            'attendance' => $user->can('viewAttendance', $employee)
                ? Inertia::optional(fn (): array => $this->timesheet($employee, $request))
                : null,
            'canCorrectAttendance' => $user->can('correctAttendance', $employee),
        ]);
    }

    /**
     * The profile as JSON, for the side drawer on the directory. Read-only by design: uploading a
     * document or correcting a scan redirects, which would navigate the list away from under the
     * drawer, so those stay on the full profile page.
     */
    public function summary(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $employee->load(['department:id,name', 'position:id,name', 'manager:id,full_name', 'company:id,code,name']);

        $user = $request->user();

        return response()->json([
            ...$this->profile($request, $employee),
            'attendance' => $user->can('viewAttendance', $employee) ? $this->timesheet($employee, $request) : null,
            'canCreateLeave' => $user->can('create', LeaveRequest::class),
        ]);
    }

    /**
     * What the full profile and the drawer share: the dossier, documents, leave and the team.
     *
     * @return array<string, mixed>
     */
    private function profile(Request $request, Employee $employee): array
    {
        $user = $request->user();

        return [
            'employee' => $this->detail($employee, $this->maySeeSensitive($user, $employee)),
            'documents' => $this->documentsFor($user, $employee),
            'requests' => LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->with('workflow.steps.decidedBy:id,name')
                ->latest('id')
                ->get()
                ->map(fn (LeaveRequest $leave): array => [
                    'id' => $leave->id,
                    'reference' => $leave->reference(),
                    'type' => $leave->leave_type->label(),
                    'startOn' => $leave->start_on->toDateString(),
                    'endOn' => $leave->end_on->toDateString(),
                    'days' => $leave->days,
                    'stageLabel' => $leave->workflow?->stage()->label(),
                    'reason' => $leave->reason,
                    'submittedAt' => $leave->created_at?->toDateString(),
                    // The reviewer's note travels with the step: a rejection always carries one, and
                    // the requester is the person it is written for.
                    'steps' => $leave->workflow?->steps->map(fn (WorkflowStep $step): array => [
                        'label' => $step->label,
                        'status' => $step->status->value,
                        'statusLabel' => $step->status->label(),
                        'note' => $step->note,
                        'decidedBy' => $step->decidedBy?->name,
                        'decidedAt' => $step->decided_at?->toDateString(),
                    ])->values()->all() ?? [],
                ]),
            // Leave taken this year. No entitlement balance is modelled (context.md §10), so
            // this is a count of days taken, not a remaining figure.
            'leaveTakenThisYear' => (int) LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->whereYear('start_on', now()->year)
                ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', 'approved'))
                ->sum('days'),
            'canUpdate' => $user->can('update', $employee),
            // The reporting line names colleagues, so it is for people who may see the directory;
            // an employee looking only at their own record does not get it.
            'team' => $user->can('viewAny', Employee::class)
                ? app(TeamSlice::class)->for($employee, $user->can(Permission::ViewAttendance->value))
                : null,
        ];
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('employees/create', [
            ...$this->formOptions(),
            'canEditSensitive' => $request->user()->can(Permission::ViewSensitiveData->value),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        // company_id is filled by BelongsToCompany from the active company (EMP-ADD-004), so
        // it is deliberately absent from the validated payload.
        $employee = Employee::create([
            ...$this->writableFields($request->safe()->except(['photo', 'remove_photo']), $request->user()),
            'photo_path' => $request->file('photo')?->store('employee-photos', 'public'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$employee->full_name} ditambahkan.",
        ]);

        return to_route('employees.show', $employee);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('employees/edit', [
            ...$this->formOptions(),
            'employee' => $this->detail($employee, $request->user()->can(Permission::ViewSensitiveData->value)),
            'canEditSensitive' => $request->user()->can(Permission::ViewSensitiveData->value),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $this->writableFields($request->safe()->except(['photo', 'remove_photo']), $request->user());

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('employee-photos', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $data['photo_path'] = null;
        }

        $previousPhoto = $employee->photo_path;

        $employee->update($data);

        if ($previousPhoto !== null && $employee->photo_path !== $previousPhoto) {
            Storage::disk('public')->delete($previousPhoto);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data karyawan diperbarui.',
        ]);

        return to_route('employees.show', $employee);
    }

    /**
     * Options every employee form needs.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('name')->get(['id', 'name', 'department_id']),
            'managers' => Employee::query()->active()->orderBy('full_name')->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'label' => "{$employee->full_name} ({$employee->employee_number})",
                ]),
            'employmentStatuses' => array_map(
                fn (EmploymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                EmploymentStatus::cases(),
            ),
            'employmentTypes' => array_map(
                fn (EmploymentType $type): array => ['value' => $type->value, 'label' => $type->label()],
                EmploymentType::cases(),
            ),
        ];
    }

    /**
     * The row shape used by the list, grid and chart views.
     *
     * @return array<string, mixed>
     */
    private function summarise(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'number' => $employee->employee_number,
            'name' => $employee->full_name,
            'photoUrl' => $employee->photoUrl(),
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'manager' => $employee->manager?->full_name,
            'managerId' => $employee->manager_id,
            'status' => $employee->employment_status->label(),
            'isActive' => $employee->employment_status === EmploymentStatus::Active,
            'hasLogin' => $employee->hasLogin(),
            'joinedOn' => $employee->joined_on?->toDateString(),
        ];
    }

    /**
     * The full dossier, for the profile and the edit form.
     *
     * @return array<string, mixed>
     */
    private function detail(Employee $employee, bool $includeSensitive = true): array
    {
        return [
            ...$this->summarise($employee),
            'companyCode' => $employee->company?->code,
            'departmentId' => $employee->department_id,
            'positionId' => $employee->position_id,
            'employmentStatus' => $employee->employment_status->value,
            'employmentType' => $employee->employment_type?->value,
            'exitedOn' => $employee->exited_on?->toDateString(),
            'contact' => $employee->only([
                'email', 'phone', 'address', 'national_id',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
            ]),
            // Null, not empty, when the viewer may not see them, so the page can say "restricted"
            // rather than show a convincing blank (EMP-DET-006).
            'bank' => $includeSensitive ? $employee->only([
                'bank_name', 'bank_account_number', 'bank_account_holder', 'tax_number', 'payment_method',
            ]) : null,
            'payroll' => $includeSensitive ? $employee->only([
                'pay_group', 'base_salary_idr', 'allowances_idr', 'overtime_rate_idr',
            ]) : null,
            'health' => $includeSensitive ? [
                ...$employee->only([
                    'insurance_number', 'insurance_tier', 'blood_type',
                    'height_cm', 'weight_kg', 'declared_conditions',
                ]),
                'last_medical_check_on' => $employee->last_medical_check_on?->toDateString(),
            ] : null,
        ];
    }

    /**
     * The month asked for with ?month=YYYY-MM, otherwise the last month that has data, otherwise
     * this one.
     *
     * @return array<string, mixed>
     */
    private function timesheet(Employee $employee, Request $request): array
    {
        $timezone = (string) config('attendance.device_timezone');

        // ?period=year&year=YYYY is the year at a glance; the default is the month's timesheet.
        if ($request->string('period')->toString() === 'year') {
            $year = $request->integer('year');
            $year = $year >= 2000 && $year <= 2100
                ? $year
                : (int) CarbonImmutable::parse(app(DailyAttendance::class)->dataThrough($timezone) ?? 'today', $timezone)->format('Y');

            return app(EmployeeTimesheet::class)->forYear($employee, $year);
        }

        $asked = $request->string('month')->toString();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $asked) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $asked.'-01', $timezone)
            : CarbonImmutable::parse(app(DailyAttendance::class)->dataThrough($timezone) ?? 'today', $timezone);

        return app(EmployeeTimesheet::class)->forMonth($employee, $month->startOfMonth());
    }

    /**
     * Whether this user may see the bank, payroll and health groups of this record: anyone with
     * the sensitive-data permission, and an employee looking at their own record.
     */
    private function maySeeSensitive(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ViewSensitiveData->value)
            || ($employee->user_id !== null && $employee->user_id === $user->id);
    }

    /**
     * Drop the sensitive fields from a write when the user may not handle them, so a form that
     * could not show them cannot blank them either.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function writableFields(array $data, User $user): array
    {
        if ($user->can(Permission::ViewSensitiveData->value)) {
            return $data;
        }

        return array_diff_key($data, array_flip(Employee::SENSITIVE_FIELDS));
    }

    /**
     * The documents this user may see on the record, newest first. A restricted document the
     * user cannot see is left out entirely, with no hint that it exists.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentsFor(User $user, Employee $employee): array
    {
        return EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->with(['versions.uploadedBy:id,name'])
            ->latest('id')
            ->get()
            ->filter(fn (EmployeeDocument $document): bool => $user->can('view', $document))
            ->map(fn (EmployeeDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category->value,
                'categoryLabel' => $document->category->label(),
                'isRestricted' => $document->is_restricted,
                'expiresOn' => $document->expires_on?->toDateString(),
                'expiryStatus' => $document->expiryStatus(),
                'canUpdate' => $user->can('update', $document),
                'versions' => $document->versions->map(fn (EmployeeDocumentVersion $version): array => [
                    'id' => $version->id,
                    'versionNo' => $version->version_no,
                    'name' => $version->original_name,
                    'sizeBytes' => $version->size_bytes,
                    'note' => $version->note,
                    'uploadedBy' => $version->uploadedBy?->name,
                    'uploadedAt' => $version->created_at?->toDateString(),
                    'downloadUrl' => route('documents.download', [$document, $version]),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
