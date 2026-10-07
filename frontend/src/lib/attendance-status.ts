import type { DayStatus } from '@/types/attendance';

/** One colour per day status, shared by the calendar, the profile tab and the issues screen. */
export const STATUS_STYLE: Record<DayStatus, string> = {
    present:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    late: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    early_leave:
        'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
    absent: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    leave: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    off: 'bg-muted text-muted-foreground',
    no_data: 'border border-dashed text-muted-foreground bg-transparent',
};

export const STATUS_LABEL: Record<DayStatus, string> = {
    present: 'Hadir',
    late: 'Terlambat',
    early_leave: 'Pulang cepat',
    absent: 'Tidak hadir',
    leave: 'Cuti',
    off: 'Libur / tidak ada scan',
    no_data: 'Belum ada data',
};

/** Minutes as "7j 30m". */
export function formatMinutes(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }

    return `${Math.floor(minutes / 60)}j ${String(minutes % 60).padStart(2, '0')}m`;
}
