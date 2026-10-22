import { Form, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import LimitedFileInput from '@/components/limited-file-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { interview, reports } from '@/routes/offboarding';
import { store as storeFiles } from '@/routes/offboarding/files';
import type { OffboardingCase } from '@/types/offboarding';

const textareaClasses =
    'w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

/**
 * The people who report to the leaver, and the departments they head: what HR settles before the case
 * can close. Reports are given a new Atasan here (the case cannot close while any is left); the head of
 * a department is set in the admin panel, so that is only a warning.
 */
export function OffboardingImpactPanel({ item }: { item: OffboardingCase }) {
    const impact = item.impact;
    const [moves, setMoves] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            (impact?.reports ?? []).map((report) => [
                report.id,
                impact?.defaultManagerId ? String(impact.defaultManagerId) : '',
            ]),
        ),
    );

    if (!impact || (impact.reports.length === 0 && impact.headOf.length === 0)) {
        return null;
    }

    const ready = impact.reports.every((report) => moves[report.id]);

    return (
        <section
            className="surface space-y-3 border-amber-500/50 p-4"
            data-test="impact-panel"
        >
            <h2 className="label-mono">Harus diselesaikan sebelum kasus ditutup</h2>

            {impact.reports.length > 0 && (
                <div className="space-y-2">
                    <p className="text-sm">
                        {item.employee.name} masih menjadi atasan langsung{' '}
                        {impact.reports.length} orang. Pindahkan mereka ke
                        atasan baru; permintaan yang menunggu tetap berjalan
                        lewat atasan baru mereka.
                    </p>
                    <ul className="divide-y">
                        {impact.reports.map((report) => (
                            <li
                                key={report.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                            >
                                <span>
                                    {report.name}{' '}
                                    <span className="label-mono">
                                        {report.number}
                                    </span>
                                </span>
                                <select
                                    value={moves[report.id] ?? ''}
                                    onChange={(event) =>
                                        setMoves({
                                            ...moves,
                                            [report.id]: event.target.value,
                                        })
                                    }
                                    className="h-9 min-w-56 rounded-xl border border-input bg-card px-3 text-sm"
                                    aria-label={`Atasan baru untuk ${report.name}`}
                                >
                                    <option value="">Pilih atasan baru…</option>
                                    {impact.options.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </li>
                        ))}
                    </ul>
                    <Button
                        size="sm"
                        disabled={!ready}
                        data-test="move-reports"
                        onClick={() =>
                            router.post(
                                reports(item.id).url,
                                { moves },
                                { preserveScroll: true },
                            )
                        }
                    >
                        Pindahkan bawahan
                    </Button>
                </div>
            )}

            {impact.headOf.length > 0 && (
                <p className="text-sm text-amber-700 dark:text-amber-300">
                    {item.employee.name} adalah kepala departemen{' '}
                    {impact.headOf.join(', ')}. Tetapkan kepala baru di panel
                    admin (Departemen); sampai itu, checklist departemen
                    tersebut ditangani Admin HR.
                </p>
            )}
        </section>
    );
}

function ExitInterview({ item }: { item: OffboardingCase }) {
    const exit = item.signoff?.exitInterview;
    const [skip, setSkip] = useState(false);

    if (!exit) {
        return null;
    }

    if (exit.recorded && !exit.canRecord) {
        return (
            <section className="surface space-y-2 p-4">
                <h2 className="label-mono">Wawancara keluar</h2>
                {exit.skipped ? (
                    <p className="text-sm">
                        Dilewati: {exit.skippedReason}
                    </p>
                ) : (
                    <dl className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt className="label-mono">Alasan utama</dt>
                            <dd>{exit.reason}</dd>
                        </div>
                        <div>
                            <dt className="label-mono">
                                Bersedia direkrut kembali
                            </dt>
                            <dd>{exit.rehire ? 'Ya' : 'Tidak'}</dd>
                        </div>
                        <div>
                            <dt className="label-mono">Catatan</dt>
                            <dd className="whitespace-pre-line">
                                {exit.notes ?? '—'}
                            </dd>
                        </div>
                    </dl>
                )}
            </section>
        );
    }

    if (!exit.canRecord) {
        return (
            <section className="surface p-4 text-sm text-muted-foreground">
                Wawancara keluar belum dicatat.
            </section>
        );
    }

    return (
        <section className="surface space-y-3 p-4" data-test="exit-interview">
            <h2 className="label-mono">Wawancara keluar</h2>
            <Form
                {...interview.form(item.id)}
                options={{ preserveScroll: true }}
                className="grid gap-3"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="skip"
                            value={skip ? '1' : '0'}
                        />
                        {skip ? (
                            <div className="grid gap-2">
                                <Label htmlFor="skipped_reason">
                                    Alasan dilewati
                                </Label>
                                <textarea
                                    id="skipped_reason"
                                    name="skipped_reason"
                                    rows={2}
                                    defaultValue={exit.skippedReason ?? ''}
                                    className={textareaClasses}
                                />
                                <InputError message={errors.skipped_reason} />
                            </div>
                        ) : (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Alasan utama</Label>
                                    <Input
                                        id="reason"
                                        name="reason"
                                        defaultValue={exit.reason ?? ''}
                                    />
                                    <InputError message={errors.reason} />
                                </div>
                                <fieldset className="grid gap-1">
                                    <legend className="text-sm font-medium">
                                        Bersedia direkrut kembali
                                    </legend>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="rehire"
                                            value="1"
                                            defaultChecked={exit.rehire === true}
                                        />
                                        Ya
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="rehire"
                                            value="0"
                                            defaultChecked={
                                                exit.rehire === false
                                            }
                                        />
                                        Tidak
                                    </label>
                                    <InputError message={errors.rehire} />
                                </fieldset>
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Catatan</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={3}
                                        defaultValue={exit.notes ?? ''}
                                        className={textareaClasses}
                                    />
                                </div>
                            </>
                        )}
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                                data-test="save-interview"
                            >
                                {skip ? 'Simpan (dilewati)' : 'Simpan wawancara'}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setSkip(!skip)}
                            >
                                {skip
                                    ? 'Isi wawancara'
                                    : 'Lewati wawancara'}
                            </Button>
                            {exit.recorded && (
                                <span className="label-mono">
                                    Tersimpan {exit.at}
                                </span>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

/** The exit documents HR adds before closing: notes of the interview, a reference letter. */
function ExitDocuments({ item }: { item: OffboardingCase }) {
    const [kind, setKind] = useState('reference_letter');

    if (!item.canAddFiles) {
        return null;
    }

    return (
        <Form
            {...storeFiles.form(item.id)}
            resetOnSuccess
            options={{ preserveScroll: true }}
            className="surface flex flex-wrap items-end gap-2 p-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1">
                        <Label htmlFor="exit-kind">Berkas keluar</Label>
                        <select
                            id="exit-kind"
                            value={kind}
                            onChange={(event) => setKind(event.target.value)}
                            className="h-9 rounded-xl border border-input bg-card px-3 text-sm"
                        >
                            <option value="reference_letter">
                                Surat referensi
                            </option>
                            <option value="exit_interview_notes">
                                Catatan wawancara keluar
                            </option>
                        </select>
                    </div>
                    <LimitedFileInput
                        name={`file_${kind}[]`}
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        maxSizeMb={10}
                        maxFiles={5}
                        className="w-72"
                    />
                    <InputError message={errors[`file_${kind}`]} />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={processing}
                    >
                        Unggah
                    </Button>
                    <p className="basis-full text-xs text-muted-foreground">
                        Surat pengunduran diri, catatan wawancara dan surat
                        referensi disimpan juga ke dokumen karyawan (terbatas)
                        saat kasus ditutup.
                    </p>
                </>
            )}
        </Form>
    );
}

/**
 * Stage 3 · Sign-off (OFF-SIG): where the case stands, the exit interview, a summary of the checklist by
 * owner, and what HR still has to settle. There are no signatures: the HR who asked for the case fills in the
 * details and closes it, and the actions are at the top of the page.
 */
export function OffboardingSignOffPanel({
    item,
    closeError,
}: {
    item: OffboardingCase;
    closeError?: string;
}) {
    const signoff = item.signoff;

    if (!signoff) {
        return null;
    }

    const closed = item.stage === 'closed';
    const detailsDone = signoff.exitInterview.recorded;

    return (
        <div className="space-y-4">
            <section className="surface flex flex-wrap items-center justify-between gap-3 p-4">
                <p
                    className="text-sm font-medium"
                    data-test="signoff-banner"
                >
                    {closed
                        ? `Kasus ditutup ${item.closedAt}. Profil karyawan dikunci.`
                        : !detailsDone
                          ? `Menunggu ${item.createdBy ?? 'HR'} mengisi detail keluar (wawancara keluar, atau dilewati dengan alasan).`
                          : `Detail lengkap. ${item.createdBy ?? 'HR'} bisa menutup kasus setelah hari kerja terakhir (${item.lastWorkingOn}).`}
                </p>
                {item.canBeritaAcara && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/offboarding/${item.id}/berita-acara`}>
                            Lihat berita acara
                        </Link>
                    </Button>
                )}
            </section>

            {closeError && (
                <p className="text-sm text-destructive" role="alert">
                    {closeError}
                </p>
            )}

            {!closed && signoff.closeBlockers.length > 0 && item.canClose && (
                <p className="text-sm text-amber-700 dark:text-amber-300">
                    Belum bisa ditutup: {signoff.closeBlockers.join(' ')}
                </p>
            )}

            <ExitInterview item={item} />

            {item.checklist && (
                <section className="surface space-y-2 p-4">
                    <h2 className="label-mono">Ringkasan checklist</h2>
                    <ul className="divide-y text-sm">
                        {item.checklist.groups.map((group) => (
                            <li
                                key={group.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-2"
                            >
                                <span>
                                    {group.name}{' '}
                                    <span className="label-mono">
                                        {group.owner ?? 'Admin HR'}
                                    </span>
                                </span>
                                <span className="flex items-center gap-2">
                                    {group.done}/{group.total} selesai
                                    <Badge variant="secondary">
                                        {group.statusLabel}
                                    </Badge>
                                    {group.status === 'done' && (
                                        <Check className="size-4" />
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <ExitDocuments item={item} />
        </div>
    );
}
