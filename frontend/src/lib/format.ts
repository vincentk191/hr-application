/**
 * How money, dates and the clock are written, in one place. Every company is on the same timezone for now, so it is a
 * constant here rather than something the server sends; if companies ever differ, this is the one place to change.
 */

/** The timezone the business runs on: "today" and "now" are read in it, not in the browser's. */
export const BUSINESS_TIMEZONE = 'Asia/Jakarta';

const rupiahFormat = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const numberFormat = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
});

/** Whole rupiah as "Rp 4.500.000"; a missing amount is a dash. Money here is integer rupiah, never a float. */
export function formatRupiah(value: number | null | undefined): string {
    return value === null || value === undefined
        ? '—'
        : rupiahFormat.format(value);
}

/** A whole number with the dots between thousands: "4.500.000". */
export function formatNumber(value: number): string {
    return numberFormat.format(value);
}

const dayFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});

const dateFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

/** The calendar day an ISO date or date-time names, as a Date at midnight UTC so no timezone can move it. */
function calendarDay(iso: string): Date {
    return new Date(`${iso.slice(0, 10)}T00:00:00Z`);
}

/**
 * "13 Nov" from an ISO date or date-time, read as the calendar day it names (the server has already put it in the
 * company's timezone, so a clock part is ignored). A missing date is a dash.
 */
export function formatDay(iso: string | null | undefined): string {
    return iso ? dayFormat.format(calendarDay(iso)) : '—';
}

/** "1 Okt 2026": the same, with the year. */
export function formatDate(iso: string | null | undefined): string {
    return iso ? dateFormat.format(calendarDay(iso)) : '—';
}

const instantParts = new Intl.DateTimeFormat('en-CA', {
    timeZone: BUSINESS_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
});

/** The business timezone's wall clock at an instant, as numbers. */
function businessParts(at: Date): Record<string, number> {
    const parts: Record<string, number> = {};

    for (const part of instantParts.formatToParts(at)) {
        if (part.type !== 'literal') {
            parts[part.type] = Number(part.value);
        }
    }

    return parts;
}

const pad = (value: number) => String(value).padStart(2, '0');

/** Today as "2026-11-25" in the business timezone, whatever the browser's is. */
export function businessToday(at: Date = new Date()): string {
    const { year, month, day } = businessParts(at);

    return `${year}-${pad(month)}-${pad(day)}`;
}

/**
 * The business timezone's wall clock right now, as a Date whose local fields read that time. Times from the server
 * ("2026-11-06T15:14", the company's time) are read into Dates the same way, so the two compare correctly on a phone
 * set to another timezone, which comparing with `new Date()` would not.
 */
export function businessNow(at: Date = new Date()): Date {
    const { year, month, day, hour, minute, second } = businessParts(at);

    return new Date(year, month - 1, day, hour, minute, second);
}

const instantDate = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: BUSINESS_TIMEZONE,
});

/** A moment as "25 Nov 2026", or "25 Nov 2026, 14:05" with `time`, in the business timezone. */
export function formatInstant(
    value: string | Date,
    { time = false }: { time?: boolean } = {},
): string {
    const at = typeof value === 'string' ? new Date(value) : value;
    const date = instantDate.format(at);

    if (!time) {
        return date;
    }

    const { hour, minute } = businessParts(at);

    return `${date}, ${pad(hour)}:${pad(minute)}`;
}

/** Whole days from an ISO date to today in the business timezone: 0 for today, never negative. */
export function daysSince(iso: string): number {
    const today = Date.parse(`${businessToday()}T00:00:00Z`);
    const then = Date.parse(`${iso.slice(0, 10)}T00:00:00Z`);

    return Math.max(0, Math.round((today - then) / 86_400_000));
}
