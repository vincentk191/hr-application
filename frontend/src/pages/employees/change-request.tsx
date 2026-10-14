import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PhotoInput } from '@/components/photo-input';
import { ProfileChangeDiff } from '@/components/profile-change-diff';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/profile-changes';
import { store } from '@/routes/employees/changes';
import { show } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { ProfileChange, ProfileFormField } from '@/types/profile-change';

type Props = {
    employee: { id: number; name: string };
    fields: ProfileFormField[];
    values: Record<string, string | null>;
    /** The request waiting for HR, if there is one: only one at a time. */
    open: ProfileChange | null;
    /** The last decided request, so a rejection and its reason are not lost. */
    latest: ProfileChange | null;
};

/**
 * "Ajukan perubahan data" (EMP-CHG-001/002): the employee proposes changes to their own profile.
 * Nothing changes until HR approves. The fields come from the server, so a new one needs no change
 * here.
 */
export default function ProfileChangeRequestPage({
    employee,
    fields,
    values,
    open,
    latest,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Profil saya', href: show(employee.id).url },
        { title: 'Ajukan perubahan data', href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    const groups = Array.from(new Set(fields.map((field) => field.group)));

    return (
        <>
            <Head title="Ajukan perubahan data" />

            <div className="max-w-2xl space-y-6 p-4">
                <header>
                    <h1 className="text-2xl font-extrabold tracking-tight">
                        Ajukan perubahan data
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Ubah data Anda di bawah ini. Data baru berlaku setelah
                        HR menyetujuinya. Jabatan, atasan, gaji, dan data
                        kepegawaian lain hanya diubah HR.
                    </p>
                </header>

                {latest && (
                    <div className="surface space-y-2 p-4">
                        <p className="text-sm">
                            Permintaan terakhir ({latest.reference}):{' '}
                            <Badge
                                variant={
                                    latest.status === 'approved'
                                        ? 'default'
                                        : 'destructive'
                                }
                            >
                                {latest.statusLabel}
                            </Badge>
                        </p>
                        {latest.decisionNote && (
                            <p className="text-sm text-muted-foreground">
                                Alasan: {latest.decisionNote}
                            </p>
                        )}
                        {latest.conflicts.length > 0 && (
                            <p className="text-sm text-destructive">
                                Tidak diterapkan karena sudah diubah HR:{' '}
                                {latest.conflicts.join(', ')}.
                            </p>
                        )}
                    </div>
                )}

                {open ? (
                    <div className="surface space-y-4 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm">
                                {open.reference} ·{' '}
                                <Badge variant="secondary">
                                    {open.statusLabel}
                                </Badge>
                            </p>
                            {open.canWithdraw && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(destroy(open.id).url)
                                    }
                                >
                                    Tarik permintaan
                                </Button>
                            )}
                        </div>
                        <ProfileChangeDiff change={open} />
                        <p className="text-xs text-muted-foreground">
                            Anda baru bisa mengajukan perubahan lain setelah HR
                            memutuskan atau Anda menarik permintaan ini.
                        </p>
                    </div>
                ) : (
                    <Form
                        {...store.form(employee.id)}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <InputError message={errors.form} />

                                {groups.map((group) => (
                                    <fieldset
                                        key={group}
                                        className="surface grid gap-4 p-4"
                                    >
                                        <legend className="label-mono px-1">
                                            {group}
                                        </legend>
                                        {fields
                                            .filter(
                                                (field) =>
                                                    field.group === group,
                                            )
                                            .map((field) => (
                                                <div
                                                    key={field.name}
                                                    className="grid gap-2"
                                                >
                                                    <Label
                                                        htmlFor={field.name}
                                                    >
                                                        {field.label}
                                                    </Label>
                                                    {field.type === 'photo' ? (
                                                        <PhotoInput
                                                            id="photo"
                                                            name="photo"
                                                        />
                                                    ) : field.type ===
                                                      'textarea' ? (
                                                        <textarea
                                                            id={field.name}
                                                            name={field.name}
                                                            rows={3}
                                                            defaultValue={
                                                                values[
                                                                    field.name
                                                                ] ?? ''
                                                            }
                                                            className="rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                                        />
                                                    ) : (
                                                        <Input
                                                            id={field.name}
                                                            name={field.name}
                                                            type={field.type}
                                                            defaultValue={
                                                                values[
                                                                    field.name
                                                                ] ?? ''
                                                            }
                                                        />
                                                    )}
                                                    <InputError
                                                        message={
                                                            errors[
                                                                field.type ===
                                                                'photo'
                                                                    ? 'photo'
                                                                    : field.name
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ))}
                                    </fieldset>
                                ))}

                                <div className="flex items-center gap-3">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="submit-change"
                                    >
                                        {processing && <Spinner />}
                                        Kirim ke HR
                                    </Button>
                                    <Button asChild variant="ghost">
                                        <Link href={show(employee.id).url}>
                                            Batal
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
