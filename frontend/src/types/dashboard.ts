/** How a badge on the dashboard is painted. */
export type Tone = 'good' | 'warn' | 'bad' | 'info' | 'muted';

/** One step of a request's way to a decision; "Diajukan" first, always done. */
export type RequestStep = {
    label: string;
    state: 'done' | 'open' | 'todo';
};

export type OpenRequest = {
    id: number;
    reference: string;
    type: string;
    /** "03–07 Nov" */
    dates: string;
    /** "5 hari" or "4 jam" */
    size: string;
    submittedOn: string | null;
    /** Who the request is waiting on now: "Atasan" or "HR". */
    waitingOn: string | null;
    steps: RequestStep[];
};

export type QuickAction = {
    key: 'cuti' | 'izin' | 'lembur' | 'lainnya';
    label: string;
    hint: string;
    /** The request type the form opens on, when this installation has one that fits. */
    type: string | null;
};

export type TodayAttendance = {
    status: string;
    statusLabel: string;
    tone: Tone;
    /** "08:02", null before the first scan. */
    in: string | null;
    /** Null until a second scan, which is the departure. */
    out: string | null;
    minutes: number | null;
    workStart: string;
    workEnd: string;
    isWorkday: boolean;
    /** Minutes after midnight, scanner time, when the page was built; the page counts on from there. */
    nowMinutes: number;
};

export type Overview = {
    /** Null when the attendance module is switched off. */
    attendance: TodayAttendance | null;
    month: {
        label: string;
        rate: number | null;
        attended: number;
        expected: number;
    } | null;
    /** Working days of approved leave this year. There is no entitlement in the app, so no maximum or balance. */
    leaveTaken: { days: number; year: number };
    /**
     * The working days of confirmed (approved) sick leave, this year and last, as `YYYY-MM-DD`, oldest first; `year`
     * is this year and `thisYear` how many of the dates fall in it.
     */
    sickDays: { dates: string[]; year: number; thisYear: number };
    openRequests: OpenRequest[];
    quickActions: QuickAction[];
};

/** The greeting at the top, for every account, with or without an employee record. */
export type Intro = {
    name: string;
    greeting: string;
    dateLabel: string;
    year: number;
    /** Minutes after midnight, scanner time, when the page was built; the page counts on from there. */
    nowMinutes: number;
};
