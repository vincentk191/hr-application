<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Support\Attendance\AgentStatus;
use App\Support\Attendance\CaptureStatus;
use App\Support\Dashboard\AdminOverview;
use App\Support\Dashboard\EmployeeOverview;
use App\Support\Features;
use App\Support\Leave\LeaveRequestPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many lapsing documents the dashboard lists before pointing at the profiles.
     */
    private const EXPIRY_LIST_LIMIT = 8;

    public function __invoke(Request $request, CaptureStatus $status, AgentStatus $agents, EmployeeOverview $overview, AdminOverview $admin): Response
    {
        $user = $request->user();

        // The reminder (EMP-DOC-002): documents already past their date or about to be, for the
        // people who look after documents. Other roles get nothing rather than an empty card.
        $lapsing = $user->can(Permission::ViewDocuments->value)
            ? EmployeeDocument::query()
                ->needingAttention()
                ->with('employee:id,full_name')
                ->orderBy('expires_on')
                ->get()
                ->filter(fn (EmployeeDocument $document): bool => $user->can('view', $document))
                ->values()
            : null;

        // The person's own record, for the greeting, today's attendance and their requests. An account with no
        // dossier record (a System Admin, say) has none of that and sees only the cards for its own duties.
        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        return Inertia::render('dashboard', [
            'intro' => $overview->intro($own?->full_name ?? $user->name),
            'overview' => $own === null ? null : $overview->for($own, withAttendance: Features::attendance()),
            'canCreateRequest' => $user->can('create', LeaveRequest::class),
            // The cards for the people who run HR. Each is null for everyone else, who sees no card.
            'waiting' => fn (): ?array => $admin->waiting($user),
            // A closure, so moving the month reloads only the calendar (`?calendar=YYYY-MM`). A person with an employee record
            // may switch it to their own leave (`&scope=me`), so they have one calendar, not a second one for themselves. Anyone
            // else with an employee record has that own view alone: their requests, waiting or approved, and the holidays and events.
            'adminCalendar' => fn (): ?array => AdminOverview::seesCalendar($user) || $own !== null
                ? $admin->calendar($user, $request->string('calendar')->toString() ?: null, $own, $request->string('scope')->toString() ?: null)
                : null,
            'recruitment' => fn (): ?array => $admin->recruitment($user),
            // A request opened over the dashboard (`?request=ID`) rather than on the Pengajuan page: the same panel, read the
            // same way, through the same visibility, so a request the person may not see opens nothing.
            'openedRequest' => fn (): ?array => $this->openedRequest($request),
            // Only for the people who capture the scanner log; everyone else gets no card.
            'attendanceStatus' => $user->can('manage-attendance-device')
                ? [...$status->resolve(), 'agentStale' => Features::attendanceAgents() && $agents->anyStale()]
                : null,
            'expiringDocuments' => $lapsing === null ? null : [
                'total' => $lapsing->count(),
                'items' => $lapsing->take(self::EXPIRY_LIST_LIMIT)->map(fn (EmployeeDocument $document): array => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'employeeId' => $document->employee_id,
                    'employeeName' => $document->employee?->full_name,
                    'expiresOn' => $document->expires_on?->toDateString(),
                    'expiryStatus' => $document->expiryStatus(),
                ])->all(),
            ],
        ]);
    }

    /**
     * The leave request the dashboard was asked to open, shaped as the Pengajuan page's panel wants it.
     *
     * @return array<string, mixed>|null
     */
    private function openedRequest(Request $request): ?array
    {
        $id = $request->integer('request');
        $user = $request->user();

        if ($id <= 0 || $user === null) {
            return null;
        }

        $leave = LeaveRequest::query()->visibleTo($user)->with(LeaveRequestPresenter::RELATIONS)->find($id);

        return $leave === null ? null : LeaveRequestPresenter::row($leave, $user);
    }
}
