/** A row of the offboarding dashboard (OFF-DSH-003). */
export type OffboardingRow = {
    id: number;
    reference: string;
    employee: {
        id: number;
        name: string | null;
        number: string | null;
        position: string | null;
        department: string | null;
    };
    exitType: string | null;
    lastWorkingOn: string | null;
    stage:
        | 'draft'
        | 'submitted'
        | 'checklist'
        | 'sign_off'
        | 'closed'
        | 'cancelled';
    stageLabel: string;
    /** 0 for a draft, then 1 to 3 along the pipeline. */
    step: number;
    statusLabel: string;
    isDraft: boolean;
    createdBy: string | null;
    daysOpen: number | null;
    /** A case listed for someone who only owns part of its checklist: no reason, type or requester. */
    limited?: boolean;
};

export type OffboardingFilters = {
    status: string;
    department: string | null;
    lastDay: 'week' | 'month' | 'quarter' | null;
};

export type OffboardingSummary = {
    open: number;
    perStage: Record<string, number>;
    oldestDays: number | null;
};

export type OffboardingFile = {
    id: number;
    kind: string;
    kindLabel: string;
    name: string;
    sizeBytes: number;
    uploadedAt: string | null;
    downloadUrl: string;
};

export type OffboardingStep = {
    label: string;
    /** The person bound to decide it, when there is one. */
    approver: string | null;
    status: 'locked' | 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    decidedBy: string | null;
    decidedAt: string | null;
    note: string | null;
    waitingDays: number | null;
};

export type OffboardingComment = {
    id: number;
    author: string | null;
    body: string;
    at: string;
};

export type OffboardingChecklistItem = {
    id: number;
    name: string;
    kind: string;
    isAdhoc: boolean;
    done: boolean;
    doneAt: string | null;
    doneBy: string | null;
    note: string | null;
    canTick: boolean;
    canRemove: boolean;
};

export type OffboardingChecklistGroup = {
    id: number;
    name: string;
    owner: string | null;
    /** Nobody is set to own it (or they are deactivated), so HR Admin does. */
    ownerIsHr: boolean;
    /** The owner's job title and department as they were when the checklist began. */
    ownerTitle: string | null;
    ownerId: number | null;
    ownerUnavailable: boolean;
    canReassign: boolean;
    status: 'choosing' | 'running' | 'done' | 'not_involved';
    statusLabel: string;
    confirmed: boolean;
    notInvolved: boolean;
    done: number;
    total: number;
    canWork: boolean;
    canAddItem: boolean;
    canSetAside: boolean;
    canSelect: boolean;
    items: OffboardingChecklistItem[];
    /** Master items of the department not yet picked, for the owner. */
    available: { id: number; name: string; kind: string }[];
};

export type OffboardingChecklist = {
    progress: {
        items: number;
        done: number;
        groups: number;
        settled: number;
        ready: boolean;
        blockers: string[];
    };
    /** The phase's own two checks: the owners sign off their part, then HR approves. */
    chain: OffboardingStep[];
    /** HR has approved the finished checklist, so the case may go on. */
    approved: boolean;
    /** HR may approve it now: it is finished and not yet approved. */
    canApprove: boolean;
    groups: OffboardingChecklistGroup[];
    /** Who HR can hand a group to: anyone with an account, in any company. */
    ownerOptions: { id: number; label: string }[];
    /** HR may bring the checklist up to date with the master list. */
    canRefresh: boolean;
    /** Items added to the master list since the checklist opened. */
    newItems: number;
};

export type OffboardingSignoff = {
    exitInterview: {
        recorded: boolean;
        skipped: boolean;
        skippedReason: string | null;
        reason: string | null;
        rehire: boolean | null;
        notes: string | null;
        at: string | null;
        canRecord: boolean;
    };
    /** Why the case cannot close yet. */
    closeBlockers: string[];
};

export type OffboardingImpact = {
    reports: { id: number; name: string; number: string }[];
    defaultManagerId: number | null;
    options: { id: number; label: string }[];
    headOf: string[];
};

export type OffboardingCase = OffboardingRow & {
    employee: OffboardingRow['employee'] & {
        manager: string | null;
        joinedOn: string | null;
        photoUrl: string | null;
    };
    exitTypeValue: string | null;
    noticeOn: string | null;
    reason: string | null;
    compensationMonths: number;
    compensationReason: string | null;
    noticeCheck: { given: number | null; required: number | null; short: boolean };
    submittedAt: string | null;
    tabs: { stage: string; label: string; state: 'done' | 'current' | 'next' }[];
    files: OffboardingFile[];
    chain: OffboardingStep[];
    comments: OffboardingComment[];
    /** Present from the checklist stage on. */
    checklist: OffboardingChecklist | null;
    /** Present from sign-off on. */
    signoff: OffboardingSignoff | null;
    /** For HR while the case runs: the leaver's reports and departments. */
    impact: OffboardingImpact | null;
    exitApplied: boolean;
    closedAt: string | null;
    cancelledAt: string | null;
    cancelReason: string | null;
    canChangeLastDay: boolean;
    canCancel: boolean;
    canClose: boolean;
    canSendBack: boolean;
    canBeritaAcara: boolean;
    /** HR may move a case whose checklist is complete on to sign-off. */
    canProceed: boolean;
    canEdit: boolean;
    canDecide: boolean;
    canComment: boolean;
    canAddFiles: boolean;
};

/** A person who can be offboarded, with what the wizard's summary panel shows. */
export type OffboardingCandidate = {
    id: number;
    name: string;
    number: string;
    position: string | null;
    department: string | null;
    manager: string | null;
    joinedOn: string | null;
    noticeDays: number | null;
    /** Active people who report to them, and the departments they head: to settle before they leave. */
    reportsCount: number;
    headOf: string[];
};

export type ValueLabel = { value: string; label: string };
