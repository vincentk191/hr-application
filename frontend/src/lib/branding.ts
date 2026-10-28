/**
 * Wear a company's colour: the accent colour the whole app uses (buttons, the current menu item,
 * focus rings) is replaced for as long as the colour is set, and put back when it is not.
 *
 * Only the `--brand*` variables are set here. `css/app.css` reads them for the light theme
 * (`--brand`) and the dark one (`--brand-dark`), so switching theme needs no script: the right
 * value is already in place. The dark one is the chosen colour made lighter, only as far as it must
 * be to stay readable on a dark surface: a black brand colour would otherwise vanish from the
 * menus, and a colour that is already light enough is left exactly as chosen.
 *
 * The text on top of the colour is black or white, whichever reads better, so a pale brand colour
 * does not give white-on-yellow buttons.
 */
const VARIABLES = [
    '--brand',
    '--brand-fg',
    '--brand-ring',
    '--brand-dark',
    '--brand-fg-dark',
    '--brand-ring-dark',
] as const;

/**
 * The relative luminance of the lightest surface that text sits on in the dark theme (the hover
 * and selected backgrounds, `oklch(0.27 ...)`). Measuring against it, not the darkest, means a
 * colour that reads there reads everywhere else too.
 */
const DARK_SURFACE_LUMINANCE = 0.02;

/** The contrast ratio the dark variant must reach against that surface (WCAG AA asks 4.5 for small text). */
const DARK_CONTRAST = 5;

const DARK_TEXT = '#111111';

const WHITE_TEXT = '#ffffff';

const COLOR_PATTERN = /^#[0-9a-f]{6}$/i;

type Rgb = [number, number, number];

function toRgb(hex: string): Rgb {
    return [1, 3, 5].map((start) =>
        parseInt(hex.slice(start, start + 2), 16),
    ) as Rgb;
}

function toHex([red, green, blue]: Rgb): string {
    return `#${[red, green, blue]
        .map((value) =>
            Math.round(Math.min(255, Math.max(0, value)))
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;
}

/** Relative luminance of a "#rrggbb" colour, 0 (black) to 1 (white). */
export function luminance(hex: string): number {
    const [red, green, blue] = toRgb(hex).map((byte) => {
        const value = byte / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/** WCAG contrast ratio between two luminances, 1 (none) to 21 (black on white). */
export function contrast(first: number, second: number): number {
    const [lighter, darker] =
        first > second ? [first, second] : [second, first];

    return (lighter + 0.05) / (darker + 0.05);
}

function toHsl([red, green, blue]: Rgb): [number, number, number] {
    const [r, g, b] = [red / 255, green / 255, blue / 255];
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const lightness = (max + min) / 2;

    if (max === min) {
        return [0, 0, lightness];
    }

    const delta = max - min;
    const saturation =
        lightness > 0.5 ? delta / (2 - max - min) : delta / (max + min);
    const hue =
        max === r
            ? (g - b) / delta + (g < b ? 6 : 0)
            : max === g
              ? (b - r) / delta + 2
              : (r - g) / delta + 4;

    return [hue * 60, saturation, lightness];
}

function fromHsl(hue: number, saturation: number, lightness: number): Rgb {
    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const channel = (offset: number) => {
        const position = (offset + hue / 30) % 12;

        return (
            lightness -
            (chroma / 2) * Math.max(-1, Math.min(position - 3, 9 - position, 1))
        );
    };

    return [channel(0) * 255, channel(8) * 255, channel(4) * 255];
}

/**
 * The colour to wear in the dark theme: the chosen one when it already reads on a dark surface,
 * otherwise the same hue and saturation made lighter until it does (black becomes a mid grey).
 */
export function darkVariant(hex: string): string {
    if (contrast(luminance(hex), DARK_SURFACE_LUMINANCE) >= DARK_CONTRAST) {
        return hex.toLowerCase();
    }

    const [hue, saturation, start] = toHsl(toRgb(hex));

    for (let lightness = start; lightness < 1; lightness += 0.005) {
        const candidate = toHex(fromHsl(hue, saturation, lightness));

        if (
            contrast(luminance(candidate), DARK_SURFACE_LUMINANCE) >=
            DARK_CONTRAST
        ) {
            return candidate;
        }
    }

    return WHITE_TEXT;
}

/** Text on the light theme's colour: black on a pale colour, white on the rest. */
export function textOn(hex: string): string {
    return luminance(hex) > 0.45 ? DARK_TEXT : WHITE_TEXT;
}

/** Text on the dark theme's colour: whichever of black and white contrasts more. */
export function textOnDark(hex: string): string {
    const own = luminance(hex);

    return contrast(own, luminance(DARK_TEXT)) >=
        contrast(own, luminance(WHITE_TEXT))
        ? DARK_TEXT
        : WHITE_TEXT;
}

export function applyBrandColor(color: string | null | undefined): void {
    const root = document.documentElement;

    if (!color || !COLOR_PATTERN.test(color)) {
        VARIABLES.forEach((name) => root.style.removeProperty(name));

        return;
    }

    const dark = darkVariant(color);

    root.style.setProperty('--brand', color);
    root.style.setProperty('--brand-fg', textOn(color));
    root.style.setProperty('--brand-ring', `${color}73`);
    root.style.setProperty('--brand-dark', dark);
    root.style.setProperty('--brand-fg-dark', textOnDark(dark));
    root.style.setProperty('--brand-ring-dark', `${dark}73`);
}
