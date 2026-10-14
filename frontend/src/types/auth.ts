export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    canAccessAdminPanel: boolean;
    /** The role shown under the name in the header. */
    roleLabel: string | null;
    canViewEmployees: boolean;
    /** HR: may open the review list of profile change requests. */
    canReviewProfileChanges: boolean;
    /** May load scanner logs and map scanner IDs (parent-wide accounts with the permission). */
    canManageAttendance: boolean;
    /** May open the attendance calendar. */
    canViewAttendance: boolean;
    /** The signed-in person's own dossier record, if they have one. */
    employeeId: number | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
