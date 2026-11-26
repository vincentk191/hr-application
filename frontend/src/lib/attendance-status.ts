import type { DayStatus } from '@/types/attendance';

/** One colour per day status, shared by the calendar, the profile tab and the issues screen. */
export const STATUS_STYLE: Record<DayStatus, string> = {
    present: 'bg-success/15 text-success-text',
    late: 'bg-pending/15 text-pending-text',
    early_leave: 'bg-caution/15 text-caution-text',
    absent: 'bg-danger/15 text-danger-text',
    leave: 'bg-info/15 text-info-text',
    off: 'bg-muted text-muted-foreground',
    no_data: 'hatch border border-dashed text-muted-foreground bg-transparent',
};

/** The same colours as a bordered tile carrying the status letter, as the week grid and its legend draw it. */
export const STATUS_TILE: Record<DayStatus, string> = {
    present: 'border-success/40 bg-success/15 text-success-text',
    late: 'border-pending/40 bg-pending/15 text-pending-text',
    early_leave: 'border-caution/40 bg-caution/15 text-caution-text',
    absent: 'border-danger/40 bg-danger/15 text-danger-text',
    leave: 'border-info/40 bg-info/15 text-info-text',
    off: 'border-border bg-muted/50 text-muted-foreground',
    no_data:
        'hatch border-dashed border-border bg-transparent text-muted-foreground',
};

export const STATUS_LABEL: Record<DayStatus, string> = {
    present: 'Hadir',
    late: 'Terlambat',
    early_leave: 'Pulang cepat',
    absent: 'Tidak hadir',
    leave: 'Cuti',
    off: 'Libur',
    no_data: 'Belum ada data',
};

/** Minutes as "7j 30m". */
export function formatMinutes(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }

    return `${Math.floor(minutes / 60)}j ${String(minutes % 60).padStart(2, '0')}m`;
}
