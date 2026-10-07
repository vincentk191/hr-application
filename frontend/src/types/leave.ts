export type WorkflowStepSummary = {
    label: string;
    status: 'locked' | 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    note: string | null;
};

/**
 * One row of the requests queue (REQ-DSH), and the subject of the decision dialog (REQ-DLG).
 *
 * `stage` and `waitingOn` both come from the workflow engine rather than from the request
 * record itself — a leave request has no status column of its own.
 */
export type LeaveRequestRow = {
    id: number;
    reference: string;
    employee: string | null;
    department: string | null;
    type: string;
    startOn: string;
    endOn: string;
    days: number;
    reason: string | null;
    submittedAt: string | null;
    stage: 'submitted' | 'approved' | 'rejected' | null;
    stageLabel: string | null;
    waitingOn: string | null;
    /** Whether the signed-in user owns the currently open step. */
    canDecide: boolean;
    steps: WorkflowStepSummary[];
};

export type LeaveTypeOption = {
    value: string;
    label: string;
};

export type EmployeeOption = {
    id: number;
    label: string;
};
