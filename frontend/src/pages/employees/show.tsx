import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { ArrowLeft, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import { EmployeeAttendance } from '@/components/employee-attendance';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { EmployeeDocuments } from '@/components/employee-documents';
import { EmployeeTeamTab } from '@/components/employee-team';
import { LeaveRequestDetail } from '@/components/leave-request-detail';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { edit, index as employeesIndex, reactivate } from '@/routes/employees';
import { activate, reject } from '@/routes/employees/activation';
import { create as requestChange } from '@/routes/employees/changes';
import type { BreadcrumbItem } from '@/types';
import type { Timesheet, YearTimesheet } from '@/types/attendance';
import type {
    EmployeeDetail,
    EmployeeDocumentRow,
    EmployeeTeam,
    LeaveRow,
    ValueOption,
} from '@/types/employee';

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
    leaveTakenThisYear: number;
    canUpdate: boolean;
    /** Null for someone who may not see the directory. */
    team: EmployeeTeam | null;
};

const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'attendance', label: 'Absensi' },
    { key: 'requests', label: 'Pengajuan' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'team', label: 'Tim' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

/** Rupiah, formatted from an integer — the only representation money has here. */
function rupiah(value: number | null): string {
    return value === null
        ? '—'
        : new Intl.NumberFormat('id-ID', {
              style: 'currency',
              currency: 'IDR',
              maximumFractionDigits: 0,
          }).format(value);
}

function Field({ label, value }: { label: string; value: unknown }) {
    const display =
        value === null || value === undefined || value === '' ? '—' : value;

    return (
        <div className="grid grid-cols-2 gap-2 py-1.5 text-sm">
            <dt className="label-mono pt-0.5">{label}</dt>
            <dd className="font-medium">{display as string}</dd>
        </div>
    );
}

/** Shown in place of a sensitive card the viewer may not read (EMP-DET-006). */
function RestrictedCard({ title }: { title: string }) {
    return (
        <section className="surface p-4">
            <h3 className="mb-2 text-base font-extrabold tracking-tight">
                {title}
            </h3>
            <p className="text-sm text-muted-foreground">
                Data ini dibatasi dan tidak dapat Anda lihat.
            </p>
        </section>
    );
}

function Card({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="surface p-4">
            <h3 className="mb-2 text-base font-extrabold tracking-tight">
                {title}
            </h3>
            <dl className="divide-y">{children}</dl>
        </section>
    );
}

/**
 * One leave request on the profile. Opening it shows the requester's note and each reviewer's
 * decision with the note they left, which a rejected request needs the requester to be able to read.
 */
function RequestRow({ row }: { row: LeaveRow }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <tr
                className="cursor-pointer border-t hover:bg-muted/40"
                onClick={() => setOpen((value) => !value)}
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
                    <Button
                        size="sm"
                        variant="ghost"
                        type="button"
                        aria-expanded={open}
                        onClick={(event) => {
                            event.stopPropagation();
                            setOpen((value) => !value);
                        }}
                    >
                        {open ? 'Tutup' : 'Detail'}
                    </Button>
                </td>
            </tr>
            {open && (
                <tr className="bg-muted/30">
                    <td colSpan={6} className="px-4 py-3">
                        <LeaveRequestDetail row={row} />
                    </td>
                </tr>
            )}
        </>
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
}: Props) {
    const { features } = usePage().props;

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

    const confirmReactivate = () => {
        if (
            window.confirm(
                `Aktifkan kembali ${employee.name}? Profil bisa diubah lagi dan akun masuknya diaktifkan kembali. Riwayat offboarding tetap tersimpan.`,
            )
        ) {
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
                                            Ubah data
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {canReactivate && (
                                    <DropdownMenuItem
                                        data-test="reactivate-employee"
                                        onSelect={confirmReactivate}
                                    >
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
                        <Button asChild variant="outline">
                            <Link href={edit(employee.id).url}>Ubah data</Link>
                        </Button>
                    )}
                    {canReactivate && (
                        <Button
                            variant="outline"
                            data-test="reactivate-employee"
                            onClick={confirmReactivate}
                        >
                            Aktifkan kembali
                        </Button>
                    )}
                    {canRequestChange && (
                        <Button asChild variant="outline">
                            <Link href={requestChange(employee.id).url}>
                                Ajukan perubahan data
                            </Link>
                        </Button>
                    )}
                </header>

                {accountPending && canActivateAccount && (
                    <section
                        className="surface flex flex-wrap items-center justify-between gap-3 border-amber-500/50 p-4"
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
                        <Badge
                            variant={
                                employee.isActive ? 'default' : 'secondary'
                            }
                            className="mt-1"
                        >
                            {employee.status}
                        </Badge>
                    </div>
                    <div className="surface p-4">
                        <p className="label-mono">Atasan</p>
                        <p className="mt-1 font-medium">
                            {employee.manager ?? '—'}
                        </p>
                    </div>
                </div>

                <nav className="pill-tabs flex-nowrap overflow-x-auto md:flex-wrap md:overflow-visible">
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
                            className="shrink-0"
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>

                {tab === 'overview' && (
                    <div className="grid gap-3 lg:grid-cols-2">
                        <Card title="Kepegawaian">
                            <Field
                                label="Departemen"
                                value={employee.department}
                            />
                            <Field label="Jabatan" value={employee.position} />
                            <Field label="Atasan" value={employee.manager} />
                            <Field
                                label="Bergabung"
                                value={employee.joinedOn}
                            />
                            <Field label="Keluar" value={employee.exitedOn} />
                            <Field label="Akun" value={employee.accountLabel} />
                        </Card>

                        <Card title="Kontak & darurat">
                            <Field
                                label="Email"
                                value={employee.contact.email}
                            />
                            <Field
                                label="Telepon"
                                value={employee.contact.phone}
                            />
                            <Field
                                label="Alamat"
                                value={employee.contact.address}
                            />
                            <Field
                                label="NIK"
                                value={employee.contact.national_id}
                            />
                            <Field
                                label="Kontak darurat"
                                value={employee.contact.emergency_contact_name}
                            />
                            <Field
                                label="Telepon darurat"
                                value={employee.contact.emergency_contact_phone}
                            />
                            <Field
                                label="Hubungan"
                                value={
                                    employee.contact.emergency_contact_relation
                                }
                            />
                        </Card>

                        {employee.bank ? (
                            <Card title="Bank & pajak">
                                <Field
                                    label="Bank"
                                    value={employee.bank.bank_name}
                                />
                                <Field
                                    label="No. rekening"
                                    value={employee.bank.bank_account_number}
                                />
                                <Field
                                    label="Atas nama"
                                    value={employee.bank.bank_account_holder}
                                />
                                <Field
                                    label="NPWP"
                                    value={employee.bank.tax_number}
                                />
                                <Field
                                    label="Metode bayar"
                                    value={employee.bank.payment_method}
                                />
                            </Card>
                        ) : (
                            <RestrictedCard title="Bank & pajak" />
                        )}

                        {employee.payroll ? (
                            <Card title="Payroll">
                                <Field
                                    label="Pay group"
                                    value={employee.payroll.pay_group}
                                />
                                <Field
                                    label="Gaji pokok"
                                    value={rupiah(
                                        employee.payroll.base_salary_idr,
                                    )}
                                />
                                <Field
                                    label="Tunjangan"
                                    value={rupiah(
                                        employee.payroll.allowances_idr,
                                    )}
                                />
                                <Field
                                    label="Tarif lembur"
                                    value={rupiah(
                                        employee.payroll.overtime_rate_idr,
                                    )}
                                />
                            </Card>
                        ) : (
                            <RestrictedCard title="Payroll" />
                        )}

                        {employee.health ? (
                            <Card title="Kesehatan & asuransi">
                                <Field
                                    label="No. asuransi"
                                    value={employee.health.insurance_number}
                                />
                                <Field
                                    label="Tingkat"
                                    value={employee.health.insurance_tier}
                                />
                                <Field
                                    label="Gol. darah"
                                    value={employee.health.blood_type}
                                />
                                <Field
                                    label="Tinggi (cm)"
                                    value={employee.health.height_cm}
                                />
                                <Field
                                    label="Berat (kg)"
                                    value={employee.health.weight_kg}
                                />
                                <Field
                                    label="Medical check terakhir"
                                    value={
                                        employee.health.last_medical_check_on
                                    }
                                />
                                <Field
                                    label="Kondisi dilaporkan"
                                    value={employee.health.declared_conditions}
                                />
                            </Card>
                        ) : (
                            <RestrictedCard title="Kesehatan & asuransi" />
                        )}
                    </div>
                )}

                {tab === 'requests' && (
                    <div className="surface overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">
                                        Pengajuan
                                    </th>
                                    <th className="p-3 font-medium">Jenis</th>
                                    <th className="p-3 font-medium">Tanggal</th>
                                    <th className="p-3 font-medium">Hari</th>
                                    <th className="p-3 font-medium">Status</th>
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
                                    <RequestRow key={row.id} row={row} />
                                ))}
                            </tbody>
                        </table>
                    </div>
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
        </>
    );
}
