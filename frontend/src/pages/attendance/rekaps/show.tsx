import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { device } from '@/routes/attendance';
import { confirm, destroy } from '@/routes/attendance/rekaps';
import { update as updateRow } from '@/routes/attendance/rekaps/rows';
import type { BreadcrumbItem } from '@/types';
import type { RekapLine, RekapStatus, RekapSummary } from '@/types/attendance';

type Props = {
    rekap: {
        id: number;
        status: RekapStatus;
        file: string | null;
        branch: string | null;
        fileBranch: string | null;
        /** The file says it is for another Cabang than the one chosen. */
        branchMismatch: boolean;
        periodStart: string;
        periodEnd: string;
        periodLabel: string | null;
        by: string | null;
        confirmedAt: string | null;
    };
    rows: RekapLine[];
    summary: RekapSummary;
    /** Active employees of the Cabang who are not in the file. */
    notInFile: string[];
    /** A confirmed file for the same Cabang and dates exists and this one will replace it. */
    replaces: boolean;
    overlaps: { id: number; periodStart: string; periodEnd: string }[];
    employees: { id: number; label: string }[];
    errors: { confirm?: string };
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const selectClasses =
    'border-input bg-card h-9 max-w-56 min-w-0 rounded-xl border px-2 text-sm';

function Notice({
    tone,
    children,
}: {
    tone: 'warning' | 'info';
    children: React.ReactNode;
}) {
    return (
        <p
            className={`rounded-xl border p-3 text-sm ${
                tone === 'warning'
                    ? 'border-destructive/40 bg-destructive/5'
                    : 'bg-muted/50'
            }`}
        >
            {children}
        </p>
    );
}

function Tile({
    label,
    value,
    alert = false,
}: {
    label: string;
    value: string | number;
    alert?: boolean;
}) {
    return (
        <div className="surface p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={`text-lg font-semibold ${alert ? 'text-destructive' : ''}`}
            >
                {value}
            </div>
        </div>
    );
}

/** What HR can do with a line: tie it to someone of the Cabang, or leave it out. */
function LinkForm({
    rekapId,
    line,
    employees,
}: {
    rekapId: number;
    line: RekapLine;
    employees: Props['employees'];
}) {
    return (
        <div className="flex flex-wrap items-start gap-2">
            <Form
                action={updateRow([rekapId, line.id]).url}
                method="put"
                className="flex flex-wrap items-center gap-2"
                options={{ preserveScroll: true }}
            >
                {({ processing, errors }) => (
                    <>
                        <select
                            name="employee_id"
                            required
                            defaultValue=""
                            aria-label={`Karyawan untuk ${line.name}`}
                            className={selectClasses}
                        >
                            <option value="">Pilih karyawan…</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.label}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" size="sm" disabled={processing}>
                            Tautkan
                        </Button>
                        <InputError message={errors.employee_id} />
                    </>
                )}
            </Form>
            {line.status !== 'skipped' && (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.put(
                            updateRow([rekapId, line.id]).url,
                            { skip: true },
                            { preserveScroll: true },
                        )
                    }
                >
                    Lewati
                </Button>
            )}
        </div>
    );
}

/**
 * Checking a branch's rekapitulasi before it counts (ATT-REK): which employee each name was matched to,
 * which names found nobody, where the file's Zona or deduction differs from the app's, and then
 * confirming. Once confirmed the page is a read-only record.
 */
export default function AttendanceRekapShow({
    rekap,
    rows,
    summary,
    notInFile,
    replaces,
    overlaps,
    employees,
    errors,
}: Props) {
    const draft = rekap.status === 'draft';

    return (
        <>
            <Head title="Periksa rekapitulasi" />

            <div className="flex flex-col gap-4 p-4">
                <section className="surface p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-lg font-semibold">
                            Rekapitulasi {rekap.branch}
                        </h1>
                        <Badge variant={draft ? 'secondary' : 'default'}>
                            {
                                {
                                    draft: 'Draf — belum berlaku',
                                    confirmed: 'Dikonfirmasi',
                                    superseded: 'Sudah diganti',
                                }[rekap.status]
                            }
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {rekap.periodStart} s.d {rekap.periodEnd}
                        {rekap.periodLabel
                            ? ` · Periode ${rekap.periodLabel}`
                            : ''}
                        {' · '}
                        {rekap.file ?? 'berkas'}
                        {rekap.by ? ` · diunggah ${rekap.by}` : ''}
                    </p>
                </section>

                {rekap.branchMismatch && (
                    <Notice tone="warning">
                        Berkas ini menyebut cabang <b>{rekap.fileBranch}</b>,
                        tetapi yang dipilih <b>{rekap.branch}</b>. Pastikan ini
                        berkas yang benar sebelum mengonfirmasi.
                    </Notice>
                )}
                {draft && replaces && (
                    <Notice tone="info">
                        Sudah ada rekapitulasi terkonfirmasi untuk cabang dan
                        tanggal yang sama. Mengonfirmasi berkas ini
                        menggantikannya.
                    </Notice>
                )}
                {draft && overlaps.length > 0 && (
                    <Notice tone="warning">
                        Tanggalnya tumpang tindih dengan rekapitulasi lain yang
                        sudah dikonfirmasi:{' '}
                        {overlaps
                            .map((o) => `${o.periodStart} s.d ${o.periodEnd}`)
                            .join('; ')}
                        . Hari yang sama bisa terhitung dua kali.
                    </Notice>
                )}

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Tile label="Baris di berkas" value={summary.total} />
                    <Tile label="Cocok" value={summary.matched} />
                    <Tile
                        label="Perlu dicocokkan"
                        value={summary.needAttention}
                        alert={summary.needAttention > 0}
                    />
                    <Tile label="Dilewati" value={summary.skipped} />
                    <Tile
                        label="Zona berbeda"
                        value={summary.zonaMismatches}
                        alert={summary.zonaMismatches > 0}
                    />
                    <Tile
                        label="Potongan berbeda"
                        value={summary.potonganMismatches}
                        alert={summary.potonganMismatches > 0}
                    />
                    <Tile
                        label="Total potongan di berkas"
                        value={rupiah.format(summary.fileTotal)}
                    />
                    <Tile
                        label="Total potongan hitungan aplikasi"
                        value={rupiah.format(summary.computedTotal)}
                        alert={summary.fileTotal !== summary.computedTotal}
                    />
                </div>

                <section className="surface">
                    <div className="p-4">
                        <h2 className="font-medium">Baris rekapitulasi</h2>
                        <p className="text-sm text-muted-foreground">
                            Nama dicocokkan dengan karyawan cabang ini. Zona dan
                            potongan dihitung ulang dari profil karyawan;
                            selisih dengan berkas ditandai merah, dan angka
                            aplikasi yang dipakai.
                        </p>
                    </div>
                    <div className="overflow-x-auto border-t">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">Nama</th>
                                    <th className="p-3 font-medium">Zona</th>
                                    <th className="p-3 font-medium">Alpa</th>
                                    <th className="p-3 font-medium">Izin</th>
                                    <th className="p-3 font-medium">Sakit</th>
                                    <th className="p-3 font-medium">Telat</th>
                                    <th className="p-3 font-medium">
                                        Potongan
                                    </th>
                                    <th className="p-3 font-medium">
                                        Karyawan
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-t align-top"
                                    >
                                        <td className="p-3">
                                            {line.name}
                                            {line.position && (
                                                <div className="text-xs text-muted-foreground">
                                                    {line.position}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            className={`p-3 ${line.zonaMismatch ? 'font-medium text-destructive' : ''}`}
                                        >
                                            {line.zonaFile ?? '—'}
                                            {line.zonaMismatch && (
                                                <div className="text-xs">
                                                    profil:{' '}
                                                    {line.zonaApp ??
                                                        'tidak dikenal'}
                                                </div>
                                            )}
                                        </td>
                                        <td className="p-3">{line.alpa}</td>
                                        <td className="p-3">{line.izin}</td>
                                        <td className="p-3">{line.sakit}</td>
                                        <td className="p-3">{line.telat}</td>
                                        <td
                                            className={`p-3 whitespace-nowrap ${line.potonganMismatch ? 'font-medium text-destructive' : ''}`}
                                        >
                                            {rupiah.format(
                                                line.computedPotongan,
                                            )}
                                            {line.exempt && (
                                                <div className="text-xs text-muted-foreground">
                                                    tanpa potongan
                                                </div>
                                            )}
                                            {line.potonganMismatch && (
                                                <div className="text-xs">
                                                    berkas:{' '}
                                                    {rupiah.format(
                                                        line.filePotongan ?? 0,
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {line.status === 'matched' ? (
                                                line.employee
                                            ) : (
                                                <Badge
                                                    variant={
                                                        line.status ===
                                                        'skipped'
                                                            ? 'secondary'
                                                            : 'destructive'
                                                    }
                                                >
                                                    {line.statusLabel}
                                                </Badge>
                                            )}
                                            {draft &&
                                                line.status !== 'matched' && (
                                                    <div className="mt-2">
                                                        <LinkForm
                                                            rekapId={rekap.id}
                                                            line={line}
                                                            employees={
                                                                employees
                                                            }
                                                        />
                                                    </div>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {draft && notInFile.length > 0 && (
                    <details className="surface p-4 text-sm">
                        <summary className="cursor-pointer font-medium">
                            {notInFile.length} karyawan aktif cabang ini tidak
                            ada di berkas
                        </summary>
                        <p className="mt-2 text-muted-foreground">
                            {notInFile.join(', ')}
                        </p>
                    </details>
                )}

                {draft && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            disabled={summary.needAttention > 0}
                            onClick={() =>
                                router.post(
                                    confirm(rekap.id).url,
                                    {},
                                    {
                                        preserveScroll: true,
                                    },
                                )
                            }
                        >
                            Konfirmasi rekapitulasi
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Buang draf ini? Berkas harus diunggah ulang.',
                                    )
                                ) {
                                    router.delete(destroy(rekap.id).url);
                                }
                            }}
                        >
                            Buang draf
                        </Button>
                        {summary.needAttention > 0 && (
                            <span className="text-sm text-muted-foreground">
                                Cocokkan atau lewati {summary.needAttention}{' '}
                                baris dulu.
                            </span>
                        )}
                        <InputError message={errors.confirm} />
                    </div>
                )}
            </div>
        </>
    );
}

AttendanceRekapShow.layout = {
    breadcrumbs: [
        { title: 'Data absensi', href: device({ query: { tab: 'rekap' } }) },
        { title: 'Periksa rekapitulasi', href: '#' },
    ] satisfies BreadcrumbItem[],
};
