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
    stage: 'draft' | 'submitted' | 'checklist' | 'sign_off' | 'closed';
    stageLabel: string;
    /** 0 for a draft, then 1 to 3 along the pipeline. */
    step: number;
    statusLabel: string;
    isDraft: boolean;
    createdBy: string | null;
    daysOpen: number | null;
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
};

export type ValueLabel = { value: string; label: string };
