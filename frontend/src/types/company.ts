/**
 * What the app wears for the company being viewed (shared on every page). The colour is "#rrggbb" or
 * null for the default look; the logo is null for the combined view.
 */
export type Branding = {
    name: string | null;
    color: string | null;
    logoUrl: string | null;
};

export type CompanyOption = {
    id: number;
    code: string;
    name: string;
};

/**
 * State for the header company switcher (CORE-NAV-001), shared on every page.
 *
 * `selected` is either a company id as a string or the combined-view sentinel `'all'`.
 * `locked` means the signed-in account is pinned to one company; it only hides the control,
 * the real guarantee is server-side (CORE-NAV-004).
 */
export type CompanySwitcher = {
    companies: CompanyOption[];
    selected: string;
    canCombine: boolean;
    locked: boolean;
};
