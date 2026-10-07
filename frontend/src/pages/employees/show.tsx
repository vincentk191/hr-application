import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { EmployeeDetail } from '@/types/employee';

type LeaveRow = {
    id: number;
    reference: string;
    type: string;
    startOn: string;
    endOn: string;
    days: number;
    stageLabel: string | null;
};

type Props = {
    employee: EmployeeDetail;
    requests: LeaveRow[];
    leaveTakenThisYear: number;
    canUpdate: boolean;
};

const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'attendance', label: 'Absensi' },
    { key: 'requests', label: 'Pengajuan' },
    { key: 'documents', label: 'Dokumen' },
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
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{display as string}</dd>
        </div>
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
        <section className="rounded-xl border p-4">
            <h3 className="mb-2 font-medium">{title}</h3>
            <dl className="divide-y">{children}</dl>
        </section>
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
    requests,
    leaveTakenThisYear,
    canUpdate,
}: Props) {
    const [tab, setTab] = useState<TabKey>('overview');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/employees' },
        { title: employee.name, href: `/employees/${employee.id}` },
    ];

    setLayoutProps({ breadcrumbs });

    return (
        <>
            <Head title={employee.name} />

            <div className="flex flex-col gap-4 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-4">
                        <EmployeeAvatar
                            name={employee.name}
                            photoUrl={employee.photoUrl}
                            className="size-20"
                        />
                        <div>
                            <h1 className="text-xl font-semibold">
                                {employee.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
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
                </header>

                {/* Profile header stats. "Leave taken", never a remaining balance — no
                    entitlement is modelled (context.md §10). */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border p-4">
                        <p className="text-xs text-muted-foreground">
                            Cuti terpakai tahun ini
                        </p>
                        <p className="text-2xl font-semibold">
                            {leaveTakenThisYear} hari
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-xs text-muted-foreground">Status</p>
                        <Badge
                            variant={
                                employee.isActive ? 'default' : 'secondary'
                            }
                            className="mt-1"
                        >
                            {employee.status}
                        </Badge>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-xs text-muted-foreground">Atasan</p>
                        <p className="mt-1 font-medium">
                            {employee.manager ?? '—'}
                        </p>
                    </div>
                </div>

                <nav className="flex gap-1 border-b">
                    {TABS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                                tab === item.key
                                    ? 'border-foreground font-medium'
                                    : 'border-transparent text-muted-foreground'
                            }`}
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
                            <Field
                                label="Akun"
                                value={employee.hasLogin ? 'Ada' : 'Data saja'}
                            />
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

                        <Card title="Payroll">
                            <Field
                                label="Pay group"
                                value={employee.payroll.pay_group}
                            />
                            <Field
                                label="Gaji pokok"
                                value={rupiah(employee.payroll.base_salary_idr)}
                            />
                            <Field
                                label="Tunjangan"
                                value={rupiah(employee.payroll.allowances_idr)}
                            />
                            <Field
                                label="Tarif lembur"
                                value={rupiah(
                                    employee.payroll.overtime_rate_idr,
                                )}
                            />
                        </Card>

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
                                value={employee.health.last_medical_check_on}
                            />
                            <Field
                                label="Kondisi dilaporkan"
                                value={employee.health.declared_conditions}
                            />
                        </Card>
                    </div>
                )}

                {tab === 'requests' && (
                    <div className="overflow-x-auto rounded-xl border">
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
                                </tr>
                            </thead>
                            <tbody>
                                {requests.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-6 text-center text-muted-foreground"
                                        >
                                            Belum ada pengajuan.
                                        </td>
                                    </tr>
                                )}
                                {requests.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="p-3 font-medium">
                                            {row.reference}
                                        </td>
                                        <td className="p-3">{row.type}</td>
                                        <td className="p-3 whitespace-nowrap">
                                            {row.startOn} – {row.endOn}
                                        </td>
                                        <td className="p-3">{row.days}</td>
                                        <td className="p-3">
                                            {row.stageLabel}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {tab === 'attendance' && (
                    <p className="rounded-xl border p-6 text-sm text-muted-foreground">
                        Riwayat absensi menyusul bersama modul Absensi, yang
                        membutuhkan data dari mesin scan (Fase 2).
                    </p>
                )}

                {tab === 'documents' && (
                    <p className="rounded-xl border p-6 text-sm text-muted-foreground">
                        Dokumen karyawan menyusul: membutuhkan tabel dokumen dan
                        kontrol akses per dokumen (EMP-DOC).
                    </p>
                )}
            </div>
        </>
    );
}
