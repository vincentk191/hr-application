/**
 * "13 Nov" from an ISO date or date-time, read as the calendar day it names (the server has already put it in the
 * company's timezone, so the clock part is ignored). A missing date is a dash.
 */
export function shortDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(`${iso.slice(0, 10)}T00:00:00Z`));
}
