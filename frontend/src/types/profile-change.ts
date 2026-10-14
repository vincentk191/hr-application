/** One changed field in a profile change request (EMP-CHG-003). */
export type ProfileChangeField = {
    field: string;
    label: string;
    group: string;
    isPhoto: boolean;
    /** The reviewer may not see bank and tax values; the field is listed, the values are not. */
    masked: boolean;
    before: string | null;
    after: string | null;
    beforeUrl: string | null;
    afterUrl: string | null;
};

export type ProfileChange = {
    id: number;
    reference: string;
    status: 'pending' | 'approved' | 'rejected' | 'withdrawn';
    statusLabel: string;
    employee: { id: number; name: string | null; number: string | null };
    requestedBy: string | null;
    submittedAt: string | null;
    decidedBy: string | null;
    decidedAt: string | null;
    decisionNote: string | null;
    /** Fields an approval did not apply because HR changed them in the meantime. */
    conflicts: string[];
    changes: ProfileChangeField[];
    canDecide: boolean;
    canWithdraw: boolean;
};

/** A field of the "Ajukan perubahan data" form. */
export type ProfileFormField = {
    name: string;
    label: string;
    group: string;
    type: 'text' | 'email' | 'textarea' | 'photo';
};
