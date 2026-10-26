export type WorkflowStepSummary = {
    label: string;
    status: 'locked' | 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    note: string | null;
    decidedBy: string | null;
    decidedAt: string | null;
};

export type LeaveAttachmentRow = {
    id: number;
    name: string;
    sizeBytes: number;
    downloadUrl: string;
};

/** One remark in a request's history: an approver's note from an earlier round, or the requester's reply. */
export type LeaveHistoryRow = {
    author: string | null;
    stage: string | null;
    stageLabel: string;
    note: string;
    at: string | null;
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
    branch: string | null;
    department: string | null;
    type: string;
    startOn: string;
    endOn: string;
    days: number;
    reason: string | null;
    /** "09:00–11:00" when the request names a time of day. */
    timeframe: string | null;
    submittedAt: string | null;
    stage: 'submitted' | 'approved' | 'rejected' | null;
    stageLabel: string | null;
    waitingOn: string | null;
    /** Whether the signed-in user owns the currently open step. */
    canDecide: boolean;
    steps: WorkflowStepSummary[];
    attachments: LeaveAttachmentRow[];
    history: LeaveHistoryRow[];
    /** The viewer may correct this (it was rejected) and send it for approval again. */
    canResubmit: boolean;
};

export type LeaveTypeOption = {
    value: string;
    label: string;
    /** How the recap counts it. Sakit needs an attachment and is decided by HR Admin. */
    kind: 'none' | 'izin' | 'sakit';
    /** Longest a single request of this type may be, or null for no limit. */
    maxDays: number | null;
    /** How many calendar days back a request of this type may start, or null for none. */
    backDays: number | null;
};

export type EmployeeOption = {
    id: number;
    label: string;
};

/** What the requests queue is narrowed by (REQ-DSH). */
export type LeaveRequestFilters = {
    status: 'all' | 'mine' | 'waiting' | 'approved' | 'rejected';
    type: string | null;
    branch: string | null;
    department: string | null;
    /** "YYYY-MM". */
    month: string | null;
};
