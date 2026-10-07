export type CaptureState = 'never' | 'current' | 'late';

/** How current the scanner data is, for the people who capture it (ATT-DEV-001). */
export type CaptureStatus = {
    state: CaptureState;
    lastScanAt: string | null;
    /** The date the data should run through by now: the previous working day. */
    expectedThrough: string;
    unmapped: number;
};

export type AttendanceImportRow = {
    id: number;
    at: string | null;
    by: string | null;
    file: string | null;
    deviceSn: string;
    rowsTotal: number;
    accepted: number;
    duplicates: number;
    unmapped: number;
    rejected: number;
    firstScanAt: string | null;
    lastScanAt: string | null;
    errors: string[];
};

/** A scanner ID with punches waiting for an employee. */
export type WaitingId = {
    deviceSn: string;
    pin: string;
    punches: number;
    firstAt: string;
    lastAt: string;
    suggestedEmployeeId: number | null;
};

export type EnrolMapRow = {
    id: number;
    deviceSn: string;
    pin: string;
    employee: string | null;
    employeeNumber: string | null;
    companyCode: string | null;
    validFrom: string | null;
    validTo: string | null;
};

export type EmployeeOption = { id: number; label: string; number: string };

export type DayStatus =
    | 'present'
    | 'late'
    | 'early_leave'
    | 'absent'
    | 'leave'
    | 'off'
    | 'no_data';

export type CalendarView = 'day' | 'week' | 'month' | 'year';

export type DayRecord = {
    status: DayStatus;
    /** Local "HH:MM" of the first scan, or null. */
    in: string | null;
    /** Local "HH:MM" of the last scan when there was more than one. */
    out: string | null;
    punches: number;
};

export type CalendarRow = {
    id: number;
    name: string;
    number: string;
    department: string | null;
    /** False when no scanner ID is mapped to this person, so nothing can be recorded for them. */
    hasScannerId: boolean;
    days: Record<string, DayRecord>;
};

export type CalendarDate = {
    date: string;
    day: number;
    weekday: string;
    isWorkday: boolean;
};

export type StatusCounts = Record<DayStatus, number>;

export type CalendarMonth = {
    month: number;
    label: string;
    firstDate: string;
    counts: StatusCounts;
};

export type LeaveEntry = {
    id: number;
    employeeId: number;
    name: string | null;
    type: string;
    from: string;
    to: string;
};

export type StatusOption = { value: DayStatus; label: string; code: string };

export type CalendarFilters = {
    departments: number[];
    statuses: DayStatus[];
    search: string;
};
