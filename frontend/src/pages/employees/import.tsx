import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import LimitedFileInput from '@/components/limited-file-input';
import { Button } from '@/components/ui/button';
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
    columns: { name: string; sensitive: boolean }[];
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
    columns,
    canImportSensitive,
    maxRows,
    result,
}: Props) {
    return (
        <>
            <Head title="Impor karyawan" />

            <div className="flex max-w-3xl flex-col gap-4 p-4">
                <div>
                    <p className="label-mono">Impor dari Excel atau CSV</p>
                    <p className="text-sm text-muted-foreground">
                        {company
                            ? `Karyawan baru ditambahkan ke ${company}.`
                            : 'Pilih satu perusahaan di pemilih perusahaan sebelum mengimpor.'}
                    </p>
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
                                <Button
                                    type="submit"
                                    disabled={processing || company === null}
                                >
                                    Impor
                                </Button>
                                <Button asChild variant="outline">
                                    <a href={template().url}>Unduh templat</a>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {result && <ResultCard result={result} />}

                <section className="surface p-4 text-sm text-muted-foreground">
                    <h2 className="font-medium text-foreground">Aturan</h2>
                    <ul className="mt-2 list-disc space-y-1 pl-5">
                        <li>
                            Kolom dikenali dari baris judul, urutannya bebas.
                            Yang wajib hanya Nomor Induk Karyawan, Nama
                            Karyawan, dan No KTP (16 digit). Templat memuat
                            semua kolom:{' '}
                            {columns.map((column) => column.name).join(', ')}.
                        </li>
                        <li>
                            Nama Perusahaan di berkas harus perusahaan yang
                            dipilih. Berkas hasil ekspor perusahaan lain perlu
                            diganti dulu namanya. NPWP perusahaan terisi dari
                            berkas hanya bila perusahaan belum punya.
                        </li>
                        <li>
                            Nomor induk yang sudah ada di perusahaan ini
                            dilewati, tidak diubah. Maksimal {maxRows} baris.
                        </li>
                        <li>
                            Lokasi kerja (cabang), Divisi (departemen), dan
                            Posisi harus sudah ada, sesuai nama. Jabatan adalah
                            isian bebas. Supervisor boleh nomor induk atau nama
                            lengkap, dari karyawan yang sudah ada atau yang ada
                            di berkas yang sama.
                        </li>
                        <li>
                            Status Karyawan: Tetap / Kontrak / Percobaan /
                            Magang. Zona: Hijau / Merah / Hitam (kosong =
                            Hijau). Tanggal: 2026-12-31 atau 31/12/2026 (hari
                            dulu). Sheet &quot;Petunjuk&quot; di templat memuat
                            pilihan lainnya.
                        </li>
                        <li>
                            {canImportSensitive
                                ? 'Kolom sensitif (rekening, NPWP, KK, nama ibu, PTKP, BPJS, asuransi) ikut diimpor karena Anda berhak mengelolanya. Gaji dan data kesehatan tidak diimpor.'
                                : 'Kolom sensitif (rekening, NPWP, KK, nama ibu, PTKP, BPJS, asuransi) dilewati karena Anda tidak berhak mengelolanya; minta HR yang berhak untuk mengisinya. Gaji dan data kesehatan tidak diimpor.'}
                        </li>
                    </ul>
                </section>
            </div>
        </>
    );
}

function ResultCard({ result }: { result: ImportResult }) {
    return (
        <section className="surface p-4">
            <h2 className="font-medium">
                {result.dryRun
                    ? 'Hasil pemeriksaan (belum disimpan)'
                    : 'Hasil impor'}
            </h2>
            <p className="mt-1 text-sm">
                {result.total} baris dibaca ·{' '}
                {result.dryRun ? 'akan ditambahkan' : 'ditambahkan'}{' '}
                <strong>{result.created}</strong> · duplikat dilewati{' '}
                <strong>{result.duplicates}</strong> · ditolak{' '}
                <strong>{result.rejected}</strong>
            </p>
            {result.sensitiveSkipped && (
                <p className="mt-2 text-sm text-amber-600 dark:text-amber-400">
                    Kolom sensitif di berkas (rekening, NPWP, KK, BPJS, dan
                    sejenisnya) tidak dibaca karena Anda tidak berhak
                    mengelolanya.
                </p>
            )}
            {result.errors.length > 0 && (
                <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-destructive">
                    {result.errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </section>
    );
}

EmployeesImport.layout = {
    breadcrumbs: [
        { title: 'Karyawan', href: index() },
        { title: 'Impor karyawan', href: importMethod() },
    ] satisfies BreadcrumbItem[],
};
