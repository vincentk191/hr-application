import { Form, router } from '@inertiajs/react';
import { Download, Lock } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import LimitedFileInput from '@/components/limited-file-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/documents';
import { store as storeVersion } from '@/routes/documents/versions';
import type { EmployeeDocumentRow, ValueOption } from '@/types/employee';

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx';

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function ExpiryBadge({ document }: { document: EmployeeDocumentRow }) {
    if (document.expiryStatus === 'expired') {
        return (
            <Badge variant="destructive">
                Kedaluwarsa {document.expiresOn}
            </Badge>
        );
    }

    if (document.expiryStatus === 'expiring') {
        return (
            <Badge
                variant="outline"
                className="border-amber-500 text-amber-700"
            >
                Segera berakhir {document.expiresOn}
            </Badge>
        );
    }

    return document.expiresOn ? (
        <span className="text-xs text-muted-foreground">
            Berlaku s/d {document.expiresOn}
        </span>
    ) : null;
}

function DocumentFields({
    categories,
    canRestrict,
    document,
    errors,
}: {
    categories: ValueOption[];
    canRestrict: boolean;
    document?: EmployeeDocumentRow;
    errors: Record<string, string>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`title-${document?.id ?? 'new'}`}>Judul</Label>
                <Input
                    id={`title-${document?.id ?? 'new'}`}
                    name="title"
                    defaultValue={document?.title ?? ''}
                    required
                />
                <InputError message={errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`category-${document?.id ?? 'new'}`}>
                    Kategori
                </Label>
                <select
                    id={`category-${document?.id ?? 'new'}`}
                    name="category"
                    defaultValue={document?.category ?? 'contract'}
                    className={selectClasses}
                >
                    {categories.map((category) => (
                        <option key={category.value} value={category.value}>
                            {category.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.category} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`expires-${document?.id ?? 'new'}`}>
                    Berlaku sampai
                </Label>
                <Input
                    id={`expires-${document?.id ?? 'new'}`}
                    name="expires_on"
                    type="date"
                    defaultValue={document?.expiresOn ?? ''}
                />
                <InputError message={errors.expires_on} />
            </div>
            {canRestrict && (
                <label className="flex items-center gap-2 text-sm sm:self-end sm:pb-2">
                    <input
                        type="checkbox"
                        name="is_restricted"
                        value="1"
                        defaultChecked={document?.isRestricted ?? false}
                    />
                    Dokumen terbatas
                </label>
            )}
        </>
    );
}

function DocumentCard({
    document,
    categories,
    canRestrict,
}: {
    document: EmployeeDocumentRow;
    categories: ValueOption[];
    canRestrict: boolean;
}) {
    const [panel, setPanel] = useState<'history' | 'version' | 'edit' | null>(
        null,
    );
    const toggle = (next: 'history' | 'version' | 'edit') =>
        setPanel((current) => (current === next ? null : next));

    const current = document.versions[0];

    return (
        <li className="surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{document.title}</span>
                        <Badge variant="secondary">
                            {document.categoryLabel}
                        </Badge>
                        {document.isRestricted && (
                            <Badge variant="outline" className="gap-1">
                                <Lock className="size-3" /> Terbatas
                            </Badge>
                        )}
                        <ExpiryBadge document={document} />
                    </div>
                    {current && (
                        <p className="text-xs text-muted-foreground">
                            v{current.versionNo} · {current.name} ·{' '}
                            {formatSize(current.sizeBytes)} ·{' '}
                            {current.uploadedAt}
                            {current.uploadedBy
                                ? ` oleh ${current.uploadedBy}`
                                : ''}
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    {current && (
                        <Button asChild size="sm" variant="outline">
                            <a href={current.downloadUrl}>
                                <Download /> Unduh
                            </a>
                        </Button>
                    )}
                    {document.versions.length > 1 && (
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            onClick={() => toggle('history')}
                        >
                            Riwayat ({document.versions.length})
                        </Button>
                    )}
                    {document.canUpdate && (
                        <>
                            <Button
                                size="sm"
                                variant="ghost"
                                type="button"
                                onClick={() => toggle('version')}
                            >
                                Versi baru
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                type="button"
                                onClick={() => toggle('edit')}
                            >
                                Ubah
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                type="button"
                                className="text-destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Hapus dokumen "${document.title}"?`,
                                        )
                                    ) {
                                        router.delete(
                                            destroy(document.id).url,
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                Hapus
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {panel === 'history' && (
                <ul className="mt-3 divide-y rounded-lg border text-sm">
                    {document.versions.map((version) => (
                        <li
                            key={version.id}
                            className="flex flex-wrap items-center justify-between gap-2 p-2"
                        >
                            <span>
                                v{version.versionNo} · {version.name} ·{' '}
                                {formatSize(version.sizeBytes)} ·{' '}
                                {version.uploadedAt}
                                {version.note ? ` · ${version.note}` : ''}
                            </span>
                            <a
                                href={version.downloadUrl}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Unduh
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            {panel === 'version' && (
                <Form
                    action={storeVersion(document.id).url}
                    method="post"
                    resetOnSuccess
                    onSuccess={() => setPanel(null)}
                    className="mt-3 grid gap-3 rounded-lg border p-3 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor={`file-${document.id}`}>
                                    Berkas baru
                                </Label>
                                <LimitedFileInput
                                    id={`file-${document.id}`}
                                    name="file"
                                    accept={ACCEPT}
                                    maxSizeMb={10}
                                    required
                                />
                                <InputError message={errors.file} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`note-${document.id}`}>
                                    Catatan perubahan
                                </Label>
                                <Input id={`note-${document.id}`} name="note" />
                                <InputError message={errors.note} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`vexpires-${document.id}`}>
                                    Berlaku sampai (opsional)
                                </Label>
                                <Input
                                    id={`vexpires-${document.id}`}
                                    name="expires_on"
                                    type="date"
                                />
                                <InputError message={errors.expires_on} />
                            </div>
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Unggah versi baru
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}

            {panel === 'edit' && (
                <Form
                    action={update(document.id).url}
                    method="put"
                    onSuccess={() => setPanel(null)}
                    className="mt-3 grid gap-3 rounded-lg border p-3 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentFields
                                categories={categories}
                                canRestrict={canRestrict}
                                document={document}
                                errors={errors}
                            />
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Simpan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </li>
    );
}

/**
 * The Documents tab of the employee profile (EMP-DOC): filing, categorising, versioning and the
 * expiry flags. What is listed is already filtered on the server by the document policy, so a
 * restricted document the viewer may not see never reaches this component.
 */
export function EmployeeDocuments({
    employeeId,
    documents,
    categories,
    canManage,
    canRestrict,
}: {
    employeeId: number;
    documents: EmployeeDocumentRow[];
    categories: ValueOption[];
    canManage: boolean;
    canRestrict: boolean;
}) {
    return (
        <div className="flex flex-col gap-4">
            {canManage && (
                <Form
                    action={store(employeeId).url}
                    method="post"
                    resetOnSuccess
                    className="surface grid gap-3 p-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <h2 className="font-medium sm:col-span-2">
                                Tambah dokumen
                            </h2>
                            <DocumentFields
                                categories={categories}
                                canRestrict={canRestrict}
                                errors={errors}
                            />
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="document-file">Berkas</Label>
                                <LimitedFileInput
                                    id="document-file"
                                    name="file"
                                    accept={ACCEPT}
                                    maxSizeMb={10}
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    PDF, gambar, Word atau Excel, maksimal 10
                                    MB.
                                </p>
                                <InputError message={errors.file} />
                            </div>
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Unggah dokumen
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}

            {documents.length === 0 ? (
                <p className="surface p-6 text-center text-sm text-muted-foreground">
                    Belum ada dokumen.
                </p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {documents.map((document) => (
                        <DocumentCard
                            key={document.id}
                            document={document}
                            categories={categories}
                            canRestrict={canRestrict}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}
