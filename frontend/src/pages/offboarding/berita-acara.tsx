import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/offboarding';
import type { BreadcrumbItem } from '@/types';
import type { OffboardingCase } from '@/types/offboarding';

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd>{children}</dd>
        </div>
    );
}

/**
 * The handover report, berita acara serah terima (OFF-SIG-007): set up for printing (Cetak / PDF), and
 * always built from the case as it is now, so it can be printed again at any time.
 */
export default function BeritaAcara({ case: item }: { case: OffboardingCase }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Offboarding', href: '/offboarding' },
        {
            title: `${item.reference} ${item.employee.name}`,
            href: show(item.id).url,
        },
        { title: 'Berita acara', href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    const exit = item.signoff?.exitInterview;

    return (
        <>
            <Head title={`Berita acara ${item.reference}`} />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 print:max-w-none print:p-0">
                <div className="flex justify-end gap-2 print:hidden">
                    <Button asChild variant="outline">
                        <Link href={show(item.id).url}>Kembali ke kasus</Link>
                    </Button>
                    <Button onClick={() => window.print()} data-test="print">
                        Cetak / PDF
                    </Button>
                </div>

                <article className="space-y-6 bg-card p-8 text-sm print:bg-white print:p-0 print:text-black">
                    <header className="space-y-1 border-b-2 border-foreground pb-4">
                        <p className="text-xs tracking-widest uppercase">
                            Berita acara serah terima
                        </p>
                        <h1 className="text-2xl font-extrabold">
                            {item.employee.name}
                        </h1>
                        <p>
                            {item.reference} · {item.statusLabel}
                        </p>
                    </header>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nomor karyawan">
                            {item.employee.number}
                        </Field>
                        <Field label="Jabatan · Departemen">
                            {[item.employee.position, item.employee.department]
                                .filter(Boolean)
                                .join(' · ') || '—'}
                        </Field>
                        <Field label="Atasan langsung">
                            {item.employee.manager ?? '—'}
                        </Field>
                        <Field label="Jenis keluar">
                            {item.exitType ?? '—'}
                        </Field>
                        <Field label="Tanggal pemberitahuan">
                            {item.noticeOn ?? '—'}
                        </Field>
                        <Field label="Hari kerja terakhir">
                            {item.lastWorkingOn ?? '—'}
                        </Field>
                        <Field label="Kompensasi">
                            {item.compensationMonths} bulan
                        </Field>
                        <Field label="Diajukan oleh">
                            {item.createdBy ?? '—'}
                        </Field>
                    </dl>

                    <section className="space-y-2">
                        <h2 className="text-xs font-bold tracking-widest uppercase">
                            Persetujuan Manajemen
                        </h2>
                        {item.chain.map((step) => (
                            <p key={step.label}>
                                {step.decidedBy ?? step.approver ?? step.label}{' '}
                                · {step.statusLabel}
                                {step.decidedAt ? ` · ${step.decidedAt}` : ''}
                            </p>
                        ))}
                    </section>

                    <section className="space-y-2">
                        <h2 className="text-xs font-bold tracking-widest uppercase">
                            Checklist serah terima
                        </h2>
                        {item.checklist?.groups
                            .filter((group) => !group.notInvolved)
                            .map((group) => (
                                <div key={group.id} className="space-y-1">
                                    <p className="font-semibold">
                                        {group.name}{' '}
                                        <span className="font-normal text-muted-foreground print:text-black">
                                            ({group.owner ?? 'Admin HR'})
                                        </span>
                                    </p>
                                    {group.items.length === 0 ? (
                                        <p>Tidak ada item yang berlaku.</p>
                                    ) : (
                                        <ul className="list-disc pl-5">
                                            {group.items.map((entry) => (
                                                <li key={entry.id}>
                                                    {entry.name}:{' '}
                                                    {entry.done
                                                        ? `selesai ${entry.doneAt ?? ''}${entry.doneBy ? `, ${entry.doneBy}` : ''}${entry.note ? ` — ${entry.note}` : ''}`
                                                        : 'belum selesai'}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))}
                    </section>

                    <section className="space-y-2">
                        <h2 className="text-xs font-bold tracking-widest uppercase">
                            Wawancara keluar
                        </h2>
                        {exit?.recorded ? (
                            exit.skipped ? (
                                <p>Dilewati: {exit.skippedReason}</p>
                            ) : (
                                <p>
                                    Alasan utama: {exit.reason}. Bersedia
                                    direkrut kembali:{' '}
                                    {exit.rehire ? 'ya' : 'tidak'}.
                                </p>
                            )
                        ) : (
                            <p>Belum dicatat.</p>
                        )}
                    </section>

                    <section className="space-y-2">
                        <h2 className="text-xs font-bold tracking-widest uppercase">
                            Tanda tangan
                        </h2>
                        <div className="grid gap-6 sm:grid-cols-2">
                            {[
                                {
                                    label: 'Yang menyerahkan',
                                    name: item.employee.name,
                                },
                                { label: 'HR', name: item.createdBy },
                            ].map((signer) => (
                                <div key={signer.label} className="space-y-8">
                                    <p>{signer.label}</p>
                                    <div className="border-b border-foreground pt-6" />
                                    <p>{signer.name ?? '—'}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </article>
            </div>
        </>
    );
}
