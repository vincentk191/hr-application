import { Form, Head } from '@inertiajs/react';
import { Building2, Lock, TriangleAlert, Upload } from 'lucide-react';
import type { ReactNode } from 'react';
import { InfoHint } from '@/components/info-hint';
import InputError from '@/components/input-error';
import LimitedFileInput from '@/components/limited-file-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index, importMethod } from '@/routes/employees';
import { store, template } from '@/routes/employees/import';
import type { BreadcrumbItem } from '@/types';

type ImportResult = {
    total: number;
    created: number;
    duplicates: number;
    rejected: number;
    errors: string[];
    dryRun: boolean;
    /** The file had bank, NPWP, KK or BPJS columns that were left unread: the importer may not handle them. */
    sensitiveSkipped: boolean;
};

type Props = {
    company: string | null;
    canImportSensitive: boolean;
    maxRows: number;
    result: ImportResult | null;
};

/**
 * Bulk import of employees from HR's data sheet, as an Excel file or a CSV (EMP-DIR-010). Only ever
 * creates: existing employee numbers are skipped, never overwritten. "Hanya periksa" runs the whole
 * check without saving anything.
 */
export default function EmployeesImport({
    company,
    canImportSensitive,
    maxRows,
    result,
}: Props) {
    return (
        <>
            <Head title="Impor karyawan" />

            <div className="flex max-w-3xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="label-mono">Impor dari Excel atau CSV</p>
                    {company ? (
                        <Badge
                            variant="secondary"
                            className="gap-1 font-normal"
                        >
                            <Building2 aria-hidden />
                            {company}
                        </Badge>
                    ) : (
                        <Badge
                            variant="outline"
                            className="gap-1 border-pending/60 bg-pending/10 font-normal text-pending-text"
                        >
                            <TriangleAlert aria-hidden />
                            Pilih perusahaan
                            <InfoHint label="Perusahaan belum dipilih">
                                Pilih satu perusahaan di pemilih perusahaan
                                sebelum mengimpor.
                            </InfoHint>
                        </Badge>
                    )}
                </div>

                <Form
                    action={store().url}
                    method="post"
                    resetOnSuccess={['file']}
                    className="surface grid gap-3 p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <label htmlFor="file" className="text-sm">
                                    Berkas Excel (.xlsx) atau CSV
                                </label>
                                <LimitedFileInput
                                    id="file"
                                    name="file"
                                    accept=".xlsx,.xls,.csv,.txt"
                                    maxSizeMb={2}
                                    required
                                />
                                <InputError message={errors.file} />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="dry_run"
                                    value="1"
                                />
                                Hanya periksa, jangan simpan
                            </label>

                            <div className="flex flex-wrap gap-2">
                                <span
                                    title={
                                        company === null
                                            ? 'Pilih perusahaan dulu'
                                            : undefined
                                    }
                                >
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || company === null
                                        }
                                    >
                                        <Upload
                                            className="size-4"
                                            aria-hidden
                                        />
                                        Impor
                                    </Button>
                                </span>
                                <Button asChild variant="outline">
                                    <a href={template().url}>Unduh templat</a>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {result && <ResultCard result={result} />}

                <RulesCard
                    company={company}
                    maxRows={maxRows}
                    canImportSensitive={canImportSensitive}
                />
            </div>
        </>
    );
}

/**
 * What the file held, as one bar split into what was added (or would be), what was skipped as a duplicate and what was
 * refused, with the same three counts under it, and the reason for each refused row.
 */
function ResultCard({ result }: { result: ImportResult }) {
    const parts = [
        {
            key: 'created',
            label: result.dryRun ? 'Akan ditambahkan' : 'Ditambahkan',
            count: result.created,
            color: 'bg-success',
        },
        {
            key: 'duplicates',
            label: 'Duplikat dilewati',
            count: result.duplicates,
            color: 'bg-pending',
        },
        {
            key: 'rejected',
            label: 'Ditolak',
            count: result.rejected,
            color: 'bg-danger',
        },
    ];

    // The bar is the whole file, so it never exceeds it even if the counts do not quite add up to the rows read.
    const whole = Math.max(
        result.total,
        parts.reduce((sum, part) => sum + part.count, 0),
        1,
    );

    return (
        <section className="surface p-4">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="font-medium">
                    {result.dryRun
                        ? 'Hasil pemeriksaan (belum disimpan)'
                        : 'Hasil impor'}
                </h2>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {result.total} baris dibaca
                </span>
            </div>

            <div
                role="img"
                aria-label={parts
                    .map((part) => `${part.label} ${part.count}`)
                    .join(', ')}
                className="mt-3 flex h-3 gap-0.5 overflow-hidden rounded-full bg-muted"
            >
                {parts
                    .filter((part) => part.count > 0)
                    .map((part) => (
                        <div
                            key={part.key}
                            className={cn('h-full min-w-1', part.color)}
                            style={{
                                width: `${(part.count / whole) * 100}%`,
                            }}
                        />
                    ))}
            </div>

            <dl className="mt-3 grid grid-cols-3 gap-3">
                {parts.map((part) => (
                    <div key={part.key} className="min-w-0">
                        <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span
                                className={cn(
                                    'size-2 shrink-0 rounded-full',
                                    part.color,
                                )}
                                aria-hidden
                            />
                            <span className="truncate">{part.label}</span>
                        </dt>
                        <dd className="text-2xl font-semibold tabular-nums">
                            {part.count}
                        </dd>
                    </div>
                ))}
            </dl>

            {result.sensitiveSkipped && (
                <div className="mt-3">
                    <Badge
                        variant="outline"
                        className="gap-1 border-pending/60 bg-pending/10 font-normal text-pending-text"
                    >
                        <Lock aria-hidden />
                        Kolom sensitif dilewati
                        <InfoHint label="Kolom sensitif">
                            Kolom sensitif di berkas (rekening, NPWP, KK, BPJS,
                            dan sejenisnya) tidak dibaca karena Anda tidak
                            berhak mengelolanya.
                        </InfoHint>
                    </Badge>
                </div>
            )}

            {result.errors.length > 0 && (
                <ul className="mt-3 list-disc space-y-1 border-t pt-3 pl-5 text-sm text-destructive">
                    {result.errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/**
 * Only what a person has to follow that the template does not already say: the columns are in the template, so they are not
 * listed here.
 */
function RulesCard({
    company,
    maxRows,
    canImportSensitive,
}: {
    company: string | null;
    maxRows: number;
    canImportSensitive: boolean;
}) {
    return (
        <section className="surface p-4 text-sm">
            <h2 className="font-medium">Aturan</h2>

            <dl className="mt-3 grid gap-y-1 sm:grid-cols-[9.5rem_minmax(0,1fr)] sm:gap-x-6 sm:gap-y-4">
                <Rule label="Templat">
                    <p>
                        Hapus baris contoh (baris 2) sebelum mengunggah. Urutan
                        kolom bebas, nama kolom jangan diubah.
                    </p>
                </Rule>

                <Rule label="Wajib diisi">
                    <Pills
                        items={[
                            'Nomor Induk Karyawan',
                            'Nama Karyawan',
                            'No KTP (16 digit)',
                        ]}
                    />
                </Rule>

                <Rule label="Perusahaan">
                    <p>
                        Nama Perusahaan di berkas harus{' '}
                        {company ? (
                            <strong className="text-foreground">
                                {company}
                            </strong>
                        ) : (
                            'perusahaan yang dipilih'
                        )}
                        . Berkas ekspor perusahaan lain: ganti dulu namanya.
                    </p>
                    <p>
                        NPWP perusahaan diisi dari berkas hanya bila perusahaan
                        belum punya.
                    </p>
                </Rule>

                <Rule label="Harus sudah ada">
                    <p>
                        Lokasi kerja (cabang), Divisi (departemen) dan Posisi,
                        persis sesuai nama: impor tidak membuatnya. Cabang boleh
                        kosong bila perusahaan hanya punya satu.
                    </p>
                    <p>
                        Supervisor: nomor induk atau nama lengkap, dari karyawan
                        yang sudah ada atau yang ada di berkas yang sama.
                    </p>
                </Rule>

                <Rule
                    label="Pilihan nilai"
                    hint='Pilihan lainnya ada di sheet "Petunjuk" pada templat.'
                >
                    <Choice
                        label="Status Karyawan"
                        items={['Tetap', 'Kontrak', 'Percobaan', 'Magang']}
                    />
                    <Choice
                        label="Zona"
                        items={['Hijau', 'Merah', 'Hitam']}
                        note="kosong = Hijau"
                    />
                    <Choice
                        label="Tanggal"
                        items={['2026-12-31', '31/12/2026']}
                        note="hari dulu"
                    />
                </Rule>

                <Rule label="Dilewati">
                    <p>
                        Nomor induk yang sudah ada di perusahaan ini dilewati,
                        tidak diubah. Maksimal {maxRows} baris.
                    </p>
                </Rule>

                <Rule label="Data sensitif">
                    <p>
                        {canImportSensitive
                            ? 'Rekening, NPWP, KK, nama ibu, PTKP, BPJS dan asuransi ikut diimpor karena Anda berhak mengelolanya.'
                            : 'Rekening, NPWP, KK, nama ibu, PTKP, BPJS dan asuransi dilewati karena Anda tidak berhak mengelolanya; minta HR yang berhak untuk mengisinya.'}{' '}
                        Gaji dan data kesehatan tidak diimpor.
                    </p>
                </Rule>
            </dl>
        </section>
    );
}

/** One rule: its name on the left (above it on a phone) and what to do on the right. */
function Rule({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <>
            <dt className="mt-3 flex items-start gap-1 font-medium text-foreground first:mt-0 sm:mt-0">
                {label}
                {hint && <InfoHint label={label}>{hint}</InfoHint>}
            </dt>
            <dd className="grid gap-1.5 text-muted-foreground">{children}</dd>
        </>
    );
}

function Pills({ items }: { items: string[] }) {
    return (
        <span className="flex flex-wrap gap-1.5">
            {items.map((item) => (
                <Badge key={item} variant="outline" className="font-normal">
                    {item}
                </Badge>
            ))}
        </span>
    );
}

/** A column and the values it accepts, with a short note such as what an empty cell means. */
function Choice({
    label,
    items,
    note,
}: {
    label: string;
    items: string[];
    note?: string;
}) {
    return (
        <span className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
            <span className="w-28 shrink-0">{label}</span>
            <Pills items={items} />
            {note && <span className="text-xs">({note})</span>}
        </span>
    );
}

EmployeesImport.layout = {
    breadcrumbs: [
        { title: 'Karyawan', href: index() },
        { title: 'Impor Karyawan', href: importMethod() },
    ] satisfies BreadcrumbItem[],
};
