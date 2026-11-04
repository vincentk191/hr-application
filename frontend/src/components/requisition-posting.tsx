import { Form, router } from '@inertiajs/react';
import { Check, ChevronDown, Copy, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { shortDate } from '@/components/requisition-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { copyText } from '@/lib/clipboard';
import {
    postingAll,
    postingDescription,
    postingRequirements,
    postingTitle,
} from '@/lib/posting-text';
import { destroy, store, update } from '@/routes/recruitment/postings';
import type { Requisition, RequisitionPosting } from '@/types/recruitment';

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

/** A copy button: puts the text on the clipboard and says so for a moment. */
function CopyButton({
    label,
    text,
    testId,
}: {
    label: string;
    text: string;
    testId: string;
}) {
    const [state, setState] = useState<'idle' | 'copied' | 'failed'>('idle');

    const copy = async () => {
        setState((await copyText(text)) ? 'copied' : 'failed');
        window.setTimeout(() => setState('idle'), 2000);
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={text.trim() === ''}
            onClick={copy}
            data-test={testId}
        >
            {state === 'copied' ? (
                <Check className="size-4" />
            ) : (
                <Copy className="size-4" />
            )}
            {state === 'copied'
                ? 'Tersalin'
                : state === 'failed'
                  ? 'Gagal menyalin'
                  : label}
        </Button>
    );
}

/** Copy shortcuts for pasting the listing into a platform's own form (REC-PST-001). */
function CopySection({
    label,
    hint,
    text,
    testId,
    defaultOpen,
}: {
    label: string;
    hint?: string;
    text: string;
    testId: string;
    defaultOpen: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const lines = text.trim() === '' ? 0 : text.split('\n').length;

    return (
        <div className="overflow-hidden rounded-xl border" data-test={testId}>
            <div className="flex items-center justify-between gap-3 p-3">
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    aria-expanded={open}
                    className="flex min-w-0 items-center gap-2 text-left"
                    data-test={`${testId}-toggle`}
                >
                    <ChevronDown
                        className={`size-4 shrink-0 transition-transform ${open ? '' : '-rotate-90'}`}
                    />
                    <span className="min-w-0">
                        <span className="block font-semibold">{label}</span>
                        <span className="label-mono block">
                            {hint ? `${hint} · ` : ''}
                            {lines} BARIS
                        </span>
                    </span>
                </button>
                <CopyButton
                    label="Salin"
                    text={text}
                    testId={`copy-${testId}`}
                />
            </div>
            {open && (
                <pre
                    className="max-h-72 overflow-auto border-t bg-muted/30 p-3 font-sans text-sm whitespace-pre-wrap"
                    data-test={`${testId}-preview`}
                >
                    {text.trim() === '' ? 'Belum ada isi.' : text}
                </pre>
            )}
        </div>
    );
}

function CopyShortcuts({ item }: { item: Requisition }) {
    return (
        <section className="surface space-y-3 p-4" data-test="copy-shortcuts">
            <div>
                <h2 className="label-mono">Salin untuk platform</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Buka tiap bagian untuk melihat teks yang akan disalin, lalu
                    tempel ke kolom yang sesuai di Glints, JobStreet dan
                    lainnya. Catat postingnya di bawah.
                </p>
            </div>
            <div className="space-y-2">
                <CopySection
                    label="Judul"
                    hint="Kolom judul pekerjaan"
                    text={postingTitle(item)}
                    testId="title"
                    defaultOpen
                />
                <CopySection
                    label="Deskripsi"
                    hint="Tentang posisi, tanggung jawab, benefit"
                    text={postingDescription(item)}
                    testId="description"
                    defaultOpen
                />
                <CopySection
                    label="Persyaratan"
                    hint="Kualifikasi, skills, nilai plus"
                    text={postingRequirements(item)}
                    testId="requirements"
                    defaultOpen
                />
                <CopySection
                    label="Semua"
                    hint="Judul, deskripsi dan persyaratan sekaligus"
                    text={postingAll(item)}
                    testId="all"
                    defaultOpen={false}
                />
            </div>
        </section>
    );
}

/** The fields of a posting, for recording a new one and for correcting one already recorded. */
function PostingFields({
    item,
    posting,
    errors,
    idPrefix,
}: {
    item: Requisition;
    posting?: RequisitionPosting;
    errors: Record<string, string>;
    idPrefix: string;
}) {
    const [platform, setPlatform] = useState(posting?.platform ?? '');

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="grid gap-1">
                <Label htmlFor={`${idPrefix}-platform`}>Platform</Label>
                <select
                    id={`${idPrefix}-platform`}
                    name="platform"
                    value={platform}
                    onChange={(event) => setPlatform(event.target.value)}
                    className={selectClasses}
                    required
                >
                    <option value="">Pilih platform</option>
                    {item.platformOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.platform} />
            </div>

            {platform === 'other' && (
                <div className="grid gap-1">
                    <Label htmlFor={`${idPrefix}-platform-name`}>
                        Nama platform
                    </Label>
                    <Input
                        id={`${idPrefix}-platform-name`}
                        name="platform_name"
                        maxLength={60}
                        defaultValue={posting?.customName ?? ''}
                        required
                    />
                    <InputError message={errors.platform_name} />
                </div>
            )}

            <div className="grid gap-1">
                <Label htmlFor={`${idPrefix}-posted-on`}>
                    Tanggal diposting
                </Label>
                <Input
                    id={`${idPrefix}-posted-on`}
                    name="posted_on"
                    type="date"
                    max={item.today}
                    defaultValue={posting?.postedOn ?? item.today}
                    required
                />
                <InputError message={errors.posted_on} />
            </div>

            <div className="grid gap-1 sm:col-span-2">
                <Label htmlFor={`${idPrefix}-url`}>
                    Tautan lowongan (opsional)
                </Label>
                <Input
                    id={`${idPrefix}-url`}
                    name="url"
                    type="url"
                    inputMode="url"
                    placeholder="https://"
                    maxLength={500}
                    defaultValue={posting?.url ?? ''}
                />
                <InputError message={errors.url} />
            </div>
        </div>
    );
}

/** One recorded posting: where, when, the link, and (for HR) correct or remove. */
function PostingRow({
    item,
    posting,
}: {
    item: Requisition;
    posting: RequisitionPosting;
}) {
    const [editing, setEditing] = useState(false);

    if (editing) {
        return (
            <li className="p-4" data-test="posting-edit">
                <Form
                    {...update.form({
                        requisition: item.id,
                        posting: posting.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEditing(false)}
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <PostingFields
                                item={item}
                                posting={posting}
                                errors={errors}
                                idPrefix={`edit-${posting.id}`}
                            />
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                    data-test="posting-save"
                                >
                                    Simpan
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEditing(false)}
                                >
                                    Batal
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </li>
        );
    }

    return (
        <li
            className="flex flex-wrap items-center justify-between gap-3 p-4"
            data-test="posting-row"
        >
            <div>
                <p className="font-semibold">{posting.label}</p>
                <p className="label-mono">
                    {shortDate(posting.postedOn)}
                    {posting.postedBy ? ` · oleh ${posting.postedBy}` : ''}
                </p>
                {posting.url && (
                    <a
                        href={posting.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-1 inline-flex items-center gap-1 text-sm underline underline-offset-4"
                    >
                        Buka lowongan
                        <ExternalLink className="size-3.5" />
                    </a>
                )}
            </div>
            {item.canManagePostings && (
                <div className="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setEditing(true)}
                        data-test="posting-edit-button"
                    >
                        Ubah
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            if (
                                window.confirm(
                                    `Hapus catatan posting di ${posting.label}?`,
                                )
                            ) {
                                router.delete(
                                    destroy.url({
                                        requisition: item.id,
                                        posting: posting.id,
                                    }),
                                    { preserveScroll: true },
                                );
                            }
                        }}
                        data-test="posting-remove"
                    >
                        Hapus
                    </Button>
                </div>
            )}
        </li>
    );
}

/**
 * Stage 02 · Diposting (REC-PST): HR posts the listing on each platform by hand, using the copy shortcuts, and records
 * where and when here. The platforms are on free accounts, so nothing is sent to them.
 */
export function PostingTab({ item }: { item: Requisition }) {
    // A new key empties the form after a posting is saved, including the platform choice it keeps in state.
    const [formKey, setFormKey] = useState(0);
    const recorded = new Set(item.postings.map((posting) => posting.platform));
    const notYet = item.platformOptions.filter(
        (option) => option.value !== 'other' && !recorded.has(option.value),
    );

    return (
        <div className="space-y-5" data-test="posting-tab">
            {item.canManagePostings && <CopyShortcuts item={item} />}

            <section className="surface overflow-hidden">
                <header className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                    <h2 className="text-lg font-extrabold tracking-tight">
                        Platform
                    </h2>
                    <span className="label-mono">
                        {item.postings.length} DIPOSTING
                    </span>
                </header>

                {item.postings.length === 0 ? (
                    <p
                        className="p-4 text-sm text-muted-foreground"
                        data-test="no-postings"
                    >
                        Belum ada catatan posting.
                        {item.canManagePostings
                            ? ' Posting lowongan di platform, lalu catat di sini.'
                            : ' HR akan mencatatnya setelah lowongan diposting.'}
                    </p>
                ) : (
                    <ul className="divide-y">
                        {item.postings.map((posting) => (
                            <PostingRow
                                key={posting.id}
                                item={item}
                                posting={posting}
                            />
                        ))}
                    </ul>
                )}

                {notYet.length > 0 && item.postings.length > 0 && (
                    <p className="border-t px-4 py-3 text-sm text-muted-foreground">
                        Belum dicatat di:{' '}
                        {notYet.map((option) => option.label).join(', ')}
                    </p>
                )}
            </section>

            {item.canManagePostings && (
                <section className="surface space-y-3 p-4">
                    <h2 className="label-mono">Tandai sudah diposting</h2>
                    <Form
                        key={formKey}
                        {...store.form(item.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setFormKey((key) => key + 1)}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <PostingFields
                                    item={item}
                                    errors={errors}
                                    idPrefix="new"
                                />
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="posting-add"
                                >
                                    Tandai diposting
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            )}
        </div>
    );
}
