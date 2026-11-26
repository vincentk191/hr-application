/**
 * Marks the fields inside an element as ones that still need filling in: a red border (and a faint ring) on every input, select
 * and textarea within it. A form uses it once the person has tried to go on or send, so the missing fields point themselves
 * out, instead of a line of text naming them.
 */
export const INVALID_FIELDS =
    '[&_input]:border-destructive [&_select]:border-destructive [&_textarea]:border-destructive [&_input]:ring-1 [&_select]:ring-1 [&_textarea]:ring-1 [&_input]:ring-destructive/30 [&_select]:ring-destructive/30 [&_textarea]:ring-destructive/30';

/** Scrolls the first field that is marked invalid (`data-invalid`) into the middle of the screen. */
export function showFirstInvalid(): void {
    document
        .querySelector('[data-invalid="true"]')
        ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}
