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
    /** From HR's data sheet. `gender` and the like are the select values, `...Label` what the profile shows. */
    personal: {
        gender: string | null;
        genderLabel: string | null;
        birth_place: string | null;
        birth_date: string | null;
        religion: string | null;
        religionLabel: string | null;
        marital_status: string | null;
        maritalStatusLabel: string | null;
        education_level: string | null;
        /** The sheet's Jabatan: a free-text title (Staff, Supervisor ...); `position` is its Posisi. */
        job_title: string | null;
    };
    /** The rest of the home address; the street is `contact.address`. */
    residence: {
        residence_village: string | null;
        residence_district: string | null;
        residence_city: string | null;
        residence_province: string | null;
        residence_postal_code: string | null;
    };
    /** The address on the KTP. */
    idAddress: {
        id_address: string | null;
        id_block: string | null;
        id_house_number: string | null;
        id_rt: string | null;
        id_rw: string | null;
        id_village: string | null;
        id_district: string | null;
        id_city: string | null;
        id_province: string | null;
        id_postal_code: string | null;
    };
    /** Null when the viewer lacks the sensitive-data permission (EMP-DET-006). */
    identity: {
        family_card_number: string | null;
        mother_name: string | null;
    } | null;
    /** Null when the viewer lacks the sensitive-data permission (EMP-DET-006). */
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_holder: string | null;
        bank_branch: string | null;
        tax_number: string | null;
        /** TK/0, K/1, K/I/2 ... */
        ptkp_status: string | null;
        payment_method: string | null;
    } | null;
    /** Kesehatan & asuransi: the two BPJS numbers. Null when the viewer lacks the sensitive-data permission. */
    coverage: {
        bpjs_employment_number: string | null;
        bpjs_health_number: string | null;
    } | null;
    payroll: EmployeePayroll | null;
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
    /** Set for a picture, which can be looked at without downloading. */
    previewUrl: string | null;
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
    /** submitted, approved or rejected; what the status badge is coloured by. */
    stage: string | null;
    stageLabel: string | null;
    reason: string | null;
    timeframe: string | null;
    submittedAt: string | null;
    steps: LeaveStepRow[];
    attachments: LeaveAttachmentRow[];
    history: LeaveHistoryRow[];
    canResubmit: boolean;
};
