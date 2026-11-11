import { useEffect } from 'react';

/**
 * Sets the paper for the page that is open: reports are landscape (the app's default, for the wide month grid), but a letter
 * is A4 portrait with room around the text. A `@page` rule cannot be chosen by a selector, so the page adds its own for as long
 * as it is shown; being later in the document than the stylesheet's, it wins.
 */
export function usePrintPage(
    orientation: 'portrait' | 'landscape',
    margin = '20mm',
): void {
    useEffect(() => {
        const style = document.createElement('style');

        style.dataset.printPage = orientation;
        style.textContent = `@media print { @page { size: A4 ${orientation}; margin: ${margin}; } }`;
        document.head.append(style);

        return () => style.remove();
    }, [orientation, margin]);
}

/**
 * Print the current page as a report, in the light theme whatever the screen is using.
 *
 * Paper is white and browsers drop backgrounds when printing, so a dark-theme page prints as pale
 * text on nothing. Taking the `dark` class off the root for the length of the print, and putting
 * it back afterwards, gives a normal black-on-white report without a second set of styles.
 */
export function printReport(): void {
    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');

    const restore = () => {
        if (wasDark) {
            root.classList.add('dark');
        }

        window.removeEventListener('afterprint', restore);
    };

    window.addEventListener('afterprint', restore);
    root.classList.remove('dark');

    window.print();

    // print() blocks until the dialog closes in most browsers; this covers those that do not.
    setTimeout(restore, 1000);
}
