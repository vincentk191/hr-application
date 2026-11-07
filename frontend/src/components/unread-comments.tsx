import { MessageSquare } from 'lucide-react';

/**
 * A red marker for a row of a list whose comment thread has comments the viewer has not read yet, with how many. Shown
 * beside the row's title so the cases with something to read stand out without opening each one. Nothing is drawn when
 * there is nothing new.
 */
export function UnreadComments({ count }: { count: number }) {
    if (count <= 0) {
        return null;
    }

    return (
        <span
            role="img"
            aria-label={`${count} komentar baru`}
            title={`${count} komentar baru`}
            className="inline-flex items-center gap-1 rounded-full bg-destructive px-2 py-0.5 align-middle text-[11px] leading-4 font-semibold text-destructive-foreground"
            data-test="unread-comments"
        >
            <MessageSquare className="size-3" aria-hidden />
            {count}
        </span>
    );
}
