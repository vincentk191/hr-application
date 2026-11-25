import { useState } from 'react';
import { Button } from '@/components/ui/button';

export const PAGE_SIZES = [10, 25, 50, 100];

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

/**
 * Paging over a list the page already holds. The lists here are loaded whole, so paging is a slice
 * on screen rather than another request; the filters narrow the list and the pager walks it.
 */
export function usePagination(count: number, initialSize = 25) {
    const [pageSize, setPageSize] = useState(initialSize);
    const [page, setPage] = useState(1);

    const lastPage = Math.max(1, Math.ceil(count / pageSize));
    const currentPage = Math.min(page, lastPage);

    return {
        count,
        pageSize,
        currentPage,
        lastPage,
        setPage,
        setPageSize: (size: number) => {
            setPageSize(size);
            setPage(1);
        },
        slice: <T,>(items: T[]): T[] =>
            items.slice((currentPage - 1) * pageSize, currentPage * pageSize),
    };
}

/** The footer for {@link usePagination}: range shown, page size, and previous/next. */
export function Pagination({
    pagination,
}: {
    pagination: ReturnType<typeof usePagination>;
}) {
    const { count, pageSize, currentPage, lastPage } = pagination;

    if (count <= PAGE_SIZES[0]) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-t p-3 text-sm print:hidden">
            <span className="text-muted-foreground">
                {(currentPage - 1) * pageSize + 1}–
                {Math.min(currentPage * pageSize, count)} dari {count}
            </span>
            <div className="flex items-center gap-2">
                <label className="flex items-center gap-1 text-muted-foreground">
                    Per halaman
                    <select
                        value={pageSize}
                        onChange={(event) =>
                            pagination.setPageSize(Number(event.target.value))
                        }
                        className={selectClasses}
                    >
                        {PAGE_SIZES.map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </select>
                </label>
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    disabled={currentPage <= 1}
                    onClick={() => pagination.setPage(currentPage - 1)}
                >
                    ‹ Sebelumnya
                </Button>
                <span>
                    {currentPage} / {lastPage}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    disabled={currentPage >= lastPage}
                    onClick={() => pagination.setPage(currentPage + 1)}
                >
                    Berikutnya ›
                </Button>
            </div>
        </div>
    );
}

/** Where the server's page sits in a list it holds in full: the figures Laravel's paginator reports. */
export type ServerPage = {
    total: number;
    from: number | null;
    to: number | null;
    currentPage: number;
    lastPage: number;
};

/**
 * The footer for a list the server pages: the range shown and previous/next, each a request for that page. Unlike
 * {@link Pagination} the rest of the list is never sent to the browser.
 */
export function ServerPagination({
    page,
    onPage,
}: {
    page: ServerPage;
    onPage: (page: number) => void;
}) {
    if (page.lastPage <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-t p-3 text-sm print:hidden">
            <span className="text-muted-foreground">
                {page.from}–{page.to} dari {page.total}
            </span>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    disabled={page.currentPage <= 1}
                    onClick={() => onPage(page.currentPage - 1)}
                >
                    ‹ Sebelumnya
                </Button>
                <span>
                    {page.currentPage} / {page.lastPage}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    disabled={page.currentPage >= page.lastPage}
                    onClick={() => onPage(page.currentPage + 1)}
                >
                    Berikutnya ›
                </Button>
            </div>
        </div>
    );
}
