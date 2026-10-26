import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    adhoc,
    confirm,
    done,
    involvement,
    owner as reassignOwner,
    refresh,
    remove,
    reopen,
    select,
} from '@/routes/offboarding/checklist';
import type {
    OffboardingChecklist,
    OffboardingChecklistGroup,
    OffboardingChecklistItem,
} from '@/types/offboarding';

const STATUS_VARIANT: Record<
    OffboardingChecklistGroup['status'],
    'default' | 'secondary' | 'outline'
> = {
    choosing: 'secondary',
    running: 'default',
    done: 'default',
    not_involved: 'outline',
};

const visit = { preserveScroll: true, preserveState: true } as const;

function AdhocForm({ caseId, groupId }: { caseId: number; groupId: number }) {
    const [name, setName] = useState('');
    const [kind, setKind] = useState('process');

    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                router.post(
                    adhoc([caseId, groupId]).url,
                    { name, kind },
                    {
                        ...visit,
                        onSuccess: () => setName(''),
                    },
                );
            }}
        >
            <div className="grid gap-1">
                <label className="label-mono" htmlFor={`adhoc-${groupId}`}>
                    Tambah item untuk kasus ini
                </label>
                <Input
                    id={`adhoc-${groupId}`}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    placeholder="mis. Alihkan klien, teruskan email"
                    className="w-72"
                />
            </div>
            <select
                value={kind}
                onChange={(event) => setKind(event.target.value)}
                className="h-9 rounded-xl border border-input bg-card px-3 text-sm"
                aria-label="Jenis item"
            >
                <option value="process">Proses</option>
                <option value="digital">Akun digital</option>
                <option value="physical">Fisik</option>
            </select>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={name.trim() === ''}
            >
                Tambah
            </Button>
        </form>
    );
}

/**
 * One item of a group. The owner ticks it with the checkbox, and un-ticks it the same way; anyone who may
 * not act on it sees the box disabled.
 */
function ItemRow({ item }: { item: OffboardingChecklistItem }) {
    return (
        <li
            className="flex flex-wrap items-start justify-between gap-2 py-2"
            data-test="checklist-item"
        >
            <div className="flex min-w-0 items-start gap-3">
                <Checkbox
                    className="mt-0.5 size-5 rounded-none border-2"
                    checked={item.done}
                    disabled={!item.canTick}
                    aria-label={`${item.done ? 'Batalkan' : 'Tandai selesai'} ${item.name}`}
                    data-test="tick-item"
                    onCheckedChange={(checked) =>
                        router.put(
                            (checked ? done(item.id) : reopen(item.id)).url,
                            {},
                            visit,
                        )
                    }
                />
                <div className="min-w-0">
                    <p
                        className={
                            item.done
                                ? 'text-muted-foreground line-through'
                                : ''
                        }
                    >
                        {item.name}{' '}
                        <span className="label-mono">
                            {item.kind}
                            {item.isAdhoc ? ' · khusus kasus ini' : ''}
                        </span>
                    </p>
                    {item.done && (
                        <p className="label-mono">
                            Selesai {item.doneAt}
                            {item.doneBy ? ` · ${item.doneBy}` : ''}
                            {item.note ? ` · “${item.note}”` : ''}
                        </p>
                    )}
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                {item.canRemove && (
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`Hapus ${item.name}`}
                        onClick={() =>
                            router.delete(remove(item.id).url, visit)
                        }
                    >
                        <X />
                    </Button>
                )}
            </div>
        </li>
    );
}

function Group({
    group,
    caseId,
    ownerOptions,
}: {
    group: OffboardingChecklistGroup;
    caseId: number;
    ownerOptions: OffboardingChecklist['ownerOptions'];
}) {
    return (
        <section
            className="surface space-y-3 p-4"
            data-test={`group-${group.name}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 className="font-semibold">{group.name}</h3>
                    <p className="label-mono">
                        {group.ownerIsHr
                            ? 'Admin HR'
                            : [group.owner, group.ownerTitle]
                                  .filter(Boolean)
                                  .join(' · ')}
                        {' · '}
                        {group.done}/{group.total} selesai
                    </p>
                    {group.ownerUnavailable && (
                        <p className="text-xs text-amber-700 dark:text-amber-300">
                            Pemilik ({group.owner}) tidak aktif; Admin HR
                            menanganinya sampai dialihkan.
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={STATUS_VARIANT[group.status]}>
                        {group.statusLabel}
                    </Badge>
                    {group.canReassign && (
                        <select
                            aria-label={`Alihkan pemilik ${group.name}`}
                            value={group.ownerId ?? ''}
                            onChange={(event) =>
                                router.put(
                                    reassignOwner([caseId, group.id]).url,
                                    {
                                        user_id: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    },
                                    visit,
                                )
                            }
                            className="h-8 max-w-64 rounded-xl border border-input bg-card px-2 text-xs"
                            data-test="reassign-owner"
                        >
                            <option value="">Admin HR</option>
                            {ownerOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    )}
                    {group.canSetAside && !group.notInvolved && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.put(
                                    involvement([caseId, group.id]).url,
                                    { not_involved: true },
                                    visit,
                                )
                            }
                        >
                            Tandai tidak terkait
                        </Button>
                    )}
                    {group.notInvolved && group.canSetAside && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.put(
                                    involvement([caseId, group.id]).url,
                                    { not_involved: false },
                                    visit,
                                )
                            }
                        >
                            Ikutkan lagi
                        </Button>
                    )}
                </div>
            </div>

            {group.canSelect && group.available.length > 0 && (
                <div className="space-y-1">
                    <p className="label-mono">
                        Pilih item yang berlaku untuk karyawan ini
                    </p>
                    <ul className="divide-y rounded-xl border">
                        {group.available.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                            >
                                <span>
                                    {item.name}{' '}
                                    <span className="label-mono">
                                        {item.kind}
                                    </span>
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    data-test="select-item"
                                    onClick={() =>
                                        router.post(
                                            select([caseId, group.id]).url,
                                            { offboarding_item_id: item.id },
                                            visit,
                                        )
                                    }
                                >
                                    Pilih
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {group.items.length > 0 ? (
                <ul className="divide-y">
                    {group.items.map((item) => (
                        <ItemRow key={item.id} item={item} />
                    ))}
                </ul>
            ) : (
                !group.notInvolved && (
                    <p className="text-sm text-muted-foreground">
                        {group.confirmed
                            ? 'Tidak ada item yang berlaku.'
                            : 'Belum ada item dipilih.'}
                    </p>
                )
            )}

            {group.canAddItem && (
                <AdhocForm caseId={caseId} groupId={group.id} />
            )}

            {group.canSelect && (
                <div className="flex items-center gap-3 border-t pt-3">
                    <Button
                        size="sm"
                        data-test="confirm-list"
                        onClick={() =>
                            router.post(
                                confirm([caseId, group.id]).url,
                                {},
                                visit,
                            )
                        }
                    >
                        Daftar selesai
                    </Button>
                    <span className="label-mono">
                        Mengunci pilihan; tekan walau tidak ada item yang
                        berlaku
                    </span>
                </div>
            )}
        </section>
    );
}

/**
 * Stage 2 · Checklist (OFF-CHK): a group for each department, owned by its supervisor. They pick which
 * items apply, confirm the list with "Daftar selesai", then tick the items with their checkbox as they are done. The case
 * cannot go on to sign-off until every list is confirmed and every item is ticked (OFF-CHK-001).
 */
export function OffboardingChecklistPanel({
    checklist,
    caseId,
    blockedMessage,
}: {
    checklist: OffboardingChecklist;
    caseId: number;
    /** The server's refusal when "Lanjut ke sign-off" was pressed too early. */
    blockedMessage?: string;
}) {
    const { progress } = checklist;

    return (
        <div className="space-y-4">
            <section
                className={`surface flex flex-wrap items-center justify-between gap-3 p-4 ${
                    progress.ready ? '' : 'border-amber-500/50'
                }`}
                data-test="checklist-summary"
            >
                <div>
                    <p className="text-lg font-extrabold">
                        {progress.done} dari {progress.items} item selesai
                    </p>
                    <p className="label-mono">
                        {progress.settled} dari {progress.groups} departemen
                        sudah mengonfirmasi daftarnya
                    </p>
                </div>
                {progress.ready ? (
                    <Badge>Lengkap · sign-off bisa dibuka</Badge>
                ) : (
                    <p className="max-w-xl text-sm text-amber-700 dark:text-amber-300">
                        Kasus belum bisa lanjut ke sign-off:{' '}
                        {progress.blockers.join(' ')} Sign-off atasan dan HR
                        terkunci sampai semuanya selesai.
                    </p>
                )}
            </section>

            {checklist.canRefresh && (
                <section
                    className="surface flex flex-wrap items-center justify-between gap-3 p-4"
                    data-test="refresh-checklist"
                >
                    <p className="text-sm">
                        {checklist.newItems > 0
                            ? `${checklist.newItems} item baru ada di daftar induk sejak checklist ini dibuka.`
                            : 'Checklist sudah sesuai daftar induk.'}
                    </p>
                    <Button
                        size="sm"
                        variant={checklist.newItems > 0 ? 'default' : 'outline'}
                        onClick={() =>
                            router.post(refresh(caseId).url, {}, visit)
                        }
                    >
                        Perbarui dari daftar induk
                    </Button>
                </section>
            )}

            {blockedMessage && (
                <p className="text-sm text-destructive" role="alert">
                    {blockedMessage}
                </p>
            )}

            {checklist.groups.map((group) => (
                <Group
                    key={group.id}
                    group={group}
                    caseId={caseId}
                    ownerOptions={checklist.ownerOptions}
                />
            ))}
        </div>
    );
}
