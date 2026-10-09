import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
};

type Props = {
    company: string | null;
    columns: string[];
    maxRows: number;
    result: ImportResult | null;
};

/**
 * Bulk import of employees from a CSV (EMP-DIR-010). Only ever creates: existing employee numbers
 * are skipped, never overwritten. "Hanya periksa" runs the whole check without saving anything.
 */
export default function EmployeesImport({
    company,
    columns,
    maxRows,
    result,
}: Props) {
    return (
        <>
            <Head title="Impor karyawan" />

            <div className="flex max-w-3xl flex-col gap-4 p-4">
                <div>
                    <p className="label-mono">Impor dari CSV</p>
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
                                    Berkas CSV
                                </label>
                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,.txt"
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
                            Kolom dikenali dari baris judul:{' '}
                            {columns.join(', ')}. Hanya ID karyawan dan Nama
                            yang wajib.
                        </li>
                        <li>
                            ID karyawan yang sudah ada di perusahaan ini
                            dilewati, tidak diubah. Maksimal {maxRows} baris.
                        </li>
                        <li>
                            Departemen dan jabatan harus sudah ada (sesuai
                            nama). ID atasan boleh karyawan yang sudah ada atau
                            yang ada di berkas yang sama.
                        </li>
                        <li>
                            Status: Aktif / Tidak aktif. Tipe: Tetap / Kontrak /
                            Percobaan / Magang. Tanggal: 2026-12-31 atau
                            31/12/2026 (hari dulu).
                        </li>
                        <li>
                            Data bank, gaji, dan kesehatan tidak diimpor; isi
                            lewat profil karyawan.
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
