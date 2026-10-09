/**
 * Wear a company's colour: the accent colour the whole app uses (buttons, the current menu item,
 * focus rings) is replaced for as long as the colour is set, and put back when it is not.
 *
 * The text on top of the colour is black or white, whichever reads better, so a pale brand colour
 * does not give white-on-yellow buttons.
 */
const OVERRIDDEN = [
    '--primary',
    '--primary-foreground',
    '--sidebar-primary',
    '--sidebar-primary-foreground',
    '--ring',
    '--sidebar-ring',
] as const;

/** Relative luminance of a "#rrggbb" colour, 0 (black) to 1 (white). */
function luminance(hex: string): number {
    const channel = (start: number) => {
        const value = parseInt(hex.slice(start, start + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
}

export function applyBrandColor(color: string | null | undefined): void {
    const root = document.documentElement;

    if (!color || !/^#[0-9a-f]{6}$/i.test(color)) {
        OVERRIDDEN.forEach((name) => root.style.removeProperty(name));

        return;
    }

    const on = luminance(color) > 0.45 ? '#111111' : '#ffffff';

    root.style.setProperty('--primary', color);
    root.style.setProperty('--primary-foreground', on);
    root.style.setProperty('--sidebar-primary', color);
    root.style.setProperty('--sidebar-primary-foreground', on);
    root.style.setProperty('--ring', `${color}73`);
    root.style.setProperty('--sidebar-ring', `${color}73`);
}
