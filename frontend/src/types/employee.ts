import type { LeaveAttachmentRow, LeaveHistoryRow } from '@/types/leave';

export type EmployeeRow = {
    id: number;
    number: string;
    name: string;
    /** Public URL of the profile picture, or null to fall back to initials. */
    photoUrl: string | null;
    /** The operating company the employee belongs to. */
    company: string | null;
    /** The office (Cabang) the employee works from. */
    branch: string | null;
    department: string | null;
    position: string | null;
    manager: string | null;
    managerId: number | null;
    status: string;
    isActive: boolean;
    /** Warehouse staff are data-only records with no login (CORE-RBA-006). */
    hasLogin: boolean;
    /** "Aktif", "Dinonaktifkan", or "Belum terdaftar" when nobody has claimed the account. */
    accountLabel: string;
    joinedOn: string | null;
};

/** Money is integer rupiah throughout — never a float. */
export type EmployeePayroll = {
    pay_group: string | null;
    base_salary_idr: number | null;
    allowances_idr: number | null;
    overtime_rate_idr: number | null;
};

export type EmployeeDetail = EmployeeRow & {
    companyCode: string | null;
    branchId: number;
    departmentId: number | null;
    positionId: number | null;
    employmentStatus: string;
    employmentType: string | null;
    /** Notice period in the contract, in days; the offboarding wizard checks against it. */
    noticeDays: number | null;
    /** Sets the rate of attendance deductions: hijau, merah or hitam. */
    zona: string;
    exitedOn: string | null;
    contact: {
        email: string | null;
        phone: string | null;
        address: string | null;
        national_id: string | null;
        emergency_contact_name: string | null;
        emergency_contact_phone: string | null;
        emergency_contact_relation: string | null;
    };
    /** Null when the viewer lacks the sensitive-data permission (EMP-DET-006). */
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_holder: string | null;
        tax_number: string | null;
        payment_method: string | null;
    } | null;
    payroll: EmployeePayroll | null;
    health: {
        insurance_number: string | null;
        insurance_tier: string | null;
        blood_type: string | null;
        height_cm: number | null;
        weight_kg: number | null;
        declared_conditions: string | null;
        last_medical_check_on: string | null;
    } | null;
};

export type EmployeeFilters = {
    search: string;
    branches: string[];
    departments: string[];
    positions: string[];
    statuses: string[];
    sort: 'name' | 'joined';
    view: 'list' | 'grid' | 'chart';
};

export type Option = { id: number; name: string };
export type ValueOption = { value: string; label: string };
export type ManagerOption = { id: number; label: string };

export type EmployeeDocumentVersion = {
    id: number;
    versionNo: number;
    name: string;
    sizeBytes: number;
    note: string | null;
    uploadedBy: string | null;
    uploadedAt: string | null;
    downloadUrl: string;
};

export type EmployeeDocumentRow = {
    id: number;
    title: string;
    category: string;
    categoryLabel: string;
    isRestricted: boolean;
    expiresOn: string | null;
    expiryStatus: 'expired' | 'expiring' | null;
    canUpdate: boolean;
    /** Newest first. */
    versions: EmployeeDocumentVersion[];
};

export type TeamPerson = {
    id: number;
    name: string;
    position: string | null;
};

/** The reporting-line slice behind the profile's "Tim" tab. */
export type EmployeeTeam = {
    grandSupervisor: TeamPerson | null;
    supervisor: TeamPerson | null;
    peers: (TeamPerson & { isSelf: boolean })[];
    reports: TeamPerson[];
    /** Which group the figures describe: direct reports when there are any, else the peers. */
    basis: 'reports' | 'peers';
    size: number;
    /** Null when the log has not reached today, or the viewer may not see attendance. */
    presentToday: number | null;
    /** Null when the viewer may not see attendance. */
    onLeaveToday: string[] | null;
};

export type LeaveStepRow = {
    label: string;
    status: 'locked' | 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    note: string | null;
    decidedBy: string | null;
    decidedAt: string | null;
};

export type LeaveRow = {
    id: number;
    reference: string;
    type: string;
    startOn: string;
    endOn: string;
    days: number;
    stageLabel: string | null;
    reason: string | null;
    timeframe: string | null;
    submittedAt: string | null;
    steps: LeaveStepRow[];
    attachments: LeaveAttachmentRow[];
    history: LeaveHistoryRow[];
    canResubmit: boolean;
};
