import { useEffect } from 'react';

/** A field that brings up the on-screen keyboard (not a checkbox, button, file picker and the like). */
const TEXT_FIELD =
    'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=reset]):not([type=file]):not([type=range]):not([type=color]):not([type=image]), textarea, select, [contenteditable=""], [contenteditable="true"]';

/** Less than this much lost height is a browser bar sliding away, not a keyboard. */
const KEYBOARD_MIN_HEIGHT = 120;

function focusedField(): HTMLElement | null {
    const active = document.activeElement;

    return active instanceof HTMLElement && active.matches(TEXT_FIELD)
        ? active
        : null;
}

/**
 * Keeps the app usable while the on-screen keyboard is up on a phone.
 *
 * Browsers disagree about what the keyboard does: Android Chrome (with `interactive-widget=resizes-content`) shrinks
 * the page, while iOS Safari leaves the page as it is, covers its lower part and pans the whole view to reach the
 * field. So this watches the visual viewport, the part of the screen that is actually visible, and while a text
 * field is focused and the keyboard is open it:
 * - sets `data-keyboard="open"` on <html> and publishes `--vv-top` and `--vv-height`, which the CSS uses to fit
 *   pop-ups and drawers exactly into the visible area, above the keyboard, instead of letting it cover them;
 * - brings the focused field into view if a scrolling area has left it behind the keyboard.
 *
 * Nothing is set while the keyboard is closed, so layouts are untouched then.
 */
export function useKeyboardViewport(): void {
    useEffect(() => {
        const viewport = window.visualViewport;

        if (!viewport) {
            return;
        }

        const root = document.documentElement;
        // The tallest visible area seen with no field focused: the screen without the keyboard.
        let baseline = viewport.height;
        let open = false;
        let frame = 0;
        let timer = 0;

        const apply = () => {
            const field = focusedField();

            if (field === null) {
                baseline = viewport.height;
            }

            const nowOpen =
                field !== null &&
                viewport.scale <= 1.01 &&
                baseline - viewport.height > KEYBOARD_MIN_HEIGHT;

            if (nowOpen) {
                root.dataset.keyboard = 'open';
                root.style.setProperty('--vv-top', `${viewport.offsetTop}px`);
                root.style.setProperty('--vv-height', `${viewport.height}px`);
            } else {
                delete root.dataset.keyboard;
                root.style.removeProperty('--vv-top');
                root.style.removeProperty('--vv-height');
            }

            if (nowOpen && field) {
                // After the layout has settled around the new height; "nearest" does nothing if it is already visible.
                window.clearTimeout(timer);
                timer = window.setTimeout(
                    () => field.scrollIntoView({ block: 'nearest' }),
                    100,
                );
            }

            open = nowOpen;
        };

        const schedule = () => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(apply);
        };

        // Focus moves between fields through a moment with none focused, so wait before deciding the keyboard went.
        const onFocusOut = () => window.setTimeout(schedule, 60);

        viewport.addEventListener('resize', schedule);
        viewport.addEventListener('scroll', schedule);
        document.addEventListener('focusin', schedule);
        document.addEventListener('focusout', onFocusOut);
        window.addEventListener('orientationchange', schedule);

        return () => {
            viewport.removeEventListener('resize', schedule);
            viewport.removeEventListener('scroll', schedule);
            document.removeEventListener('focusin', schedule);
            document.removeEventListener('focusout', onFocusOut);
            window.removeEventListener('orientationchange', schedule);
            window.cancelAnimationFrame(frame);
            window.clearTimeout(timer);

            if (open) {
                delete root.dataset.keyboard;
                root.style.removeProperty('--vv-top');
                root.style.removeProperty('--vv-height');
            }
        };
    }, []);
}

/** Mounts {@link useKeyboardViewport} once, for the whole app. */
export function KeyboardViewport(): null {
    useKeyboardViewport();

    return null;
}
