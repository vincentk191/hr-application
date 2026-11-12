import type { MouseEvent } from 'react';

/**
 * For a link whose target is a page that opens a side panel on arrival (a request on the Pengajuan page, say): a plain
 * click opens the panel over the page the person is already on instead, while a click with a modifier key, or a middle
 * click, still follows the link, so "open in a new tab" keeps working.
 */
export function openInPlace(event: MouseEvent, open: () => void): void {
    if (
        event.button > 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    event.preventDefault();
    open();
}
