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

export type SyncAgent = {
    id: number;
    name: string;
    lastRunAt: string | null;
    lastSeenAt: string | null;
    lastHeartbeatAt: string | null;
    lastIp: string | null;
    /** No completed report for over 3 hours during working hours. */
    stale: boolean;
    syncRequested: boolean;
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
    /** Title of the holiday or company event covering this person that day, if any. */
    event: string | null;
};

export type CalendarRow = {
    id: number;
    name: string;
    number: string;
    department: string | null;
    position: string | null;
    /** Name of the employee's supervisor. */
    manager: string | null;
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
    attended: number;
    expected: number;
    /** Attendance rate in percent, null while the month has no data. */
    rate: number | null;
    workdays: number;
    /** Person-days. */
    leaveDays: number;
    late: number;
    absent: number;
};

/** One day of the week and month views, for the people the filters select. */
export type CalendarDaySummary = {
    date: string;
    day: number;
    weekday: string;
    isWorkday: boolean;
    /** The scan log reaches this day; otherwise the counts mean nothing. */
    covered: boolean;
    attended: number;
    late: number;
    earlyLeave: number;
    absent: number;
    leave: number;
    /** Name of the holiday or event covering everyone, if any. */
    holiday: string | null;
};

/** One approved leave inside the range on screen, for the calendar's side panel. */
export type LeaveEntry = {
    id: number;
    employeeId: number;
    name: string | null;
    department: string | null;
    type: string;
    from: string;
    to: string;
    /** "09:00–11:00" when the request names a time of day. */
    timeframe: string | null;
};

export type CalendarEventRow = {
    id: number;
    title: string;
    kind: 'holiday' | 'event';
    from: string;
    to: string;
    scope: string;
    canDelete: boolean;
};

export type EventFormOptions = {
    canBeGroupWide: boolean;
    companies: { id: number; code: string; name: string }[];
    departments: { id: number; company_id: number; name: string }[];
};

export type StatusOption = { value: DayStatus; label: string; code: string };

export type CalendarFilters = {
    departments: string[];
    statuses: DayStatus[];
    search: string;
    team: number | null;
    leaveType: string | null;
};

export type TimesheetScan = {
    id: number;
    time: string;
    source: string;
    isManual: boolean;
    reason: string | null;
    voided: boolean;
    voidReason: string | null;
};

export type TimesheetDay = {
    date: string;
    weekday: string;
    status: DayStatus;
    event: string | null;
    in: string | null;
    out: string | null;
    minutes: number | null;
    scans: TimesheetScan[];
};

/** One employee's month of attendance, as sent to the profile's attendance tab (EMP-ATT-003). */
export type Timesheet = {
    period: 'month';
    month: string;
    label: string;
    prev: string;
    next: string;
    dataThrough: string | null;
    days: TimesheetDay[];
    totals: {
        present: number;
        late: number;
        early_leave: number;
        absent: number;
        leave: number;
        minutes: number;
    };
};

/** One employee's year at a glance: a summary per month (EMP-ATT-003). */
export type YearTimesheet = {
    period: 'year';
    year: number;
    label: string;
    prev: number;
    next: number;
    dataThrough: string | null;
    months: {
        month: number;
        /** "YYYY-MM", to open that month. */
        key: string;
        label: string;
        present: number;
        late: number;
        early_leave: number;
        absent: number;
        leave: number;
        minutes: number;
        rate: number | null;
    }[];
    totals: {
        present: number;
        late: number;
        early_leave: number;
        absent: number;
        leave: number;
        minutes: number;
        rate: number | null;
    };
};
