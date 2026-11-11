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
    /** The Cabang sends a rekapitulasi: no scanner, so no scans to show. */
    viaRekap: boolean;
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

/** One thing waiting on this person's decision, as a line of its kind's list. */
export type AdminWaitingEntry = {
    id: number;
    title: string;
    /** What kind of it: the leave type, the reference. */
    chip: string | null;
    detail: string | null;
    subdetail: string | null;
    /** How long it has waited, "4 hari 20 jam". */
    waiting: string;
    /** Opens the item itself. */
    href: string;
};

/** What waits on this person's decision, by kind (HR and Management, or anyone with something waiting). */
export type AdminWaiting = {
    total: number;
    items: {
        key: 'leave' | 'requisition' | 'offer' | 'offboarding' | 'profile';
        label: string;
        /** All of them; `entries` holds only the first few. */
        count: number;
        entries: AdminWaitingEntry[];
    }[];
};

/** One person, or one event, on one date of the admin calendar. */
export type AdminCalendarEntry = {
    date: string;
    kind: 'leave' | 'interview' | 'holiday' | 'event';
    title: string;
    detail: string;
    /** "10:00" for an interview. */
    time: string | null;
    /** For leave: still waiting, or approved. */
    status: 'submitted' | 'approved' | null;
    href: string | null;
};

/** A month of leave, interviews and holidays, for the people who run HR. */
export type AdminCalendar = {
    /** "YYYY-MM". */
    month: string;
    label: string;
    prev: string;
    next: string;
    entries: AdminCalendarEntry[];
};

/** How recruitment is going: running requisitions by stage, interviews ahead, and links that lapsed unfilled. */
export type RecruitmentOverview = {
    open: number;
    stages: { stage: string; label: string; count: number }[];
    interviewsThisWeek: number;
    upcoming: {
        id: number;
        name: string;
        when: string | null;
        requisitionId: number;
        positionName: string | null;
    }[];
    lapsedLinks: number;
    lapsed: {
        id: number;
        name: string;
        expiredOn: string | null;
        requisitionId: number;
        positionName: string | null;
    }[];
};
