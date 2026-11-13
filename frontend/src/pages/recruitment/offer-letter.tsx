import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { printReport, usePrintPage } from '@/lib/print';
import { show } from '@/routes/recruitment';
import type { BreadcrumbItem } from '@/types';
import type { OfferLetter as Letter } from '@/types/recruitment';

function Term({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid grid-cols-[11rem_1fr] gap-2">
            <dt>{label}</dt>
            <dd className="font-semibold">: {children}</dd>
        </div>
    );
}

/** The paragraphs of HR's wording; a line break HR typed inside one is kept. */
function Paragraphs({ lines }: { lines: string[] }) {
    return (
        <>
            {lines.map((line, index) => (
                <p key={`${index}-${line}`} className="whitespace-pre-line">
                    {line}
                </p>
            ))}
        </>
    );
}

/**
 * The offer letter, surat penawaran kerja (REC-OFR): set up for printing (Cetak / PDF). The wording (title, subject, the
 * paragraphs before and after the terms, who signs) is what HR set in the admin panel under "Template surat penawaran",
 * already filled in for this offer; the terms are always printed from the offer. Before the offer is sent the letter follows
 * the template as it is now; once sent it is the letter as it was sent, so it can be printed again unchanged.
 */
export default function OfferLetter({
    requisition,
    letter,
}: {
    requisition: { id: number; reference: string; positionName: string };
    letter: Letter;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Rekrutmen', href: '/recruitment' },
        {
            title: `${requisition.reference} ${requisition.positionName}`,
            href: show(requisition.id).url,
        },
        { title: 'Surat penawaran', href: '#' },
    ];

    setLayoutProps({ breadcrumbs });
    usePrintPage('portrait');

    return (
        <>
            <Head title={`Surat penawaran ${letter.candidate}`} />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 print:max-w-none print:p-0">
                <div className="flex justify-end gap-2 print:hidden">
                    <Button asChild variant="outline">
                        <Link href={show(requisition.id).url}>
                            Kembali ke lowongan
                        </Link>
                    </Button>
                    <Button onClick={printReport} data-test="print">
                        <Printer className="size-4" aria-hidden />
                        Cetak / PDF
                    </Button>
                </div>

                <article
                    className="space-y-6 bg-card p-8 text-sm leading-relaxed print:space-y-5 print:bg-white print:p-0 print:text-[11pt] print:text-black"
                    data-test="offer-letter-body"
                    data-print-area
                >
                    <header className="flex items-center gap-4 border-b-2 border-foreground pb-4">
                        {letter.logoUrl && (
                            <img
                                src={letter.logoUrl}
                                alt=""
                                className="h-14 w-auto max-w-40 object-contain"
                            />
                        )}
                        <div className="space-y-1">
                            <p className="text-xl font-extrabold tracking-wide uppercase">
                                {letter.company}
                            </p>
                            <p
                                className="text-xs tracking-widest uppercase"
                                data-test="letter-title"
                            >
                                {letter.title}
                            </p>
                        </div>
                    </header>

                    <div className="space-y-1">
                        {letter.number && (
                            <p data-test="letter-number">
                                Nomor: {letter.number}
                            </p>
                        )}
                        <p>{letter.date}</p>
                        <p>
                            Perihal: <strong>{letter.subject}</strong>
                        </p>
                    </div>

                    <div className="space-y-1">
                        <p>Kepada Yth.</p>
                        <p className="font-bold">{letter.candidate}</p>
                    </div>

                    <div className="space-y-3" data-test="letter-opening">
                        <Paragraphs lines={letter.opening} />
                    </div>

                    <dl className="space-y-1.5">
                        <Term label="Posisi">{letter.position}</Term>
                        {letter.department && (
                            <Term label="Departemen">{letter.department}</Term>
                        )}
                        {letter.branch && (
                            <Term label="Cabang">{letter.branch}</Term>
                        )}
                        {letter.manager && (
                            <Term label="Atasan langsung">
                                {letter.manager}
                            </Term>
                        )}
                        {letter.contract && (
                            <Term label="Status kerja">{letter.contract}</Term>
                        )}
                        <Term label="Tanggal mulai kerja">
                            {letter.startOn ?? '—'}
                        </Term>
                        <Term label="Masa percobaan">
                            {letter.probation ?? '—'}
                        </Term>
                        <Term label="Gaji pokok per bulan">
                            {letter.salary ?? '—'}
                        </Term>
                    </dl>

                    {letter.note && (
                        <p
                            className="whitespace-pre-line"
                            data-test="letter-note"
                        >
                            <strong>Catatan:</strong> {letter.note}
                        </p>
                    )}

                    <div className="space-y-3" data-test="letter-closing">
                        <Paragraphs lines={letter.closing} />
                    </div>

                    <section className="grid break-inside-avoid gap-8 pt-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <p>Hormat kami,</p>
                            <p className="font-semibold">{letter.company}</p>
                            <div className="border-b border-foreground pt-16" />
                            {letter.signer.name && (
                                <p
                                    className="font-semibold"
                                    data-test="letter-signer"
                                >
                                    {letter.signer.name}
                                </p>
                            )}
                            <p>{letter.signer.title}</p>
                        </div>
                        <div className="space-y-2">
                            <p>Setuju dan menerima,</p>
                            <p className="font-semibold">
                                Tanggal: ____________________
                            </p>
                            <div className="border-b border-foreground pt-16" />
                            <p>{letter.candidate}</p>
                        </div>
                    </section>
                </article>
            </div>
        </>
    );
}
