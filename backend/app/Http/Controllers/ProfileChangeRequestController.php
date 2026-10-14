<?php

namespace App\Http\Controllers;

use App\Actions\Profile\DecideProfileChange;
use App\Actions\Profile\SubmitProfileChange;
use App\Enums\ProfileChangeStatus;
use App\Http\Requests\DecideProfileChangeRequest;
use App\Http\Requests\StoreProfileChangeRequest;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Support\Profile\ProfileChangePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Changes an employee asks to make to their own profile, and HR's review of them (EMP-CHG).
 */
class ProfileChangeRequestController extends Controller
{
    public function __construct(private ProfileChangePresenter $presenter) {}

    /**
     * The review list for HR (EMP-CHG-003): what is waiting first, then the latest decided.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProfileChangeRequest::class);

        $user = $request->user();
        $query = ProfileChangeRequest::query()->with(['employee:id,company_id,full_name,employee_number,user_id', 'requestedBy:id,name', 'decidedBy:id,name']);

        return Inertia::render('profile-changes/index', [
            'waiting' => (clone $query)->pending()->oldest()->get()
                ->map(fn (ProfileChangeRequest $change): array => $this->presenter->present($change, $user))->values(),
            'decided' => (clone $query)->where('status', '!=', ProfileChangeStatus::Pending->value)->latest('id')->limit(30)->get()
                ->map(fn (ProfileChangeRequest $change): array => $this->presenter->present($change, $user))->values(),
            'open' => $request->integer('open') ?: null,
        ]);
    }

    /**
     * "Ajukan perubahan data": the form for the employee's own record, or the request already waiting.
     */
    public function create(Request $request, Employee $employee, SubmitProfileChange $submit): Response
    {
        abort_unless($request->user()->can('createFor', [ProfileChangeRequest::class, $employee]), 403);

        $open = $submit->openRequestFor($employee);

        return Inertia::render('employees/change-request', [
            'employee' => ['id' => $employee->id, 'name' => $employee->full_name],
            ...$this->presenter->form($employee),
            'open' => $open === null ? null : $this->presenter->present($open, $request->user()),
            'latest' => $open !== null ? null : $this->latestDecided($request, $employee),
        ]);
    }

    public function store(StoreProfileChangeRequest $request, Employee $employee, SubmitProfileChange $submit): RedirectResponse
    {
        $submit->handle($employee, $request->safe()->except('photo'), $request->file('photo'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan perubahan data terkirim. Data baru berlaku setelah HR menyetujui.']);

        return to_route('employees.changes.create', $employee);
    }

    /**
     * Take back a request that is still waiting.
     */
    public function destroy(Request $request, ProfileChangeRequest $profileChange, SubmitProfileChange $submit): RedirectResponse
    {
        $this->authorize('withdraw', $profileChange);

        $submit->withdraw($profileChange);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan ditarik.']);

        return to_route('employees.changes.create', $profileChange->employee_id);
    }

    public function decide(DecideProfileChangeRequest $request, ProfileChangeRequest $profileChange, DecideProfileChange $decide): RedirectResponse
    {
        if ($request->isApproval()) {
            $skipped = $decide->approve($profileChange, $request->user(), $request->input('note'));

            Inertia::flash('toast', [
                'type' => $skipped === [] ? 'success' : 'warning',
                'message' => $skipped === []
                    ? "{$profileChange->reference()} disetujui dan diterapkan."
                    : "{$profileChange->reference()} disetujui. Tidak diterapkan karena sudah diubah HR: ".implode(', ', $skipped).'.',
            ]);
        } else {
            $decide->reject($profileChange, $request->user(), (string) $request->input('note'));

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$profileChange->reference()} ditolak."]);
        }

        return back();
    }

    /**
     * The employee's last decided request, so a rejection and its reason are not lost on them.
     *
     * @return array<string, mixed>|null
     */
    private function latestDecided(Request $request, Employee $employee): ?array
    {
        $latest = ProfileChangeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [ProfileChangeStatus::Approved->value, ProfileChangeStatus::Rejected->value])
            ->latest('id')
            ->first();

        return $latest === null ? null : $this->presenter->present($latest, $request->user());
    }
}
