import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { search } from '@/routes';

type Item = { id: string; title: string; subtitle: string | null; url: string };
type Group = { key: string; label: string; items: Item[] };

const MIN_LENGTH = 2;

/**
 * Global search (CORE-SRC): a box in the header that opens over the page, searches pages, employees,
 * leave requests and documents as you type, and opens the result you choose. Ctrl/⌘ + K opens it
 * from anywhere, and the arrow keys and Enter work without a mouse.
 *
 * The server decides what may appear, so a result here is something the person could already open.
 */
export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<Group[]>([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const trimmed = query.trim();
    const searchable = trimmed.length >= MIN_LENGTH;

    useEffect(() => {
        if (!open || !searchable) {
            return;
        }

        const controller = new AbortController();
        // A short pause after the last keystroke, so typing a name is one request, not one per letter.
        const timer = setTimeout(() => {
            setLoading(true);

            fetch(search({ query: { q: trimmed } }).url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) =>
                    response.ok ? response.json() : { groups: [] },
                )
                .then((data: { groups: Group[] }) => {
                    setGroups(data.groups);
                    setActive(0);
                    setLoading(false);
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setLoading(false);
                    }
                });
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, searchable, trimmed]);

    // Results belong to the query that found them; a cleared or too-short box shows none.
    const shownGroups = useMemo(
        () => (searchable ? groups : []),
        [searchable, groups],
    );
    const flat = useMemo(
        () => shownGroups.flatMap((group) => group.items),
        [shownGroups],
    );

    const choose = (item: Item) => {
        setOpen(false);
        router.visit(item.url);
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (flat.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((value) => (value + 1) % flat.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((value) => (value - 1 + flat.length) % flat.length);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            choose(flat[active]);
        }
    };

    useEffect(() => {
        listRef.current
            ?.querySelector('[data-active="true"]')
            ?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    let position = -1;

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                className="hidden h-11 w-full max-w-md justify-start gap-3 rounded-full bg-background px-4 text-muted-foreground shadow-none sm:flex"
                onClick={() => setOpen(true)}
                aria-label="Cari"
            >
                <Search className="size-4" />
                <span className="flex-1 text-left font-normal">Cari…</span>
                <kbd className="rounded border px-1.5 text-[10px]">Ctrl K</kbd>
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="sm:hidden"
                onClick={() => setOpen(true)}
                aria-label="Cari"
            >
                <Search className="size-5" />
            </Button>

            <Dialog
                open={open}
                onOpenChange={(value) => {
                    setOpen(value);

                    if (!value) {
                        setQuery('');
                        setGroups([]);
                    }
                }}
            >
                <DialogContent
                    className="top-[15%] translate-y-0 gap-0 p-0 sm:max-w-xl"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        (event.currentTarget as HTMLElement)
                            .querySelector('input')
                            ?.focus();
                    }}
                >
                    <DialogTitle className="sr-only">Cari</DialogTitle>
                    <DialogDescription className="sr-only">
                        Cari halaman, karyawan, pengajuan cuti, dan dokumen.
                    </DialogDescription>

                    <div className="flex items-center gap-2 border-b px-3">
                        <Search className="size-4 text-muted-foreground" />
                        <input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="Cari karyawan, cuti, dokumen…"
                            aria-label="Kata kunci"
                            className="h-12 flex-1 bg-transparent text-sm outline-none"
                        />
                    </div>

                    <ul
                        ref={listRef}
                        className="max-h-[60vh] overflow-y-auto p-2"
                        role="listbox"
                    >
                        {!searchable && (
                            <li className="p-4 text-center text-sm text-muted-foreground">
                                Ketik minimal {MIN_LENGTH} huruf.
                            </li>
                        )}
                        {searchable && !loading && flat.length === 0 && (
                            <li className="p-4 text-center text-sm text-muted-foreground">
                                Tidak ada hasil untuk “{trimmed}”.
                            </li>
                        )}
                        {shownGroups.map((group) => (
                            <li key={group.key} role="presentation">
                                <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                    {group.label}
                                </p>
                                <ul>
                                    {group.items.map((item) => {
                                        position += 1;
                                        const index = position;

                                        return (
                                            <li key={`${group.key}-${item.id}`}>
                                                <button
                                                    type="button"
                                                    role="option"
                                                    aria-selected={
                                                        active === index
                                                    }
                                                    data-active={
                                                        active === index
                                                    }
                                                    onMouseMove={() =>
                                                        setActive(index)
                                                    }
                                                    onClick={() => choose(item)}
                                                    className={`flex w-full flex-col items-start rounded-md px-2 py-1.5 text-left ${
                                                        active === index
                                                            ? 'bg-muted'
                                                            : ''
                                                    }`}
                                                >
                                                    <span className="text-sm">
                                                        {item.title}
                                                    </span>
                                                    {item.subtitle && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {item.subtitle}
                                                        </span>
                                                    )}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}
