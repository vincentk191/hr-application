import type { MouseEvent } from 'react';

/**
 * A plain click on an employee opens the profile drawer; a modified click (new tab, new window)
 * still follows the link to the full profile.
 */
export function openInDrawer(
    event: MouseEvent,
    id: number,
    onOpen?: (id: number) => void,
): void {
    if (
        !onOpen ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.button !== 0
    ) {
        return;
    }

    event.preventDefault();
    onOpen(id);
}
