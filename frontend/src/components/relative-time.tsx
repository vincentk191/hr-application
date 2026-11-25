import { formatInstant } from '@/lib/format';
import { relativeTime } from '@/lib/relative-time';

/**
 * A moment as "5 menit lalu", with the exact day and time in a tooltip ("25 Nov 2026, 14:05") so a vague age can always
 * be pinned down. Renders a `<time>` element, which tells assistive technology and the browser what it is.
 */
export function RelativeTime({
    iso,
    className,
}: {
    iso: string | null;
    className?: string;
}) {
    if (!iso) {
        return null;
    }

    return (
        <time
            dateTime={iso}
            title={formatInstant(iso, { time: true })}
            className={className}
        >
            {relativeTime(iso)}
        </time>
    );
}
