<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStage;
use App\Http\Requests\DecideLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Workflows\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeaveRequestDecisionController extends Controller
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * Approve or reject the open step (REQ-DLG-004 / REQ-DLG-005).
     *
     * The controller never decides anything itself: it tells the engine, and the engine
     * enforces whose step it is, opens the next one, and records the decision. Advancing the
     * stage is likewise gated — the request only becomes Approved once the whole chain is
     * approved, which for a two-step chain means HR after the supervisor.
     */
    public function update(DecideLeaveRequestRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $instance = $leaveRequest->workflow;
        $step = $this->engine->waitingOn($instance);

        if ($step === null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Pengajuan ini sudah diputuskan.']);

            return back();
        }

        if ($request->isApproval()) {
            $this->engine->approve($step, $request->user(), $request->note());

            // Only the last approval finishes the request; earlier ones just open the next
            // step. blockingReasons() is what tells us which case this is.
            if ($this->engine->chainIsApproved($instance->refresh())) {
                $this->engine->transitionTo($instance, LeaveRequestStage::Approved, $request->user());

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => "Pengajuan {$leaveRequest->reference()} disetujui.",
                ]);

                return back();
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Persetujuan Anda tercatat. Menunggu persetujuan berikutnya.',
            ]);

            return back();
        }

        $this->engine->reject($step, $request->user(), $request->note());
        $this->engine->transitionTo($instance->refresh(), LeaveRequestStage::Rejected, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengajuan {$leaveRequest->reference()} ditolak.",
        ]);

        return back();
    }
}
