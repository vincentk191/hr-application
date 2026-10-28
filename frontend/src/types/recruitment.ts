import type { ApprovalChainStep } from '@/components/approval-chain';

export type RequisitionStage =
    | 'draft'
    | 'submitted'
    | 'posting'
    | 'screening'
    | 'interview'
    | 'offer'
    | 'filled'
    | 'cancelled';

/** A row of the recruitment dashboard (REC-DSH-002). */
export type RequisitionRow = {
    id: number;
    reference: string;
    positionName: string;
    department: string | null;
    branch: string | null;
    quota: number | null;
    /** Hired so far; 0 until offers exist (stage 05). */
    filled: number;
    /** Applicants; null until screening exists (stage 03). */
    candidates: number | null;
    stage: RequisitionStage;
    stageLabel: string;
    /** 0 for a draft, then 1 to 6 along the pipeline. */
    step: number;
    /** Where in the stage it is, e.g. "Menunggu persetujuan HR" (REC-PIP-006). */
    statusLabel: string;
    isDraft: boolean;
    createdBy: string | null;
    /** The date the latest stage was reached; null for a draft (REC-DSH-004). */
    lastUpdate: string | null;
};

export type RequisitionFilters = {
    status: string;
    department: string | null;
};

export type RequisitionSummary = {
    open: number;
    awaitingApproval: number;
    quota: number;
    drafts: number;
};

/** One of the six pipeline stages (REC-PIP-002). */
export type RequisitionTab = {
    stage: RequisitionStage;
    label: string;
    number: string;
    state: 'done' | 'current' | 'locked';
    reachedAt: string | null;
};

export type RequisitionStep = ApprovalChainStep & { note: string | null };

/** A subtitle under Tanggung Jawab Utama with its lines; the requester names and adds these (REC-WZ2). */
export type ResponsibilityGroup = { title: string; items: string[] };

/** The candidate checklist's fixed subtitles, in order (RequisitionTemplate::CHECKLIST). */
export const CHECKLIST_KEYS = [
    'education',
    'experience',
    'hard',
    'soft',
    'bonus',
] as const;

export type ChecklistKey = (typeof CHECKLIST_KEYS)[number];

export type Checklist = Record<ChecklistKey, string[]>;

/** What the job posting and the candidate checklist hold: the template's shape, filled by the requester. */
export type RequisitionContent = {
    about: string;
    responsibilities: ResponsibilityGroup[];
    benefits: string[];
    checklist: Checklist;
};

/** A contract type the wizard offers; Kontrak and Magang also ask for the length in months. */
export type EmploymentTypeOption = {
    value: string;
    label: string;
    needsMonths: boolean;
};

/** Someone who can be the new hire's direct superior, with their company so the list can follow the department. */
export type ManagerOption = {
    id: number;
    name: string;
    number: string | null;
    company_id: number;
};

/** A requisition on its pipeline, or in the wizard (REC-PIP, REC-WIZ). */
export type Requisition = RequisitionRow & {
    departmentId: number;
    branchId: number | null;
    reason: string | null;
    targetStartOn: string | null;
    employmentType: string | null;
    contractMonths: number | null;
    /** "Kontrak 1 tahun", "Magang 6 bulan", "Tetap". */
    contractLabel: string | null;
    managerId: number | null;
    manager: string | null;
    about: string | null;
    responsibilities: ResponsibilityGroup[];
    benefits: string[];
    checklist: Checklist;
    /** The wizard step a draft was left on (REC-WIZ-004). */
    draftStep: number;
    submittedAt: string | null;
    tabs: RequisitionTab[];
    chain: RequisitionStep[];
    /** Only for Management and HR (REC-APR-008). */
    salary: { min: number | null; max: number | null; set: boolean } | null;
    /** The open step is Management's, who must set the salary expectation to approve. */
    awaitingSalary: boolean;
    /** Why "Lanjut ke Posting" is not open yet (REC-APR-003). */
    advanceBlockers: string[];
    canEdit: boolean;
    canDelete: boolean;
    canDecide: boolean;
    canSaveSalary: boolean;
    canAdvance: boolean;
    canComment: boolean;
};

/** A department the person may ask for, with its company so the Cabang list can follow it. */
export type RequisitionDepartmentOption = {
    id: number;
    name: string;
    company_id: number;
};
