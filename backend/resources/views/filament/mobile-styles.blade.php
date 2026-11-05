{{-- The phone layout of the admin panel (see mobile-tab-bar). Plain CSS: the panel has no theme build of its own. --}}
@verbatim
<style>
    /* Nothing of the phone navigation shows from tablet width up, where the side menu is used. */
    .fi-mobile-bar {
        display: none;
    }

    @media (max-width: 767.98px) {
        /* The drawer and the button that opens it give way to the bar along the bottom. */
        .fi-sidebar,
        .fi-sidebar-close-overlay,
        .fi-topbar-open-sidebar-btn,
        .fi-topbar-close-sidebar-btn,
        .fi-layout-sidebar-toggle-btn-ctn {
            display: none !important;
        }

        .fi-main-ctn {
            padding-bottom: calc(4.5rem + env(safe-area-inset-bottom));
        }

        .fi-mobile-bar {
            display: block;
            position: fixed;
            inset-inline: 0;
            bottom: 0;
            z-index: 30;
            padding-bottom: env(safe-area-inset-bottom);
            border-top: 1px solid var(--gray-200);
            background: #fff;
        }

        .dark .fi-mobile-bar {
            border-top-color: rgb(255 255 255 / 0.1);
            background: var(--gray-900);
        }

        .fi-mobile-bar-list {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            height: 4rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .fi-mobile-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.125rem;
            width: 100%;
            height: 100%;
            position: relative;
            color: var(--gray-500);
            font-size: 0.6875rem;
            font-weight: 500;
            line-height: 1.2;
            background: none;
            border: 0;
            cursor: pointer;
        }

        .dark .fi-mobile-tab {
            color: var(--gray-400);
        }

        .fi-mobile-tab.fi-active {
            color: var(--primary-600);
        }

        .dark .fi-mobile-tab.fi-active {
            color: var(--primary-400);
        }

        .fi-mobile-tab-pill {
            position: relative;
            display: inline-flex;
            padding: 0.25rem 1rem;
            border-radius: 9999px;
        }

        .fi-mobile-tab-pill svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .fi-mobile-tab.fi-active .fi-mobile-tab-pill {
            background: color-mix(in oklab, var(--primary-500) 15%, transparent);
        }

        .fi-mobile-tab-badge,
        .fi-mobile-sheet-badge {
            min-width: 1rem;
            padding: 0 0.25rem;
            border-radius: 9999px;
            background: var(--primary-600);
            color: #fff;
            font-size: 0.625rem;
            font-weight: 600;
            line-height: 1rem;
            text-align: center;
        }

        .fi-mobile-tab-badge {
            position: absolute;
            top: -0.125rem;
            inset-inline-end: 0.25rem;
        }

        /* The raised button in the middle, back to the dashboard. */
        .fi-mobile-tab-home-button {
            position: absolute;
            top: -1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 9999px;
            background: var(--primary-600);
            color: #fff;
            box-shadow: 0 0 0 4px #fff, 0 6px 14px rgb(0 0 0 / 0.2);
        }

        .dark .fi-mobile-tab-home-button {
            box-shadow: 0 0 0 4px var(--gray-900), 0 6px 14px rgb(0 0 0 / 0.5);
        }

        .fi-mobile-tab-home-button svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .fi-mobile-tab-home-label {
            margin-top: 2rem;
        }

        /* "Lainnya": a sheet rising from the bottom with every other page. */
        .fi-mobile-sheet-ctn {
            position: fixed;
            inset: 0;
            z-index: 35;
        }

        .fi-mobile-sheet-overlay {
            position: absolute;
            inset: 0;
            background: rgb(0 0 0 / 0.5);
        }

        .fi-mobile-sheet {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            max-height: 85vh;
            border-radius: 1.5rem 1.5rem 0 0;
            background: #fff;
            padding-bottom: env(safe-area-inset-bottom);
        }

        .dark .fi-mobile-sheet {
            background: var(--gray-900);
        }

        .fi-mobile-sheet-enter {
            transition: transform 200ms ease-out;
        }

        .fi-mobile-sheet-enter-start {
            transform: translateY(100%);
        }

        .fi-mobile-sheet-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1rem 0.5rem 1.25rem;
        }

        .fi-mobile-sheet-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 800;
        }

        .fi-mobile-sheet-close {
            padding: 0.5rem;
            border-radius: 9999px;
            color: var(--gray-500);
        }

        .fi-mobile-sheet-body {
            overflow-y: auto;
            padding: 0 0.5rem 1rem;
        }

        .fi-mobile-sheet-body ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .fi-mobile-sheet-group {
            margin: 0.75rem 0.75rem 0.25rem;
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .fi-mobile-sheet-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .fi-mobile-sheet-link svg {
            flex: none;
            width: 1.25rem;
            height: 1.25rem;
            color: var(--gray-500);
        }

        .fi-mobile-sheet-link span:first-of-type {
            flex: 1;
        }

        .fi-mobile-sheet-link.fi-active {
            background: color-mix(in oklab, var(--primary-500) 10%, transparent);
            color: var(--primary-600);
        }

        .dark .fi-mobile-sheet-link.fi-active {
            color: var(--primary-400);
        }

        .fi-mobile-sheet-link.fi-active svg {
            color: inherit;
        }
    }

    /*
     * Table rows as cards. Filament stacks the cells of a row below 640px (Table::stackedOnMobile, set for every table)
     * and prints each column's heading in front of its value. These styles turn the stack into a card: the first
     * value is the title, every other value sits on a line with its heading, and the actions go along the bottom.
     */
    @media (max-width: 639.98px) {
        .fi-ta-ctn {
            background: transparent;
            box-shadow: none;
            --tw-ring-shadow: 0 0 #0000;
            overflow: visible;
        }

        /* Search and filters, and the page links, stay in a card of their own above and below the row cards. */
        .fi-ta-header-ctn,
        .fi-ta-ctn > .fi-ta-main > .fi-pagination {
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            background: #fff;
        }

        .dark .fi-ta-header-ctn,
        .dark .fi-ta-ctn > .fi-ta-main > .fi-pagination {
            border-color: rgb(255 255 255 / 0.1);
            background: var(--gray-900);
        }

        .fi-ta-header-toolbar {
            padding: 0.75rem;
            gap: 0.5rem;
            border: 0;
        }

        .fi-ta-header-toolbar > div:last-child {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .fi-ta-search-field {
            flex: 1;
        }

        .fi-ta-table-stacked-on-mobile > thead > tr.fi-ta-table-stacked-header-row,
        .fi-ta-table-stacked-on-mobile > thead > tr.fi-ta-table-stacked-header-row > td {
            border: 0;
        }

        .fi-ta-table-stacked-on-mobile > tbody {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding-block: 0.75rem;
        }

        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row {
            padding: 0.875rem 0;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        .dark .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row {
            border-color: rgb(255 255 255 / 0.1);
            background: var(--gray-900);
        }

        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:not(.fi-ta-selection-cell) {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-block: 0.25rem;
        }

        .fi-ta-table-stacked-on-mobile .fi-ta-cell-label {
            flex: none;
            color: var(--gray-500);
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .fi-ta-table-stacked-on-mobile .fi-ta-cell-content {
            min-width: 0;
            text-align: end;
        }

        /* The title of the card: the first value, without its heading. */
        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:first-child:not(.fi-ta-selection-cell),
        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-selection-cell + .fi-ta-cell {
            display: block;
            padding-bottom: 0.5rem;
            font-weight: 600;
        }

        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:first-child:not(.fi-ta-selection-cell) > .fi-ta-cell-label,
        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-selection-cell + .fi-ta-cell > .fi-ta-cell-label {
            display: none;
        }

        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:first-child:not(.fi-ta-selection-cell) .fi-ta-cell-content,
        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-selection-cell + .fi-ta-cell .fi-ta-cell-content {
            text-align: start;
        }

        /* A value that is only an empty icon (the "no" of a flag) leaves a heading with nothing beside it. */
        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:has(.fi-ta-icon):not(:has(.fi-ta-icon svg)) {
            display: none !important;
        }

        .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:has(> .fi-ta-actions) {
            margin-top: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-100);
        }

        .dark .fi-ta-table-stacked-on-mobile > tbody > tr.fi-ta-row > .fi-ta-cell:has(> .fi-ta-actions) {
            border-top-color: rgb(255 255 255 / 0.05);
        }
    }
</style>
@endverbatim
