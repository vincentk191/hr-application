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
    /** Marked "Mendesak" by the requester (REC-WIZ-006). */
    isUrgent: boolean;
    urgentReason: string | null;
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

/** A job platform HR can record a posting on (REC-PST). "other" is named by HR. */
export type PlatformOption = { value: string; label: string };

/** A listing HR recorded as posted on one platform (REC-PST-002). */
export type RequisitionPosting = {
    id: number;
    platform: string;
    /** The name HR typed for "Lainnya"; null for a named platform. */
    customName: string | null;
    /** The platform as it is shown: its name, or the custom one. */
    label: string;
    url: string | null;
    postedOn: string;
    postedBy: string | null;
};

/** Someone HR shortlisted (REC-SCR). Only HR is given these. */
export type Candidate = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    source: string;
    /** The source as shown: its name, or the one typed for "Lainnya". */
    sourceLabel: string;
    note: string | null;
    addedOn: string | null;
    addedBy: string | null;
    cvName: string | null;
    cvSize: number | null;
    cvUrl: string | null;
    /** Set when the CV is a picture, which can be looked at without downloading. */
    cvPreviewUrl: string | null;
    /** Where the interview invitation stands (REC-INT). */
    inviteState: 'none' | 'invited' | 'expired' | 'submitted';
    invitedOn: string | null;
    inviteExpiresOn: string | null;
    /** "Selasa, 10 November 2026 · 10:00 WIB". */
    interviewAtLabel: string | null;
    /** The same moment as a datetime-local value, to prefill the form. */
    interviewAtInput: string | null;
    interviewMode: 'online' | 'onsite' | null;
    interviewPlace: string | null;
    formSubmittedOn: string | null;
    /** The invitation as sent, without its personal link; null if never invited. */
    invitation: SentInvitation | null;
    /** What the interviewers wrote after the interview (REC-INT), oldest first. */
    evaluations: CandidateEvaluation[];
    /** This person may add or change their evaluation now. */
    canEvaluate: boolean;
    /** What the candidate answered on their form; null until they send it. */
    form: CandidateForm | null;
};

/** One interviewer's evaluation of a candidate: a 1 to 5 rating, a recommendation and a note. */
export type CandidateEvaluation = {
    id: number;
    author: string | null;
    rating: number;
    recommendation: 'continue' | 'consider' | 'reject';
    recommendationLabel: string;
    note: string | null;
    writtenOn: string | null;
    /** Written by the person looking at it, so it can be changed. */
    isMine: boolean;
};

export type SentInvitation = {
    subject: string | null;
    message: string;
    /** It went out by email; otherwise HR copied it for WhatsApp. */
    emailed: boolean;
    sentOn: string | null;
};

export type CandidateForm = {
    answers: { group: string; text: string; answer: boolean }[];
    /** How many were answered Ya. */
    yes: number;
    expectedSalary: number | null;
    availableFrom: string | null;
    message: string | null;
};

/** What HR is handed once, right after inviting: the personal link and the text for WhatsApp. */
export type Invitation = {
    candidateId: number;
    name: string;
    /** The address the email went to; null when there was none. */
    email: string | null;
    /** Null when the candidate had already filled the form (a new time only). */
    link: string | null;
    message: string;
    expiresOn: string | null;
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
    postings: RequisitionPosting[];
    platformOptions: PlatformOption[];
    /** Today in the company's timezone: the latest date a posting may carry. */
    today: string;
    /** Why "Lanjut ke Screening" is not open yet (REC-PST-003). */
    screeningBlockers: string[];
    cancelledReason: string | null;
    cancelledAt: string | null;
    cancelledBy: string | null;
    /** The total number of applicants HR entered; null until then. */
    applicantsCount: number | null;
    shortlistedCount: number;
    /** The shortlist; null for anyone but HR, who see the counts only. */
    shortlist: Candidate[] | null;
    sourceOptions: PlatformOption[];
    /** Why "Lanjut ke Interview" is not open yet. */
    interviewBlockers: string[];
    /** HR may enter the total and shortlist: at Screening, and still at Interview. */
    canManageScreening: boolean;
    canAdvanceToInterview: boolean;
    /** HR may invite shortlisted candidates, at Interview and Offer. */
    canManageInterview: boolean;
    canManagePostings: boolean;
    canCancel: boolean;
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
