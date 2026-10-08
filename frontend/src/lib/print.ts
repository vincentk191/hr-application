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
