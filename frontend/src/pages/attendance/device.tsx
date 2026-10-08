import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { device } from '@/routes/attendance';
import { syncNow } from '@/routes/attendance/agents';
import { destroy, store as storeMap } from '@/routes/attendance/enrol-maps';
import { store as storeImport } from '@/routes/attendance/imports';
import type { BreadcrumbItem } from '@/types';
import type {
    AttendanceImportRow,
    CaptureStatus,
    EmployeeOption,
    EnrolMapRow,
    SyncAgent,
    WaitingId,
} from '@/types/attendance';

type Props = {
    status: CaptureStatus;
    agents: SyncAgent[];
    timezone: string;
    lastDeviceSn: string | null;
    knownDevices: string[];
    imports: AttendanceImportRow[];
    waiting: WaitingId[];
    maps: EnrolMapRow[];
    employees: EmployeeOption[];
};

const selectClasses =
    'border-input bg-background h-9 rounded-md border px-3 text-sm';

/** Date and time as the scanner shows them, in the scanner's own timezone. */
function formatDateTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    const [date, time] = iso.split('T');

    return `${date} ${time.slice(0, 5)}`;
}

function StatusCard({ status }: { status: CaptureStatus }) {
    const badge = {
        current: { label: 'Terkini', variant: 'default' as const },
        late: { label: 'Terlambat', variant: 'destructive' as const },
        never: { label: 'Belum ada data', variant: 'secondary' as const },
    }[status.state];

    return (
        <section className="rounded-xl border p-4">
            <div className="flex flex-wrap items-center gap-3">
                <h2 className="font-medium">Status data absensi</h2>
                <Badge variant={badge.variant}>{badge.label}</Badge>
            </div>
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt className="text-muted-foreground">Data sampai</dt>
                    <dd className="font-medium">
                        {formatDateTime(status.lastScanAt)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        Seharusnya sampai hari kerja
                    </dt>
                    <dd className="font-medium">{status.expectedThrough}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        ID menunggu pemetaan
                    </dt>
                    <dd className="font-medium">{status.unmapped} scan</dd>
                </div>
            </dl>
            {status.state !== 'current' && (
                <p className="mt-3 text-sm text-muted-foreground">
                    Log dari pemindai belum diunggah sampai hari kerja terakhir.
                    Hari-hari setelah data terakhir bukan berarti karyawan tidak
                    hadir; datanya hanya belum masuk.
                </p>
            )}
        </section>
    );
}

/**
 * The on-site sync agents and whether they are reporting in (ATT-DEV-001, ATT-DEV-003). With none
 * registered the page says so: the manual upload below is then the only way data arrives.
 */
function AgentsCard({ agents }: { agents: SyncAgent[] }) {
    return (
        <section className="rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-medium">Agen sinkronisasi</h2>
                {agents.length > 0 && (
                    <Button
                        size="sm"
                        variant="outline"
                        type="button"
                        onClick={() =>
                            router.post(
                                syncNow().url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Sinkronkan sekarang
                    </Button>
                )}
            </div>

            {agents.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Belum ada agen terdaftar. Data masuk lewat unggah manual di
                    bawah. Agen didaftarkan di panel admin (Agen sinkronisasi).
                </p>
            ) : (
                <ul className="mt-2 divide-y text-sm">
                    {agents.map((agent) => (
                        <li
                            key={agent.id}
                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div>
                                <span className="font-medium">
                                    {agent.name}
                                </span>
                                <div className="text-xs text-muted-foreground">
                                    Laporan terakhir{' '}
                                    {formatDateTime(agent.lastRunAt)} · listener
                                    aktif{' '}
                                    {formatDateTime(agent.lastHeartbeatAt)}
                                    {agent.lastIp ? ` · ${agent.lastIp}` : ''}
                                    {agent.syncRequested
                                        ? ' · menunggu permintaan sinkronisasi'
                                        : ''}
                                </div>
                            </div>
                            <Badge
                                variant={
                                    agent.stale ? 'destructive' : 'default'
                                }
                            >
                                {agent.stale ? 'Diam' : 'Melapor'}
                            </Badge>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function UploadCard({
    lastDeviceSn,
    knownDevices,
}: Pick<Props, 'lastDeviceSn' | 'knownDevices'>) {
    return (
        <Form
            action={storeImport().url}
            method="post"
            resetOnSuccess={['file']}
            className="grid gap-3 rounded-xl border p-4 sm:grid-cols-2"
        >
            {({ processing, errors }) => (
                <>
                    <div className="sm:col-span-2">
                        <h2 className="font-medium">Unggah log pemindai</h2>
                        <p className="text-sm text-muted-foreground">
                            Berkas CSV hasil Download ScanLog. Mengunggah berkas
                            yang sama atau tumpang tindih aman: scan yang sudah
                            ada diabaikan.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="device_sn">
                            Nomor seri pemindai (SN)
                        </Label>
                        <Input
                            id="device_sn"
                            name="device_sn"
                            list="known-devices"
                            defaultValue={lastDeviceSn ?? ''}
                            required
                        />
                        <datalist id="known-devices">
                            {knownDevices.map((serial) => (
                                <option key={serial} value={serial} />
                            ))}
                        </datalist>
                        <InputError message={errors.device_sn} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="file">Berkas (CSV)</Label>
                        <Input
                            id="file"
                            name="file"
                            type="file"
                            accept=".csv,.txt"
                            required
                        />
                        <InputError message={errors.file} />
                    </div>
                    <p className="text-xs text-muted-foreground sm:col-span-2">
                        Kolom yang dibutuhkan: PIN/ID dan tanggal-waktu scan
                        (satu kolom, atau kolom tanggal dan jam terpisah). SN,
                        verify, dan in/out bersifat opsional. Tanggal dibaca
                        hari-dulu (31/12/2026).
                    </p>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Impor log
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function WaitingRow({
    row,
    employees,
}: {
    row: WaitingId;
    employees: EmployeeOption[];
}) {
    return (
        <tr className="border-t align-top">
            <td className="p-3 font-mono">{row.pin}</td>
            <td className="p-3">{row.deviceSn}</td>
            <td className="p-3">{row.punches}</td>
            <td className="p-3 whitespace-nowrap">
                {formatDateTime(row.firstAt)} – {formatDateTime(row.lastAt)}
            </td>
            <td className="p-3">
                <Form
                    action={storeMap().url}
                    method="post"
                    className="flex flex-wrap items-center gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="device_sn"
                                value={row.deviceSn}
                            />
                            <input type="hidden" name="pin" value={row.pin} />
                            <select
                                name="employee_id"
                                defaultValue={row.suggestedEmployeeId ?? ''}
                                required
                                aria-label={`Karyawan untuk PIN ${row.pin}`}
                                className={`${selectClasses} max-w-64`}
                            >
                                <option value="">Pilih karyawan…</option>
                                {employees.map((employee) => (
                                    <option
                                        key={employee.id}
                                        value={employee.id}
                                    >
                                        {employee.label}
                                    </option>
                                ))}
                            </select>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                Petakan
                            </Button>
                            {row.suggestedEmployeeId !== null && (
                                <span className="text-xs text-muted-foreground">
                                    saran: ID sama dengan nomor karyawan
                                </span>
                            )}
                            <InputError message={errors.pin} />
                            <InputError message={errors.employee_id} />
                        </>
                    )}
                </Form>
            </td>
        </tr>
    );
}

function AddMapCard({
    lastDeviceSn,
    employees,
}: Pick<Props, 'lastDeviceSn' | 'employees'>) {
    return (
        <Form
            action={storeMap().url}
            method="post"
            resetOnSuccess
            className="flex flex-col gap-3 rounded-xl border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="font-medium">Tambah pemetaan</h2>

                    <div className="flex flex-wrap items-start gap-3">
                        <div className="grid w-44 gap-2">
                            <Label htmlFor="map-device">SN pemindai</Label>
                            <Input
                                id="map-device"
                                name="device_sn"
                                list="known-devices"
                                defaultValue={lastDeviceSn ?? ''}
                                required
                            />
                            <InputError message={errors.device_sn} />
                        </div>
                        <div className="grid w-32 gap-2">
                            <Label htmlFor="map-pin">PIN / ID</Label>
                            <Input id="map-pin" name="pin" required />
                            <InputError message={errors.pin} />
                        </div>
                        {/* min-w-0 + w-full on the select: a native select sizes itself to its
                            longest option, which is what let long names spill into the dates. */}
                        <div className="grid w-full min-w-0 gap-2 sm:w-96">
                            <Label htmlFor="map-employee">Karyawan</Label>
                            <select
                                id="map-employee"
                                name="employee_id"
                                required
                                className={`${selectClasses} w-full min-w-0 truncate`}
                            >
                                <option value="">Pilih karyawan…</option>
                                {employees.map((employee) => (
                                    <option
                                        key={employee.id}
                                        value={employee.id}
                                    >
                                        {employee.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employee_id} />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-start gap-3">
                        <div className="grid w-44 gap-2">
                            <Label htmlFor="map-from">Berlaku dari</Label>
                            <Input
                                id="map-from"
                                name="valid_from"
                                type="date"
                            />
                            <InputError message={errors.valid_from} />
                        </div>
                        <div className="grid w-44 gap-2">
                            <Label htmlFor="map-to">Sampai</Label>
                            <Input id="map-to" name="valid_to" type="date" />
                            <InputError message={errors.valid_to} />
                        </div>
                        <p className="max-w-md self-end pb-2 text-xs text-muted-foreground">
                            Tanggal opsional. Isi hanya bila PIN yang sama
                            dipakai orang lain pada waktu berbeda (karyawan
                            keluar, nomor dipakai ulang).
                        </p>
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            Simpan pemetaan
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

/**
 * The attendance data page (ATT-DEV): how current the scanner log is, the upload that refreshes
 * it, scanner IDs waiting to be matched to employees, and the matches already made. Times are
 * shown in the scanner's own timezone, as that is what people compare them against.
 */
export default function AttendanceDevice({
    status,
    agents,
    timezone,
    lastDeviceSn,
    knownDevices,
    imports,
    waiting,
    maps,
    employees,
}: Props) {
    return (
        <>
            <Head title="Data absensi" />

            <div className="flex flex-col gap-4 p-4">
                <StatusCard status={status} />

                <AgentsCard agents={agents} />

                <UploadCard
                    lastDeviceSn={lastDeviceSn}
                    knownDevices={knownDevices}
                />

                <section className="rounded-xl border">
                    <div className="p-4">
                        <h2 className="font-medium">
                            ID menunggu pemetaan ({waiting.length})
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Scan dari ID yang belum dipetakan ke karyawan
                            disimpan, tidak dibuang. Setelah dipetakan, scan
                            tersebut otomatis tercatat sebagai absensi.
                        </p>
                    </div>
                    {waiting.length === 0 ? (
                        <p className="border-t p-4 text-sm text-muted-foreground">
                            Tidak ada ID yang menunggu.
                        </p>
                    ) : (
                        <div className="overflow-x-auto border-t">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">PIN</th>
                                        <th className="p-3 font-medium">SN</th>
                                        <th className="p-3 font-medium">
                                            Scan
                                        </th>
                                        <th className="p-3 font-medium">
                                            Rentang
                                        </th>
                                        <th className="p-3 font-medium">
                                            Karyawan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {waiting.map((row) => (
                                        <WaitingRow
                                            key={`${row.deviceSn}|${row.pin}`}
                                            row={row}
                                            employees={employees}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <AddMapCard lastDeviceSn={lastDeviceSn} employees={employees} />

                <section className="rounded-xl border">
                    <h2 className="p-4 font-medium">
                        Pemetaan ID ({maps.length})
                    </h2>
                    {maps.length === 0 ? (
                        <p className="border-t p-4 text-sm text-muted-foreground">
                            Belum ada pemetaan.
                        </p>
                    ) : (
                        <div className="overflow-x-auto border-t">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">PIN</th>
                                        <th className="p-3 font-medium">SN</th>
                                        <th className="p-3 font-medium">
                                            Karyawan
                                        </th>
                                        <th className="p-3 font-medium">
                                            Berlaku
                                        </th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {maps.map((map) => (
                                        <tr key={map.id} className="border-t">
                                            <td className="p-3 font-mono">
                                                {map.pin}
                                            </td>
                                            <td className="p-3">
                                                {map.deviceSn}
                                            </td>
                                            <td className="p-3">
                                                {map.employee} (
                                                {map.employeeNumber})
                                                {map.companyCode
                                                    ? ` · ${map.companyCode}`
                                                    : ''}
                                            </td>
                                            <td className="p-3 whitespace-nowrap">
                                                {map.validFrom ?? '—'} →{' '}
                                                {map.validTo ?? '—'}
                                            </td>
                                            <td className="p-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Hapus pemetaan PIN ${map.pin}? Scan yang sudah tercatat tetap ada.`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                destroy(map.id)
                                                                    .url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Hapus
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-xl border">
                    <h2 className="p-4 font-medium">
                        Riwayat impor{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            (waktu {timezone})
                        </span>
                    </h2>
                    {imports.length === 0 ? (
                        <p className="border-t p-4 text-sm text-muted-foreground">
                            Belum ada impor.
                        </p>
                    ) : (
                        <div className="overflow-x-auto border-t">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Diunggah
                                        </th>
                                        <th className="p-3 font-medium">
                                            Berkas
                                        </th>
                                        <th className="p-3 font-medium">
                                            Rentang scan
                                        </th>
                                        <th className="p-3 font-medium">
                                            Baru
                                        </th>
                                        <th className="p-3 font-medium">
                                            Duplikat
                                        </th>
                                        <th className="p-3 font-medium">
                                            Menunggu
                                        </th>
                                        <th className="p-3 font-medium">
                                            Ditolak
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {imports.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t align-top"
                                        >
                                            <td className="p-3 whitespace-nowrap">
                                                {formatDateTime(row.at)}
                                                <div className="text-xs text-muted-foreground">
                                                    {row.by ?? '—'}
                                                </div>
                                            </td>
                                            <td className="p-3">
                                                {row.file ?? '—'}
                                                <div className="text-xs text-muted-foreground">
                                                    SN {row.deviceSn} ·{' '}
                                                    {row.rowsTotal} baris
                                                </div>
                                            </td>
                                            <td className="p-3 whitespace-nowrap">
                                                {formatDateTime(
                                                    row.firstScanAt,
                                                )}{' '}
                                                –{' '}
                                                {formatDateTime(row.lastScanAt)}
                                            </td>
                                            <td className="p-3">
                                                {row.accepted}
                                            </td>
                                            <td className="p-3">
                                                {row.duplicates}
                                            </td>
                                            <td className="p-3">
                                                {row.unmapped}
                                            </td>
                                            <td className="p-3">
                                                {row.rejected}
                                                {row.errors.length > 0 && (
                                                    <details className="mt-1 text-xs text-muted-foreground">
                                                        <summary className="cursor-pointer">
                                                            Lihat
                                                        </summary>
                                                        <ul className="mt-1 list-disc pl-4">
                                                            {row.errors.map(
                                                                (message) => (
                                                                    <li
                                                                        key={
                                                                            message
                                                                        }
                                                                    >
                                                                        {
                                                                            message
                                                                        }
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </details>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

AttendanceDevice.layout = {
    breadcrumbs: [
        { title: 'Data absensi', href: device() },
    ] satisfies BreadcrumbItem[],
};
