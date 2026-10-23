import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { EmployeeTeamTab } from '@/components/employee-team';
import { LeaveRequestDetail } from '@/components/leave-request-detail';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    STATUS_LABEL,
    STATUS_STYLE,
    formatMinutes,
} from '@/lib/attendance-status';
import { edit, show, summary } from '@/routes/employees';
import { create as createLeave } from '@/routes/leave-requests';
import type { Timesheet } from '@/types/attendance';
import type {
    EmployeeDetail,
    EmployeeDocumentRow,
    EmployeeTeam,
    LeaveRow,
} from '@/types/employee';

type Summary = {
    employee: EmployeeDetail;
    documents: EmployeeDocumentRow[];
    requests: LeaveRow[];
    leaveTakenThisYear: number;
    canUpdate: boolean;
    canCreateLeave: boolean;
    attendance: Timesheet | null;
    team: EmployeeTeam | null;
};

const TABS = [
    { key: 'profile', label: 'Profil' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'leave', label: 'Pengajuan' },
    { key: 'attendance', label: 'Absensi' },
    { key: 'team', label: 'Tim' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

function Field({ label, value }: { label: string; value: unknown }) {
    const display =
        value === null || value === undefined || value === '' ? '—' : value;

    return (
        <div className="grid grid-cols-[9rem_1fr] gap-2 border-t py-2 text-sm">
            <dt className="label-mono pt-0.5">{label}</dt>
            <dd className="font-medium break-words">{display as string}</dd>
        </div>
    );
}

/**
 * The employee profile as a side drawer over the directory. It reads the same data as the full
 * profile through a JSON endpoint and is read-only; changing anything (editing, uploading a
 * document, correcting a scan) happens on the full page, one click away, because those actions
 * redirect and would take the list away from under the drawer.
 *
 * Opening someone from the Tim tab swaps the drawer to that person.
 */
export function EmployeeDrawer({
    employeeId,
    onOpen,
    onClose,
}: {
    employeeId: number | null;
    onOpen: (id: number) => void;
    onClose: () => void;
}) {
    const [loaded, setLoaded] = useState<{
        id: number;
        data: Summary | null;
    } | null>(null);
    const [tab, setTab] = useState<TabKey>('profile');

    useEffect(() => {
        if (employeeId === null) {
            return;
        }

        const controller = new AbortController();

        fetch(summary(employeeId).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data: Summary | null) => setLoaded({ id: employeeId, data }))
            .catch(() => {
                if (!controller.signal.aborted) {
                    setLoaded({ id: employeeId, data: null });
                }
            });

        return () => controller.abort();
    }, [employeeId]);

    const ready = loaded !== null && loaded.id === employeeId;
    const data = ready ? loaded.data : null;

    return (
        <Sheet
            open={employeeId !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent
                side="right"
                className="w-full gap-0 sm:max-w-xl"
                onOpenAutoFocus={(event) => event.preventDefault()}
            >
                <SheetTitle className="sr-only">Profil karyawan</SheetTitle>
                <SheetDescription className="sr-only">
                    Ringkasan profil karyawan yang dipilih dari daftar.
                </SheetDescription>

                {employeeId !== null && !ready && (
                    <p className="p-6 text-sm text-muted-foreground">Memuat…</p>
                )}

                {employeeId !== null && ready && data === null && (
                    <p className="p-6 text-sm text-muted-foreground">
                        Profil tidak dapat dimuat.{' '}
                        <Link href={show(employeeId).url} className="underline">
                            Buka halaman profil
                        </Link>
                    </p>
                )}

                {data && (
                    <DrawerBody
                        data={data}
                        tab={tab}
                        setTab={setTab}
                        onOpen={(id) => {
                            setTab('profile');
                            onOpen(id);
                        }}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

function DrawerBody({
    data,
    tab,
    setTab,
    onOpen,
}: {
    data: Summary;
    tab: TabKey;
    setTab: (tab: TabKey) => void;
    onOpen: (id: number) => void;
}) {
    const { employee } = data;
    const visibleTabs = TABS.filter(
        (item) =>
            (item.key !== 'team' || data.team) &&
            (item.key !== 'attendance' || data.attendance),
    );
    const active = visibleTabs.some((item) => item.key === tab)
        ? tab
        : 'profile';

    return (
        <>
            <div className="label-mono flex items-center justify-between border-b px-4 py-3 pr-14">
                <span>Dosir · dibuka dari daftar</span>
                <span className="hidden sm:inline">Esc untuk menutup</span>
            </div>
            <div className="flex items-start gap-4 border-b p-4 pr-12">
                <EmployeeAvatar
                    name={employee.name}
                    photoUrl={employee.photoUrl}
                    className="size-16"
                />
                <div className="min-w-0">
                    <p className="font-mono text-[11px] font-medium tracking-wider text-primary uppercase">
                        {employee.number}
                        {employee.companyCode
                            ? ` · ${employee.companyCode}`
                            : ''}
                    </p>
                    <h2 className="text-2xl font-extrabold tracking-tight">
                        {employee.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {employee.position ?? '—'}
                        {employee.branch ? ` · ${employee.branch}` : ''}
                        {employee.department ? ` · ${employee.department}` : ''}
                    </p>
                </div>
            </div>

            <nav className="pill-tabs px-2">
                {visibleTabs.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => setTab(item.key)}
                        data-on={active === item.key}
                    >
                        {item.label}
                    </button>
                ))}
            </nav>

            <div className="flex-1 overflow-y-auto p-4">
                {active === 'profile' && <ProfileTab data={data} />}
                {active === 'documents' && <DocumentsTab data={data} />}
                {active === 'leave' && <LeaveTab data={data} />}
                {active === 'attendance' && data.attendance && (
                    <AttendanceTab attendance={data.attendance} />
                )}
                {active === 'team' && data.team && (
                    <EmployeeTeamTab
                        team={data.team}
                        name={employee.name}
                        onOpen={onOpen}
                    />
                )}
            </div>

            <div className="flex flex-wrap gap-2 border-t bg-card p-4">
                {data.canUpdate && (
                    <Button asChild variant="outline">
                        <Link href={edit(employee.id).url}>Ubah data</Link>
                    </Button>
                )}
                {data.canCreateLeave && employee.isActive && (
                    <Button asChild variant="outline">
                        <Link
                            href={createLeave({
                                query: { employee: employee.id },
                            })}
                        >
                            Buat pengajuan
                        </Link>
                    </Button>
                )}
                <Button asChild className="ml-auto">
                    <Link href={show(employee.id).url}>Buka profil penuh</Link>
                </Button>
            </div>
        </>
    );
}

function ProfileTab({ data }: { data: Summary }) {
    const { employee } = data;

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-3 divide-x overflow-hidden rounded-xl border text-sm">
                <div className="p-3">
                    <p className="label-mono">Bergabung</p>
                    <p className="text-lg font-extrabold tracking-tight">
                        {employee.joinedOn ?? '—'}
                    </p>
                </div>
                <div className="p-3">
                    <p className="label-mono">Cuti tahun ini</p>
                    <p className="text-lg font-extrabold tracking-tight">
                        {data.leaveTakenThisYear} hari
                    </p>
                </div>
                <div className="p-3">
                    <p className="label-mono">Status</p>
                    <Badge
                        variant={employee.isActive ? 'default' : 'secondary'}
                    >
                        {employee.status}
                    </Badge>
                </div>
            </div>

            <dl>
                <Field label="Atasan" value={employee.manager} />
                <Field label="Email" value={employee.contact.email} />
                <Field label="Telepon" value={employee.contact.phone} />
                <Field label="Alamat" value={employee.contact.address} />
                <Field label="NIK" value={employee.contact.national_id} />
                <Field
                    label="Kontak darurat"
                    value={[
                        employee.contact.emergency_contact_name,
                        employee.contact.emergency_contact_phone,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                />
                {employee.bank && (
                    <Field
                        label="Bank"
                        value={[
                            employee.bank.bank_name,
                            employee.bank.bank_account_number,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                )}
                {employee.health && (
                    <Field
                        label="Asuransi"
                        value={employee.health.insurance_number}
                    />
                )}
            </dl>
        </div>
    );
}

function DocumentsTab({ data }: { data: Summary }) {
    if (data.documents.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">Belum ada dokumen.</p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border text-sm">
            {data.documents.map((document) => {
                const latest = document.versions[0];

                return (
                    <li
                        key={document.id}
                        className="flex items-center justify-between gap-3 p-3"
                    >
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                {document.title}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {document.categoryLabel}
                                {document.expiresOn
                                    ? ` · berlaku s/d ${document.expiresOn}`
                                    : ''}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {document.expiryStatus && (
                                <Badge variant="destructive">
                                    {document.expiryStatus === 'expired'
                                        ? 'Kedaluwarsa'
                                        : 'Segera berakhir'}
                                </Badge>
                            )}
                            {latest && (
                                <a
                                    href={latest.downloadUrl}
                                    className="underline"
                                >
                                    Unduh
                                </a>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function LeaveTab({ data }: { data: Summary }) {
    const [openId, setOpenId] = useState<number | null>(null);

    if (data.requests.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Belum ada pengajuan.
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border text-sm">
            {data.requests.map((row) => (
                <li key={row.id}>
                    <button
                        type="button"
                        aria-expanded={openId === row.id}
                        onClick={() =>
                            setOpenId(openId === row.id ? null : row.id)
                        }
                        className="flex w-full items-center justify-between gap-3 p-3 text-left hover:bg-muted/40"
                    >
                        <span>
                            <span className="block font-medium">
                                {row.reference} · {row.type}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {row.startOn} – {row.endOn}
                                {row.timeframe
                                    ? ` · ${row.timeframe}`
                                    : ''} ·{' '}
                                {row.days} hari
                            </span>
                        </span>
                        <span className="text-right">
                            {row.stageLabel}
                            <span className="block text-xs text-muted-foreground">
                                {openId === row.id ? 'Tutup' : 'Detail'}
                            </span>
                        </span>
                    </button>
                    {openId === row.id && (
                        <div className="border-t bg-muted/30 p-3">
                            <LeaveRequestDetail row={row} />
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

function AttendanceTab({ attendance }: { attendance: Timesheet }) {
    const recent = attendance.days
        .filter((day) => day.status !== 'off' && day.status !== 'no_data')
        .slice()
        .reverse()
        .slice(0, 14);

    return (
        <div className="flex flex-col gap-3 text-sm">
            <p className="text-muted-foreground">
                {attendance.label}: hadir {attendance.totals.present}, terlambat{' '}
                {attendance.totals.late}, pulang cepat{' '}
                {attendance.totals.early_leave}, tidak hadir{' '}
                {attendance.totals.absent}, cuti {attendance.totals.leave}.
            </p>

            {recent.length === 0 ? (
                <p className="text-muted-foreground">
                    Belum ada data absensi pada bulan ini.
                </p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {recent.map((day) => (
                        <li
                            key={day.date}
                            className="flex items-center justify-between gap-3 p-3"
                        >
                            <span>
                                {day.weekday} {day.date}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {day.in ?? '—'}
                                {day.out ? ` – ${day.out}` : ''}
                                {day.minutes !== null
                                    ? ` · ${formatMinutes(day.minutes)}`
                                    : ''}
                            </span>
                            <span
                                className={`rounded px-2 py-0.5 text-xs ${STATUS_STYLE[day.status]}`}
                            >
                                {STATUS_LABEL[day.status]}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
