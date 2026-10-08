<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\EmployeeDocument;
use App\Support\Attendance\AgentStatus;
use App\Support\Attendance\CaptureStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many lapsing documents the dashboard lists before pointing at the profiles.
     */
    private const EXPIRY_LIST_LIMIT = 8;

    public function __invoke(Request $request, CaptureStatus $status, AgentStatus $agents): Response
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

        return Inertia::render('dashboard', [
            // Only for the people who capture the scanner log; everyone else gets no card.
            'attendanceStatus' => $user->can('manage-attendance-device')
                ? [...$status->resolve(), 'agentStale' => $agents->anyStale()]
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
}
