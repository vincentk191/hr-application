import { UserCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { shortDate } from '@/components/requisition-summary';
import type { Candidate, Requisition } from '@/types/recruitment';

/** "2 dari 3 penilai: Lanjut", or nothing before anyone has evaluated. */
function recommendation(candidate: Candidate): string | null {
    const total = candidate.evaluations.length;

    if (total === 0) {
        return null;
    }

    const forward = candidate.evaluations.filter(
        (entry) => entry.recommendation === 'continue',
    ).length;

    return `${forward} dari ${total} penilai: Lanjut`;
}

/**
 * Stage 05 Penawaran. For now it lists the candidates the direct superior chose (REC-PIP), each opening the candidate's
 * card; the offer itself (Management's approval, the terms and the letter) is built on top of this list. Only HR and the
 * direct superior see who was chosen; everyone else sees how many.
 */
export function OfferTab({ item }: { item: Requisition }) {
    const [viewingId, setViewingId] = useState<number | null>(null);
    const [drawerTab, setDrawerTab] = useState<CandidateTab>('interview');

    if (item.shortlist === null) {
        return (
            <section
                className="surface p-6 text-sm text-muted-foreground"
                data-test="offer-tab"
            >
                {item.selectedCount} kandidat dipilih atasan langsung.
            </section>
        );
    }

    const selected = item.shortlist.filter((candidate) => candidate.isSelected);
    const viewing =
        item.shortlist.find((candidate) => candidate.id === viewingId) ?? null;

    return (
        <div className="space-y-5" data-test="offer-tab">
            <section className="surface space-y-4 p-4">
                <header className="flex items-center justify-between gap-3">
                    <h2 className="label-mono flex items-center gap-2">
                        <UserCheck className="size-4" aria-hidden />
                        Dipilih atasan langsung
                    </h2>
                    <span className="label-mono inline-flex items-center gap-1.5">
                        <Users className="size-4" aria-hidden />
                        {selected.length}
                        {item.quota ? ` / ${item.quota}` : ''}
                    </span>
                </header>

                {selected.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada yang dipilih.
                    </p>
                ) : (
                    <ul className="space-y-2.5">
                        {selected.map((candidate) => (
                            <li key={candidate.id}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setDrawerTab('interview');
                                        setViewingId(candidate.id);
                                    }}
                                    className="flex w-full items-center gap-3 rounded-2xl border bg-card p-3 text-left hover:bg-muted/40"
                                    data-test="offer-candidate"
                                >
                                    <EmployeeAvatar
                                        name={candidate.name}
                                        photoUrl={null}
                                        className="size-10"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-bold">
                                            {candidate.name}
                                        </span>
                                        <span className="block truncate font-mono text-sm text-muted-foreground">
                                            {[
                                                recommendation(candidate),
                                                candidate.selectedOn
                                                    ? `dipilih ${shortDate(candidate.selectedOn)}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
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
