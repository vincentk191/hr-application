import { Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    Check,
    ListChecks,
    Undo2,
    UserCheck,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import { Person } from '@/components/requisition-offer';
import { formatDate } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { show as employeeShow } from '@/routes/employees';
import { employee } from '@/routes/recruitment/offers';
import { takedown } from '@/routes/recruitment/postings';
import type {
    Candidate,
    Requisition,
    RequisitionPosting,
} from '@/types/recruitment';

/** One listing HR still has to take down from its platform, or has: HR ticks it off and can take the tick back. */
function ListingRow({
    item,
    posting,
}: {
    item: Requisition;
    posting: RequisitionPosting;
}) {
    const [busy, setBusy] = useState(false);
    const done = posting.takenDownOn !== null;

    return (
        <li
            className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-card p-3"
            data-test="listing-row"
        >
            <span className="min-w-0">
                <span className="block truncate font-bold">
                    {posting.label}
                </span>
                <span className="block truncate font-mono text-sm text-muted-foreground">
                    {done
                        ? `diturunkan ${formatDate(posting.takenDownOn ?? '')}`
                        : `diposting ${formatDate(posting.postedOn)}`}
                    {posting.url ? ` · ${posting.url}` : ''}
                </span>
            </span>
            {item.canCloseFilled && (
                <Button
                    size="sm"
                    variant={done ? 'outline' : 'default'}
                    disabled={busy}
                    onClick={() =>
                        router.put(
                            takedown.url({
                                requisition: item.id,
                                posting: posting.id,
                            }),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setBusy(true),
                                onFinish: () => setBusy(false),
                            },
                        )
                    }
                    data-test="take-down"
                >
                    {done ? (
                        <>
                            <Undo2 className="size-4" aria-hidden />
                            Batal
                        </>
                    ) : (
                        <>
                            <Check className="size-4" aria-hidden />
                            Sudah diturunkan
                        </>
                    )}
                </Button>
            )}
        </li>
    );
}

/**
 * Stage 06 Terisi (REC-FIL): who accepted, and what is left to close. Taking the listings down from the platforms is HR's,
 * ticked here; withdrawing the offers still out and ending the form links were done when the requisition was marked
 * filled. Who sees the hires follows who sees the offers; everyone else sees how many places were filled.
 */
export function FilledTab({ item }: { item: Requisition }) {
    const [viewingId, setViewingId] = useState<number | null>(null);
    const [drawerTab, setDrawerTab] = useState<CandidateTab>('interview');
    const hires = (item.offers ?? []).filter(
        (offer) => offer.stage === 'accepted',
    );
    // The same lookup as the Penawaran tab: the shortlist where the person sees it, else the candidate each offer carries.
    const candidates: Candidate[] = [
        ...(item.shortlist ?? []),
        ...(item.offers ?? []).flatMap((offer) =>
            offer.candidate ? [offer.candidate] : [],
        ),
    ];
    const viewing =
        candidates.find((candidate) => candidate.id === viewingId) ?? null;

    return (
        <div className="space-y-5" data-test="filled-tab">
            <section className="surface space-y-4 p-4">
                <header className="flex items-center justify-between gap-3">
                    <h2 className="label-mono flex items-center gap-2">
                        <BadgeCheck className="size-4" aria-hidden />
                        Diterima
                    </h2>
                    <span className="label-mono">
                        {item.filled}
                        {item.quota ? ` / ${item.quota}` : ''}
                    </span>
                </header>

                {item.offers === null ? (
                    <p className="text-sm text-muted-foreground">
                        {item.filled} kandidat menerima penawaran.
                    </p>
                ) : (
                    <ul className="space-y-2.5">
                        {hires.map((offer) => (
                            <li
                                key={offer.id}
                                className="flex items-center gap-3 rounded-2xl border bg-card p-3"
                                data-test="hire-row"
                            >
                                <Person
                                    name={offer.name ?? '—'}
                                    detail={
                                        offer.startOn
                                            ? `mulai ${formatDate(offer.startOn)}`
                                            : '—'
                                    }
                                    onOpen={() => {
                                        setDrawerTab('interview');
                                        setViewingId(offer.candidateId);
                                    }}
                                />
                                {offer.canConvert && (
                                    <Button
                                        asChild
                                        size="sm"
                                        data-test="convert-hire"
                                    >
                                        <Link
                                            href={
                                                employee({
                                                    requisition: item.id,
                                                    offer: offer.id,
                                                }).url
                                            }
                                        >
                                            <UserPlus
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Jadikan karyawan
                                        </Link>
                                    </Button>
                                )}
                                {offer.employeeId !== null && (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        data-test="open-employee"
                                    >
                                        <Link
                                            href={
                                                employeeShow(offer.employeeId)
                                                    .url
                                            }
                                        >
                                            <UserCheck
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Karyawan
                                        </Link>
                                    </Button>
                                )}
                                {offer.hasEmployee &&
                                    offer.employeeId === null && (
                                        <span
                                            className="label-mono flex items-center gap-1.5"
                                            data-test="converted"
                                        >
                                            <UserCheck
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Sudah jadi karyawan
                                        </span>
                                    )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="surface space-y-4 p-4">
                <header className="flex items-center justify-between gap-3">
                    <h2 className="label-mono flex items-center gap-2">
                        <ListChecks className="size-4" aria-hidden />
                        Penutupan
                    </h2>
                    <span className="label-mono">
                        {
                            item.postings.filter((p) => p.takenDownOn).length
                        } /{' '}
                        {item.postings.length}
                    </span>
                </header>

                {item.postings.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Tidak ada lowongan yang perlu diturunkan.
                    </p>
                ) : (
                    <ul className="space-y-2.5">
                        {item.postings.map((posting) => (
                            <ListingRow
                                key={posting.id}
                                item={item}
                                posting={posting}
                            />
                        ))}
                    </ul>
                )}

                <p className="text-sm text-muted-foreground">
                    Penawaran lain sudah ditarik dan tautan formulir ditutup.
                </p>
            </section>

            <CandidateDrawer
                item={item}
                candidate={viewing}
                tab={drawerTab}
                onTab={setDrawerTab}
                onClose={() => setViewingId(null)}
            />
        </div>
    );
}
