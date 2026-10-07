<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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

        $filters = [
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

        $employees = Employee::query()
            ->with(['department:id,name', 'position:id,name', 'manager:id,full_name'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('full_name', 'like', "%{$filters['search']}%")
                    ->orWhere('employee_number', 'like', "%{$filters['search']}%"),
            ))
            ->when($filters['departments'] !== [], fn (Builder $query) => $query->whereIn('department_id', $filters['departments']))
            ->when($filters['positions'] !== [], fn (Builder $query) => $query->whereIn('position_id', $filters['positions']))
            ->when($filters['statuses'] !== [], fn (Builder $query) => $query->whereIn('employment_status', $filters['statuses']))
            ->when($filters['sort'] === 'joined', fn (Builder $query) => $query->orderByDesc('joined_on'))
            ->when($filters['sort'] === 'name', fn (Builder $query) => $query->orderBy('full_name'))
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
     * The employee profile (EMP-PRF) and its Overview tab (EMP-DET).
     */
    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load(['department:id,name', 'position:id,name', 'manager:id,full_name', 'company:id,code,name']);

        return Inertia::render('employees/show', [
            'employee' => $this->detail($employee),
            'requests' => LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->with('workflow')
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
                ]),
            // Leave taken this year. No entitlement balance is modelled (context.md §10), so
            // this is a count of days taken, not a remaining figure.
            'leaveTakenThisYear' => (int) LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->whereYear('start_on', now()->year)
                ->whereHas('workflow', fn (Builder $query) => $query->where('current_stage', 'approved'))
                ->sum('days'),
            'canUpdate' => $request->user()->can('update', $employee),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('employees/create', $this->formOptions());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        // company_id is filled by BelongsToCompany from the active company (EMP-ADD-004), so
        // it is deliberately absent from the validated payload.
        $employee = Employee::create([
            ...$request->safe()->except(['photo', 'remove_photo']),
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
            'employee' => $this->detail($employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'remove_photo']);

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
    private function detail(Employee $employee): array
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
            'bank' => $employee->only([
                'bank_name', 'bank_account_number', 'bank_account_holder', 'tax_number', 'payment_method',
            ]),
            'payroll' => $employee->only([
                'pay_group', 'base_salary_idr', 'allowances_idr', 'overtime_rate_idr',
            ]),
            'health' => [
                ...$employee->only([
                    'insurance_number', 'insurance_tier', 'blood_type',
                    'height_cm', 'weight_kg', 'declared_conditions',
                ]),
                'last_medical_check_on' => $employee->last_medical_check_on?->toDateString(),
            ],
        ];
    }
}
