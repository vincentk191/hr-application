import type { BreadcrumbItem } from '@/types';

/**
 * The page's name in large type under the top bar, with the section it belongs to as a small
 * caption above when there is one. Taken from the page's breadcrumbs, so every page has one without
 * each having to draw it.
 */
export function PageTitleBar({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    if (breadcrumbs.length === 0) {
        return null;
    }

    const title = breadcrumbs[breadcrumbs.length - 1].title;
    const trail = breadcrumbs.slice(0, -1).map((item) => item.title);

    return (
        <div className="border-b bg-card px-4 py-5 sm:px-6">
            {trail.length > 0 && (
                <p className="label-mono mb-1">{trail.join(' › ')}</p>
            )}
            <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">
                {title}
            </h1>
        </div>
    );
}
