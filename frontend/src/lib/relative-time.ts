/**
 * "5 menit lalu", "kemarin", or the date for anything older: enough for a notification list without
 * pulling in a date library.
 */
export function relativeTime(
    iso: string | null,
    now: Date = new Date(),
): string {
    if (!iso) {
        return '';
    }

    const then = new Date(iso);
    const minutes = Math.round((now.getTime() - then.getTime()) / 60000);

    if (minutes < 1) {
        return 'baru saja';
    }

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours} jam lalu`;
    }

    const days = Math.round(hours / 24);

    if (days === 1) {
        return 'kemarin';
    }

    if (days < 7) {
        return `${days} hari lalu`;
    }

    return then.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
