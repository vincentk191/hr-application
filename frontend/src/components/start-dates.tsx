import { Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { formatDay } from '@/lib/format';
import { show } from '@/routes/recruitment';
import type { StartDateRow } from '@/types/attendance';

/**
 * The target start dates of roles still open that fall in the calendar's range, for HR and the new hire's direct
 * superior. Not holidays: attendance is judged as usual on these days. Left out entirely when there are none.
 */
export function StartDates({ items }: { items: StartDateRow[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section className="surface p-4" data-test="start-dates">
            <h3 className="flex items-center gap-3 font-medium">
                <UserPlus className="size-4 shrink-0" aria-hidden />
                <span>Mulai kerja</span>
            </h3>
            <ul className="mt-2 divide-y text-sm">
                {items.map((item) => (
                    <li key={item.id} className="py-2">
                        <Link
                            href={show(item.id).url}
                            className="font-medium hover:underline"
                        >
                            {item.title}
                        </Link>
                        <div className="text-xs text-muted-foreground">
                            {formatDay(item.date)} · {item.reference}
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
