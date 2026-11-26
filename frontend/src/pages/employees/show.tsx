import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronRight,
    Lock,
    MoreVertical,
    Pencil,
    Power,
    RefreshCw,
    RotateCcw,
    Send,
    X,
} from 'lucide-react';
import { IconButton } from '@/components/icon-button';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { EmployeeAttendance } from '@/components/employee-attendance';
import { InfoHint } from '@/components/info-hint';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { EmployeeDocuments } from '@/components/employee-documents';
import { EmployeeTeamTab } from '@/components/employee-team';
import { LeaveDecisionDialog } from '@/components/leave-decision-dialog';
import { revealTarget } from '@/components/masked-value';
import { Card, Field, CardGroup } from '@/components/profile-parts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatRupiah } from '@/lib/format';
import {
    edit,
    index as employeesIndex,
    reactivate,
    show as showEmployee,
} from '@/routes/employees';
import { activate, reject } from '@/routes/employees/activation';
import { create as requestChange } from '@/routes/employees/changes';
import type { BreadcrumbItem } from '@/types';
import type { Timesheet, YearTimesheet } from '@/types/attendance';
import type { LeaveRequestRow } from '@/types/leave';
import type {
    EmployeeDetail,
    EmployeeDocumentRow,
    EmployeeTeam,
    LeaveRow,
    ValueOption,
} from '@/types/employee';
import { StatusPill } from '@/components/status-pill';
import { decisionTone } from '@/lib/status';

type Props = {
    employee: EmployeeDetail;
    documents: EmployeeDocumentRow[];
    documentCategories: ValueOption[];
    canManageDocuments: boolean;
    canRestrictDocuments: boolean;
    /** This is the viewer's own record: they change it by asking HR. */
    canRequestChange: boolean;
    /** The employee registered an account that HR has not yet activated. */
    accountPending: boolean;
    canActivateAccount: boolean;
    /** The profile is closed and this viewer may bring it back. */
    canReactivate: boolean;
    /** Absent until the tab asks for it; null when this viewer may not see it. */
    attendance?: Timesheet | YearTimesheet | null;
    canCorrectAttendance: boolean;
    requests: LeaveRow[];
    /** The request asked for with `?request=ID`, to open as the Pengajuan page's side panel. */
    openedRequest: LeaveRequestRow | null;
    leaveTakenThisYear: number;
    canUpdate: boolean;
    /** Null for someone who may not see the directory. */
    team: EmployeeTeam | null;
};

const TABS = [
    { key: 'overview', label: 'Ringkasan' },
    { key: 'attendance', label: 'Absensi' },
    { key: 'requests', label: 'Pengajuan' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'team', label: 'Tim' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

/** Rupiah, formatted from an integer — the only representation money has here. */
function rupiah(value: number | string | null): string {
    return typeof value === 'string' ? value : formatRupiah(value);
}

/** Shown in place of a sensitive card the viewer may not read (EMP-DET-006). */
function RestrictedCard({ title }: { title: string }) {
    return (
        <section className="surface p-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-base font-extrabold tracking-tight">
                    {title}
                </h3>
                <span className="flex items-center gap-1.5">
                    <Badge variant="outline" className="gap-1 font-normal">
                        <Lock aria-hidden />
                        Terbatas
                    </Badge>
                    <InfoHint label="Data dibatasi">
                        Data ini dibatasi dan tidak dapat Anda lihat.
                    </InfoHint>
                </span>
            </div>
            <div
                role="img"
                aria-label="Data dibatasi"
                className="hatch mt-3 h-14 rounded-xl border border-dashed text-muted-foreground"
            />
        </section>
    );
}

/**
 * One leave request on the profile. Choosing it opens the same side panel as the Pengajuan page (the requester's note, the
 * approval chain and what each reviewer decided), so a rejected request can be read in full.
 */
function RequestRow({
    row,
    onOpen,
}: {
    row: LeaveRow;
    onOpen: (id: number) => void;
}) {
    return (
        <tr
            className="cursor-pointer border-t hover:bg-muted/40"
            onClick={() => onOpen(row.id)}
        >
            <td className="p-3 font-medium">{row.reference}</td>
            <td className="p-3">{row.type}</td>
            <td className="p-3 whitespace-nowrap">
                {row.startOn} – {row.endOn}
                {row.timeframe && (
                    <span className="block text-xs text-muted-foreground">
                        {row.timeframe}
                    </span>
                )}
            </td>
            <td className="p-3">{row.days}</td>
            <td className="p-3">{row.stageLabel}</td>
            <td className="p-3 text-right">
                <IconButton
                    icon={ChevronRight}
                    label="Detail"
                    onClick={(event) => {
                        event.stopPropagation();
                        onOpen(row.id);
                    }}
                    className="size-8"
                />
            </td>
        </tr>
    );
}

/**
 * One leave request on the profile as a card, for a phone, where the table's six columns do not fit: what it is and its
 * status on top, the dates and days beneath. Tapping it opens the same side panel as the table's rows.
 */
function RequestCard({
    row,
    onOpen,
}: {
    row: LeaveRow;
    onOpen: (id: number) => void;
}) {
    return (
        <article className="surface overflow-hidden" data-test="request-card">
            <button
                type="button"
                onClick={() => onOpen(row.id)}
                className="flex w-full flex-col gap-2 p-4 text-left"
            >
                <span className="flex items-start justify-between gap-3">
                    <span className="min-w-0">
                        <span className="block font-semibold">{row.type}</span>
                        <span className="label-mono">{row.reference}</span>
                    </span>
                    <StatusPill tone={decisionTone(row.stage)}>
                        {row.stageLabel}
                    </StatusPill>
                </span>
                <span className="text-sm">
                    {row.startOn} – {row.endOn} · {row.days} hari
                </span>
                {row.timeframe && (
                    <span className="text-xs text-muted-foreground">
                        {row.timeframe}
                    </span>
                )}
            </button>
        </article>
    );
}

/**
 * The employee profile (EMP-PRF) and its tabs.
 *
 * Overview (EMP-DET) and Pengajuan (EMP-REQ) are real. Absensi and Dokumen are deliberately
 * empty: attendance arrives in Phase 2 and documents need their own table and per-document
 * ACL, so they say so rather than showing a convincing blank.
 */
export default function EmployeeShow({
    employee,
    documents,
    documentCategories,
    canManageDocuments,
    canRestrictDocuments,
    attendance,
    canCorrectAttendance,
    requests,
    leaveTakenThisYear,
    canUpdate,
    canRequestChange,
    accountPending,
    canActivateAccount,
    canReactivate,
    team,
    openedRequest,
}: Props) {
    const { features } = usePage().props;

    // A request opens as the Pengajuan page's side panel on top of the profile, so the person stays on this tab. It is read
    // from the server (`?request=ID`, a partial reload of that one prop, and the address can be shared); closing hides it at
    // once and then clears the address.
    const [dismissed, setDismissed] = useState<number | null>(null);
    const request = openedRequest?.id === dismissed ? null : openedRequest;

    const showRequest = (id: number | null) => {
        const params = new URLSearchParams(window.location.search);

        if (id === null) {
            params.delete('request');
        } else {
            params.set('request', String(id));
            setDismissed(null);
        }

        router.get(showEmployee(employee.id).url, Object.fromEntries(params), {
            only: ['openedRequest'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // ?tab=attendance opens straight onto that tab, so other screens can link to it.
    const [tab, setTab] = useState<TabKey>(() => {
        const asked =
            typeof window === 'undefined'
                ? null
                : new URLSearchParams(window.location.search).get('tab');

        return TABS.some(
            (item) =>
                item.key === asked &&
                (item.key !== 'attendance' || features.attendance),
        )
            ? (asked as TabKey)
            : 'overview';
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/employees' },
        { title: employee.name, href: `/employees/${employee.id}` },
    ];

    setLayoutProps({ breadcrumbs, compactTitleOnMobile: true });

    const { confirm, dialog } = useConfirm();

    const confirmReactivate = async () => {
        const confirmed = await confirm({
            title: `Aktifkan kembali ${employee.name}?`,
            description:
                'Profil bisa diubah lagi dan akun masuknya diaktifkan kembali. Riwayat offboarding tetap tersimpan.',
            context: employee.company ?? undefined,
            confirmLabel: 'Aktifkan kembali',
            icon: RotateCcw,
        });

        if (confirmed) {
            router.post(
                reactivate(employee.id).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    const hasHeaderActions = canUpdate || canReactivate || canRequestChange;

    return (
        <>
            <Head title={employee.name} />
            {dialog}

            <div className="flex flex-col gap-4 p-4">
                {/* Phone: a compact back + avatar + name header, in place of the page's usual title bar
                    (PageTitleBar is hidden here, see compactTitleOnMobile above). The same actions as the
                    desktop header, below, fold into the overflow menu so the row stays one line. */}
                <header className="flex items-center gap-3 md:hidden">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="shrink-0"
                    >
                        <Link
                            href={employeesIndex()}
                            aria-label="Kembali ke direktori"
                        >
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <EmployeeAvatar
                        name={employee.name}
                        photoUrl={employee.photoUrl}
                        className="size-9 shrink-0"
                    />
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold">
                            {employee.name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {[employee.position, employee.department]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                    {hasHeaderActions && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="shrink-0"
                                    aria-label="Menu profil"
                                >
                                    <MoreVertical className="size-5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {canUpdate && (
                                    <DropdownMenuItem asChild>
                                        <Link href={edit(employee.id).url}>
                                            <RefreshCw
                                                className="size-4 shrink-0"
                                                aria-hidden
                                            />
                                            Ubah data
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {canReactivate && (
                                    <DropdownMenuItem
                                        data-test="reactivate-employee"
                                        onSelect={confirmReactivate}
                                    >
                                        <RotateCcw
                                            className="size-4 shrink-0"
                                            aria-hidden
                                        />
                                        Aktifkan kembali
                                    </DropdownMenuItem>
                                )}
                                {canRequestChange && (
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={
                                                requestChange(employee.id).url
                                            }
                                        >
                                            <Send
                                                className="size-4 shrink-0"
                                                aria-hidden
                                            />
                                            Ajukan perubahan data
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </header>

                <header className="hidden flex-wrap items-start justify-between gap-3 md:flex">
                    <div className="flex items-center gap-4">
                        <EmployeeAvatar
                            name={employee.name}
                            photoUrl={employee.photoUrl}
                            className="size-20"
                        />
                        <div>
                            <p className="label-mono text-sm">
                                {employee.number}
                                {employee.position
                                    ? ` · ${employee.position}`
                                    : ''}
                                {employee.branch ? ` · ${employee.branch}` : ''}
                                {employee.department
                                    ? ` · ${employee.department}`
                                    : ''}
                                {employee.companyCode
                                    ? ` · ${employee.companyCode}`
                                    : ''}
                            </p>
                        </div>
                    </div>
                    {canUpdate && (
                        <IconButton
                            icon={Pencil}
                            variant="outline"
                            label="Ubah data"
                            href={edit(employee.id).url}
                        />
                    )}
                    {canReactivate && (
                        <Button
                            variant="outline"
                            data-test="reactivate-employee"
                            onClick={confirmReactivate}
                        >
                            <RotateCcw className="size-4" aria-hidden />
                            Aktifkan kembali
                        </Button>
                    )}
                    {canRequestChange && (
                        <Button asChild variant="outline">
                            <Link href={requestChange(employee.id).url}>
                                <Send className="size-4 shrink-0" aria-hidden />
                                Perubahan data
                            </Link>
                        </Button>
                    )}
                </header>

                {accountPending && canActivateAccount && (
                    <section
                        className="surface flex flex-wrap items-center justify-between gap-3 border-pending/50 p-4"
                        data-test="pending-account"
                    >
                        <p className="max-w-2xl text-sm">
                            {employee.name} mendaftarkan akun dengan NIK dan ID
                            karyawan ini, dan belum bisa masuk. Aktifkan jika
                            benar orangnya; tolak jika bukan, dan profil ini
                            bisa didaftarkan ulang.
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.delete(reject(employee.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <X className="size-4" aria-hidden />
                                Tolak
                            </Button>
                            <Button
                                data-test="activate-account"
                                onClick={() =>
                                    router.post(
                                        activate(employee.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Power className="size-4" aria-hidden />
                                Aktifkan akun
                            </Button>
                        </div>
                    </section>
                )}

                {/* Profile header stats. "Leave taken", never a remaining balance — no
                    entitlement is modelled (context.md §10). */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="surface p-4">
                        <p className="label-mono">Cuti terpakai tahun ini</p>
                        <p className="text-3xl font-extrabold tracking-tight">
                            {leaveTakenThisYear} hari
                        </p>
                    </div>
                    <div className="surface p-4">
                        <p className="label-mono">Status</p>
                        <StatusPill
                            tone={employee.isActive ? 'success' : 'neutral'}
                            className="mt-1"
                        >
                            {employee.status}
                        </StatusPill>
                    </div>
                    <div className="surface p-4">
                        <p className="label-mono">Atasan</p>
                        <p className="mt-1 font-medium">
                            {employee.manager ?? '—'}
                        </p>
                    </div>
                </div>

                {/* Phone: every tab shares the row equally, in smaller, tighter type, so none has to slide out of
                    view. Tablet and up: the usual pills. */}
                <nav className="pill-tabs flex-nowrap gap-0.5 md:flex-wrap md:gap-1">
                    {TABS.filter(
                        (item) =>
                            (item.key !== 'team' || team) &&
                            (item.key !== 'attendance' || features.attendance),
                    ).map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            data-on={tab === item.key}
                            className="min-w-0 flex-1 truncate px-1 text-xs tracking-normal md:flex-none md:px-4 md:text-xs md:tracking-wide"
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>

                {tab === 'overview' && (
                    <div className="grid gap-3 lg:grid-cols-2">
                        <Card title="Kepegawaian">
                            <Field label="Cabang" value={employee.branch} />
                            <Field
                                label="Departemen"
                                value={employee.department}
                            />
                            <Field label="Posisi" value={employee.position} />
                            <Field
                                label="Jabatan"
                                value={employee.personal.job_title}
                            />
                            <Field label="Atasan" value={employee.manager} />
                            <Field
                                label="Bergabung"
                                value={employee.joinedOn}
                            />
                            <Field label="Keluar" value={employee.exitedOn} />
                            <Field label="Akun" value={employee.accountLabel} />
                        </Card>

                        <Card
                            title="Data Pribadi"
                            grouped
                            className="lg:col-span-2"
                        >
                            <div className="grid gap-x-8 gap-y-4 lg:grid-cols-2">
                                <CardGroup>
                                    <Field
                                        label="Jenis kelamin"
                                        value={employee.personal.genderLabel}
                                    />
                                    <Field
                                        label="Tempat lahir"
                                        value={employee.personal.birth_place}
                                    />
                                    <Field
                                        label="Tanggal lahir"
                                        value={employee.personal.birth_date}
                                    />
                                    <Field
                                        label="Agama"
                                        value={employee.personal.religionLabel}
                                    />
                                    <Field
                                        label="Status perkawinan"
                                        value={
                                            employee.personal.maritalStatusLabel
                                        }
                                    />
                                    <Field
                                        label="Pendidikan terakhir"
                                        value={
                                            employee.personal.education_level
                                        }
                                    />
                                </CardGroup>
                                <CardGroup title="Kontak">
                                    <Field
                                        label="Email"
                                        value={employee.contact.email}
                                    />
                                    <Field
                                        label="Telepon"
                                        value={employee.contact.phone}
                                        reveal={revealTarget(
                                            employee,
                                            'phone',
                                            'telepon',
                                        )}
                                    />
                                    <Field
                                        label="NIK"
                                        value={employee.contact.national_id}
                                        reveal={revealTarget(
                                            employee,
                                            'national_id',
                                            'NIK',
                                        )}
                                    />
                                </CardGroup>
                                <CardGroup title="Alamat Tinggal">
                                    <Field
                                        label="Alamat"
                                        value={employee.contact.address}
                                    />
                                    <Field
                                        label="Kelurahan"
                                        value={
                                            employee.residence.residence_village
                                        }
                                    />
                                    <Field
                                        label="Kecamatan"
                                        value={
                                            employee.residence
                                                .residence_district
                                        }
                                    />
                                    <Field
                                        label="Kabupaten / kota"
                                        value={
                                            employee.residence.residence_city
                                        }
                                    />
                                    <Field
                                        label="Provinsi"
                                        value={
                                            employee.residence
                                                .residence_province
                                        }
                                    />
                                    <Field
                                        label="Kode pos"
                                        value={
                                            employee.residence
                                                .residence_postal_code
                                        }
                                    />
                                </CardGroup>
                                <CardGroup title="Alamat KTP">
                                    <Field
                                        label="Alamat"
                                        value={employee.idAddress.id_address}
                                    />
                                    <Field
                                        label="Blok"
                                        value={employee.idAddress.id_block}
                                    />
                                    <Field
                                        label="No."
                                        value={
                                            employee.idAddress.id_house_number
                                        }
                                    />
                                    <Field
                                        label="RT / RW"
                                        value={
                                            employee.idAddress.id_rt ||
                                            employee.idAddress.id_rw
                                                ? `${employee.idAddress.id_rt ?? '—'} / ${employee.idAddress.id_rw ?? '—'}`
                                                : null
                                        }
                                    />
                                    <Field
                                        label="Kelurahan"
                                        value={employee.idAddress.id_village}
                                    />
                                    <Field
                                        label="Kecamatan"
                                        value={employee.idAddress.id_district}
                                    />
                                    <Field
                                        label="Kabupaten / kota"
                                        value={employee.idAddress.id_city}
                                    />
                                    <Field
                                        label="Provinsi"
                                        value={employee.idAddress.id_province}
                                    />
                                    <Field
                                        label="Kode pos"
                                        value={
                                            employee.idAddress.id_postal_code
                                        }
                                    />
                                </CardGroup>
                                {employee.identity ? (
                                    <CardGroup title="Identitas Keluarga">
                                        <Field
                                            label="No. KK"
                                            value={
                                                employee.identity
                                                    .family_card_number
                                            }
                                            reveal={revealTarget(
                                                employee,
                                                'family_card_number',
                                                'No. KK',
                                            )}
                                        />
                                        <Field
                                            label="Nama ibu kandung"
                                            value={
                                                employee.identity.mother_name
                                            }
                                        />
                                    </CardGroup>
                                ) : (
                                    <CardGroup title="Identitas Keluarga">
                                        <div className="flex items-center gap-2 py-1.5 text-sm text-muted-foreground">
                                            <Lock
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Data ini dibatasi dan tidak dapat
                                            Anda lihat.
                                        </div>
                                    </CardGroup>
                                )}
                            </div>
                        </Card>

                        {employee.bank ? (
                            <Card title="Bank & Pajak">
                                <Field
                                    label="Bank"
                                    value={employee.bank.bank_name}
                                />
                                <Field
                                    label="No. rekening"
                                    value={employee.bank.bank_account_number}
                                    reveal={revealTarget(
                                        employee,
                                        'bank_account_number',
                                        'nomor rekening',
                                    )}
                                />
                                <Field
                                    label="Atas nama"
                                    value={employee.bank.bank_account_holder}
                                />
                                <Field
                                    label="Cabang bank"
                                    value={employee.bank.bank_branch}
                                />
                                <Field
                                    label="NPWP"
                                    value={employee.bank.tax_number}
                                    reveal={revealTarget(
                                        employee,
                                        'tax_number',
                                        'NPWP',
                                    )}
                                />
                                <Field
                                    label="Status PTKP"
                                    value={employee.bank.ptkp_status}
                                />
                                <Field
                                    label="Metode bayar"
                                    value={employee.bank.payment_method}
                                />
                            </Card>
                        ) : (
                            <RestrictedCard title="Bank & Pajak" />
                        )}

                        {employee.coverage ? (
                            <Card title="Kesehatan & Asuransi">
                                <Field
                                    label="BPJS Ketenagakerjaan"
                                    value={
                                        employee.coverage.bpjs_employment_number
                                    }
                                    reveal={revealTarget(
                                        employee,
                                        'bpjs_employment_number',
                                        'BPJS Ketenagakerjaan',
                                    )}
                                />
                                <Field
                                    label="BPJS Kesehatan"
                                    value={employee.coverage.bpjs_health_number}
                                    reveal={revealTarget(
                                        employee,
                                        'bpjs_health_number',
                                        'BPJS Kesehatan',
                                    )}
                                />
                            </Card>
                        ) : (
                            <RestrictedCard title="Kesehatan & Asuransi" />
                        )}

                        {employee.payroll ? (
                            <Card title="Penggajian">
                                <Field
                                    label="Kelompok gaji"
                                    value={employee.payroll.pay_group}
                                />
                                <Field
                                    label="Gaji pokok"
                                    value={rupiah(
                                        employee.payroll.base_salary_idr,
                                    )}
                                    reveal={revealTarget(
                                        employee,
                                        'base_salary_idr',
                                        'gaji pokok',
                                    )}
                                    format={(raw) => rupiah(Number(raw))}
                                />
                                <Field
                                    label="Tunjangan"
                                    value={rupiah(
                                        employee.payroll.allowances_idr,
                                    )}
                                    reveal={revealTarget(
                                        employee,
                                        'allowances_idr',
                                        'tunjangan',
                                    )}
                                    format={(raw) => rupiah(Number(raw))}
                                />
                                <Field
                                    label="Tarif lembur"
                                    value={rupiah(
                                        employee.payroll.overtime_rate_idr,
                                    )}
                                    reveal={revealTarget(
                                        employee,
                                        'overtime_rate_idr',
                                        'tarif lembur',
                                    )}
                                    format={(raw) => rupiah(Number(raw))}
                                />
                            </Card>
                        ) : (
                            <RestrictedCard title="Penggajian" />
                        )}

                        <Card title="Darurat">
                            <Field
                                label="Kontak darurat"
                                value={employee.contact.emergency_contact_name}
                            />
                            <Field
                                label="Telepon darurat"
                                value={employee.contact.emergency_contact_phone}
                                reveal={revealTarget(
                                    employee,
                                    'emergency_contact_phone',
                                    'telepon kontak darurat',
                                )}
                            />
                            <Field
                                label="Hubungan"
                                value={
                                    employee.contact.emergency_contact_relation
                                }
                            />
                        </Card>
                    </div>
                )}

                {tab === 'requests' && (
                    <>
                        {/* Phone: one card per request. */}
                        <div
                            className="flex flex-col gap-3 md:hidden"
                            data-test="request-cards"
                        >
                            {requests.length === 0 && (
                                <p className="surface p-6 text-center text-sm text-muted-foreground">
                                    Belum ada pengajuan.
                                </p>
                            )}
                            {requests.map((row) => (
                                <RequestCard
                                    key={row.id}
                                    row={row}
                                    onOpen={showRequest}
                                />
                            ))}
                        </div>

                        {/* Tablet and up: the table. */}
                        <div className="surface hidden overflow-x-auto md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Pengajuan
                                        </th>
                                        <th className="p-3 font-medium">
                                            Jenis
                                        </th>
                                        <th className="p-3 font-medium">
                                            Tanggal
                                        </th>
                                        <th className="p-3 font-medium">
                                            Hari
                                        </th>
                                        <th className="p-3 font-medium">
                                            Status
                                        </th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="p-6 text-center text-muted-foreground"
                                            >
                                                Belum ada pengajuan.
                                            </td>
                                        </tr>
                                    )}
                                    {requests.map((row) => (
                                        <RequestRow
                                            key={row.id}
                                            row={row}
                                            onOpen={showRequest}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {tab === 'attendance' && (
                    <EmployeeAttendance
                        employeeId={employee.id}
                        attendance={attendance}
                        canCorrect={canCorrectAttendance}
                    />
                )}

                {tab === 'team' && team && (
                    <EmployeeTeamTab team={team} name={employee.name} />
                )}

                {tab === 'documents' && (
                    <EmployeeDocuments
                        employeeId={employee.id}
                        documents={documents}
                        categories={documentCategories}
                        canManage={canManageDocuments}
                        canRestrict={canRestrictDocuments}
                    />
                )}
            </div>

            <LeaveDecisionDialog
                request={request}
                onClose={() => {
                    setDismissed(openedRequest?.id ?? null);
                    showRequest(null);
                }}
            />
        </>
    );
}
