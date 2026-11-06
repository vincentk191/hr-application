import { Form, router } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import InputError from '@/components/input-error';
import LimitedFileInput from '@/components/limited-file-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { parseApplicantText } from '@/lib/applicant-paste';
import { update as updateApplicants } from '@/routes/recruitment/applicants';
import { destroy, store } from '@/routes/recruitment/candidates';
import type { Candidate, Requisition } from '@/types/recruitment';

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

/** The applicant total and how many were shortlisted: the only numbers kept about people who were not. */
function Counts({ item }: { item: Requisition }) {
    const { applicantsCount, shortlistedCount } = item;
    const share =
        applicantsCount !== null && applicantsCount > 0
            ? Math.round((shortlistedCount / applicantsCount) * 100)
            : null;

    return (
        <section className="surface space-y-4 p-4" data-test="applicant-counts">
            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <p className="label-mono">Total pelamar</p>
                    <p
                        className="text-3xl font-extrabold"
                        data-test="applicants-total"
                    >
                        {applicantsCount ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="label-mono">Masuk shortlist</p>
                    <p
                        className="text-3xl font-extrabold"
                        data-test="shortlisted-total"
                    >
                        {shortlistedCount}
                    </p>
                </div>
                <div>
                    <p className="label-mono">Lolos screening</p>
                    <p className="text-3xl font-extrabold">
                        {share === null ? '—' : `${share}%`}
                    </p>
                </div>
            </div>

            {item.canManageScreening ? (
                <Form
                    {...updateApplicants.form(item.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap items-end gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="applicants-count">
                                    Jumlah pelamar (semua platform)
                                </Label>
                                <Input
                                    id="applicants-count"
                                    name="applicants_count"
                                    type="number"
                                    min={shortlistedCount}
                                    inputMode="numeric"
                                    defaultValue={applicantsCount ?? ''}
                                    className="w-44"
                                    required
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                data-test="save-applicants"
                            >
                                Simpan
                            </Button>
                            <div className="basis-full">
                                <InputError message={errors.applicants_count} />
                            </div>
                        </>
                    )}
                </Form>
            ) : null}
        </section>
    );
}

/**
 * One shortlisted candidate: the name, the platform they came from and, for HR, "Hapus". Clicking the row opens the
 * candidate's drawer, which holds everything else about them.
 */
function ShortlistRow({
    item,
    candidate,
    onOpen,
}: {
    item: Requisition;
    candidate: Candidate;
    onOpen: (candidate: Candidate) => void;
}) {
    return (
        <li
            className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-muted/40"
            data-test="candidate-row"
        >
            <button
                type="button"
                onClick={() => onOpen(candidate)}
                className="flex min-w-0 flex-1 items-center gap-2 text-left focus-visible:outline-none"
                data-test="candidate-open"
            >
                <span className="truncate font-semibold">{candidate.name}</span>
                <Badge variant="secondary" className="shrink-0">
                    {candidate.sourceLabel}
                </Badge>
            </button>
            {item.canManageScreening && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        if (
                            window.confirm(
                                `Hapus ${candidate.name} dari shortlist? CV-nya ikut dihapus.`,
                            )
                        ) {
                            router.delete(
                                destroy.url({
                                    requisition: item.id,
                                    candidate: candidate.id,
                                }),
                                { preserveScroll: true },
                            );
                        }
                    }}
                    data-test="candidate-remove"
                >
                    Hapus
                </Button>
            )}
        </li>
    );
}

/** The shortlist: a row per candidate, opened in a drawer for the details; HR can take someone off. */
function Shortlist({ item, list }: { item: Requisition; list: Candidate[] }) {
    const [viewingId, setViewingId] = useState<number | null>(null);
    const [tab, setTab] = useState<CandidateTab>('overview');
    const viewing =
        list.find((candidate) => candidate.id === viewingId) ?? null;

    return (
        <section className="surface overflow-hidden" data-test="shortlist">
            <header className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                <h2 className="text-lg font-extrabold tracking-tight">
                    Shortlist
                </h2>
                <span className="label-mono">{list.length} KANDIDAT</span>
            </header>

            {list.length === 0 ? (
                <p
                    className="p-4 text-sm text-muted-foreground"
                    data-test="no-candidates"
                >
                    Belum ada kandidat. Saring pelamar di platform, lalu
                    tambahkan yang layak lanjut lewat tombol “Tambah kandidat”
                    di atas.
                </p>
            ) : (
                <ul className="divide-y">
                    {list.map((candidate) => (
                        <ShortlistRow
                            key={candidate.id}
                            item={item}
                            candidate={candidate}
                            onOpen={(chosen) => {
                                setTab('overview');
                                setViewingId(chosen.id);
                            }}
                        />
                    ))}
                </ul>
            )}

            <CandidateDrawer
                item={item}
                candidate={viewing}
                tab={tab}
                onTab={setTab}
                onClose={() => setViewingId(null)}
            />
        </section>
    );
}

/**
 * The quick-add form: a few fields, the platform kept between entries, "Isi otomatis" for text pasted off the
 * platform's applicant page (phone and email are detected, the name is a guess HR confirms), and a warning when the
 * same phone or email is already shortlisted.
 */
function QuickAdd({ item }: { item: Requisition }) {
    const firstPosted = item.postings
        .map((posting) => posting.platform)
        .find((platform) =>
            item.sourceOptions.some((option) => option.value === platform),
        );

    const [formKey, setFormKey] = useState(0);
    const [source, setSource] = useState(firstPosted ?? '');
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [email, setEmail] = useState('');
    const [pasted, setPasted] = useState('');
    const [pasteMessage, setPasteMessage] = useState<string | null>(null);
    const nameRef = useRef<HTMLInputElement>(null);

    // After a candidate is saved the form empties (the platform stays) and the cursor returns to the name.
    useEffect(() => {
        if (formKey > 0) {
            nameRef.current?.focus();
        }
    }, [formKey]);

    const fill = () => {
        const found = parseApplicantText(pasted);

        if (found.name) {
            setName(found.name);
        }

        if (found.phone) {
            setPhone(found.phone);
        }

        if (found.email) {
            setEmail(found.email);
        }

        setPasteMessage(
            found.name || found.phone || found.email
                ? 'Terisi dari teks. Periksa dulu, terutama namanya.'
                : 'Tidak ada nama, telepon atau email yang dikenali dari teks itu.',
        );
    };

    return (
        <div className="space-y-4" data-test="quick-add">
            <div className="space-y-2">
                <Label htmlFor="pasted-applicant">
                    Tempel data pelamar (opsional)
                </Label>
                <textarea
                    id="pasted-applicant"
                    rows={3}
                    value={pasted}
                    onChange={(event) => setPasted(event.target.value)}
                    placeholder="Salin kartu pelamar dari Glints, JobStreet, dsb., lalu tempel di sini"
                    className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    data-test="pasted-applicant"
                />
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={pasted.trim() === ''}
                        onClick={fill}
                        data-test="autofill"
                    >
                        <Wand2 /> Isi otomatis
                    </Button>
                    {pasteMessage && (
                        <span className="text-sm text-muted-foreground">
                            {pasteMessage}
                        </span>
                    )}
                </div>
            </div>

            <Form
                key={formKey}
                {...store.form(item.id)}
                options={{ preserveScroll: true }}
                onSuccess={() => {
                    setName('');
                    setPhone('');
                    setEmail('');
                    setPasted('');
                    setPasteMessage(null);
                    setFormKey((key) => key + 1);
                }}
                className="space-y-3"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="confirm_duplicate"
                            defaultValue=""
                        />

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="candidate-name">Nama</Label>
                                <Input
                                    id="candidate-name"
                                    ref={nameRef}
                                    name="name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    maxLength={120}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="candidate-source">Asal</Label>
                                <select
                                    id="candidate-source"
                                    name="source"
                                    value={source}
                                    onChange={(event) =>
                                        setSource(event.target.value)
                                    }
                                    className={selectClasses}
                                    required
                                >
                                    <option value="">Pilih asal</option>
                                    {item.sourceOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.source} />
                            </div>
                            {source === 'other' && (
                                <div className="grid gap-1 sm:col-span-2">
                                    <Label htmlFor="candidate-source-name">
                                        Nama sumber
                                    </Label>
                                    <Input
                                        id="candidate-source-name"
                                        name="source_name"
                                        maxLength={60}
                                        required
                                    />
                                    <InputError message={errors.source_name} />
                                </div>
                            )}
                            <div className="grid gap-1">
                                <Label htmlFor="candidate-phone">
                                    Nomor telepon / WhatsApp
                                </Label>
                                <Input
                                    id="candidate-phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    value={phone}
                                    onChange={(event) =>
                                        setPhone(event.target.value)
                                    }
                                    maxLength={30}
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="candidate-email">Email</Label>
                                <Input
                                    id="candidate-email"
                                    name="email"
                                    type="email"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    maxLength={160}
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-1 sm:col-span-2">
                                <Label htmlFor="candidate-note">
                                    Catatan (opsional)
                                </Label>
                                <textarea
                                    id="candidate-note"
                                    name="note"
                                    rows={2}
                                    maxLength={1000}
                                    className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.note} />
                            </div>
                            <div className="grid gap-1 sm:col-span-2">
                                <Label htmlFor="candidate-cv">
                                    CV (opsional)
                                </Label>
                                <LimitedFileInput
                                    id="candidate-cv"
                                    name="cv"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                    maxSizeMb={5}
                                />
                                <InputError message={errors.cv} />
                            </div>
                        </div>

                        {errors.duplicate && (
                            <div
                                className="space-y-2 rounded-xl border border-amber-500/50 bg-amber-500/10 p-3 text-sm"
                                data-test="duplicate-warning"
                            >
                                <p>{errors.duplicate}</p>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={(event) => {
                                        const form = event.currentTarget.form;
                                        const flag =
                                            form?.elements.namedItem(
                                                'confirm_duplicate',
                                            );

                                        if (
                                            form &&
                                            flag instanceof HTMLInputElement
                                        ) {
                                            flag.value = '1';
                                            form.requestSubmit();
                                        }
                                    }}
                                    data-test="save-anyway"
                                >
                                    Ini orang yang berbeda, simpan tetap
                                </Button>
                            </div>
                        )}

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="add-candidate"
                        >
                            Tambah ke shortlist
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}

/**
 * Stage 03 · Screening (REC-SCR). Applicants are screened inside the platforms, so HR enters how many applied in total
 * and shortlists the ones worth taking further; applicants who are not shortlisted are never stored.
 */
export function ScreeningTab({ item }: { item: Requisition }) {
    return (
        <div className="space-y-5" data-test="screening-tab">
            {(item.stage === 'interview' || item.stage === 'offer') &&
                item.canManageScreening && (
                    <p
                        className="surface p-4 text-sm text-muted-foreground"
                        data-test="screening-continues"
                    >
                        Lowongan sudah di tahap berikutnya, tetapi screening
                        tetap terbuka: tambahkan kandidat baru lewat tombol
                        “Tambah kandidat” di atas kapan saja, misalnya bila
                        semua yang sudah diinterview tidak lolos atau penawaran
                        ditolak.
                    </p>
                )}

            <Counts item={item} />

            {item.shortlist === null ? (
                <p className="surface p-4 text-sm text-muted-foreground">
                    Daftar kandidat hanya terlihat oleh HR.
                </p>
            ) : (
                <Shortlist item={item} list={item.shortlist} />
            )}
        </div>
    );
}

/**
 * “Tambah kandidat”: the quick-add form in a pop-up opened from the button at the top of the requisition page, so it is
 * in reach from every tab and every stage that keeps screening open. It stays open after each save, ready for the next
 * candidate; the cross closes it.
 */
export function AddCandidateDialog({
    item,
    trigger,
}: {
    item: Requisition;
    trigger: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                className="max-h-[90vh] gap-4 overflow-y-auto sm:max-w-2xl"
                data-test="add-candidate-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Tambah ke shortlist</DialogTitle>
                    <DialogDescription className="sr-only">
                        Data kandidat yang lolos screening.
                    </DialogDescription>
                </DialogHeader>
                <QuickAdd item={item} />
            </DialogContent>
        </Dialog>
    );
}
