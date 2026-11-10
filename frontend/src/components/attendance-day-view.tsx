import { Link } from '@inertiajs/react';
import {
    Building2,
    FileText,
    LogIn,
    LogOut,
    ScanLine,
    User,
    Users,
} from 'lucide-react';
import { STATUS_LABEL, STATUS_TILE } from '@/lib/attendance-status';
import { cn } from '@/lib/utils';
import { show as employeeShow } from '@/routes/employees';
import type {
    CalendarRequest,
    CalendarRow,
    DayStatus,
    StatusOption,
} from '@/types/attendance';

/**
 * The day of the attendance calendar: who came, when, and what they filed. The search, the filters and the status
 * chips above it are the calendar's own row, shared with the other views (attendance-filter-bar); the list shows
 * whoever they leave.
 *
 * People of a Cabang that sends a rekapitulasi have no daily attendance, only their Pengajuan, so they carry an R
 * rather than a status.
 */

const GRID =
    'grid grid-cols-[auto_minmax(0,1fr)_auto] md:grid-cols-[4rem_minmax(12rem,1.5fr)_minmax(8rem,1fr)_minmax(7rem,1fr)_5.5rem_5.5rem_minmax(9rem,1.2fr)]';

/** "Design · Jakarta": the department and the Cabang, whichever the person has. */
function unitOf(row: CalendarRow): string {
    return [row.department, row.branch].filter(Boolean).join(' · ') || '—';
}

function coveringOn(row: CalendarRow, date: string): CalendarRequest[] {
    return row.requests.filter(
        (request) => request.from <= date && request.to >= date,
    );
}

/** A dot for where a Pengajuan stands: amber while it waits, green once approved. */
function RequestDot({ status }: { status: CalendarRequest['status'] }) {
    return (
        <span
            aria-hidden
            className={cn(
                'size-2 shrink-0 rounded-full',
                status === 'approved' ? 'bg-emerald-500' : 'bg-amber-400',
            )}
        />
    );
}

function requestTitle(request: CalendarRequest): string {
    return [request.type, request.statusLabel, request.timeframe]
        .filter(Boolean)
        .join(' · ');
}

/** The Pengajuan of the day in the list's own column: a pill each, kept apart from the attendance status. */
function RequestPills({ requests }: { requests: CalendarRequest[] }) {
    return (
        <div className="flex min-w-0 flex-col items-start gap-1">
            {requests.map((request) => (
                <span
                    key={request.id}
                    data-test="request-chip"
                    title={requestTitle(request)}
                    className="inline-flex max-w-full items-center gap-2 rounded-full border px-3 py-1 text-sm"
                >
                    <RequestDot status={request.status} />
                    <span className="truncate">{request.type}</span>
                </span>
            ))}
        </div>
    );
}

/** The same on a phone, short: the first word of the type under the times. */
function RequestLines({ requests }: { requests: CalendarRequest[] }) {
    return (
        <div className="flex flex-col items-end gap-0.5">
            {requests.map((request) => (
                <span
                    key={request.id}
                    title={requestTitle(request)}
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <RequestDot status={request.status} />
                    <FileText className="size-3.5" aria-hidden />
                    {request.type.split(' ')[0]}
                </span>
            ))}
        </div>
    );
}

function Time({
    value,
    warn,
}: {
    value: string | null | undefined;
    warn?: boolean;
}) {
    return value ? (
        <span
            className={cn(
                'font-mono font-semibold tabular-nums',
                warn && 'text-amber-600 dark:text-amber-300',
            )}
        >
            {value}
        </span>
    ) : (
        <span className="font-mono text-muted-foreground/60">—</span>
    );
}

function PersonRow({
    row,
    date,
    codeOf,
}: {
    row: CalendarRow;
    date: string;
    codeOf: Record<DayStatus, string>;
}) {
    const record = row.days[date];
    const status: DayStatus = record?.status ?? 'no_data';
    const requests = coveringOn(row, date);
    const holiday =
        record?.status === 'off' && record.event ? record.event : null;
    const note = row.viaRekap ? (
        <span className="inline-flex items-center gap-1 text-muted-foreground">
            <Users className="size-3.5 shrink-0" aria-hidden />
            Lewat rekapitulasi cabang
        </span>
    ) : (
        !row.hasScannerId && (
            <span className="inline-flex items-center gap-1 text-amber-600 dark:text-amber-300">
                <ScanLine className="size-3.5 shrink-0" aria-hidden />
                ID belum dipetakan
            </span>
        )
    );

    return (
        <div
            role="row"
            className={cn(
                GRID,
                'items-center gap-x-3 border-t px-4 py-3 md:gap-x-4 md:pr-4 md:pl-0',
            )}
        >
            <span
                role="cell"
                title={holiday ?? STATUS_LABEL[status]}
                className={cn(
                    'flex size-10 items-center justify-center rounded-lg border text-sm font-bold md:mx-auto md:size-9',
                    row.viaRekap ? STATUS_TILE.off : STATUS_TILE[status],
                )}
            >
                {row.viaRekap ? 'R' : codeOf[status]}
            </span>

            <div role="rowheader" className="min-w-0">
                <Link
                    href={employeeShow(row.id)}
                    className="block truncate font-semibold hover:underline"
                >
                    {row.name}
                </Link>
                <div className="flex min-w-0 flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                    <span className="hidden truncate md:inline">
                        {row.position ?? row.number}
                    </span>
                    <span className="truncate md:hidden">
                        {note || unitOf(row)}
                    </span>
                    <span className="hidden md:inline">{note}</span>
                </div>
            </div>

            <span role="cell" className="hidden truncate text-sm md:block">
                {unitOf(row)}
            </span>
            <span role="cell" className="hidden truncate text-sm md:block">
                {row.manager ?? '—'}
            </span>
            <span role="cell" className="hidden text-sm md:block">
                <Time value={record?.in} warn={status === 'late'} />
            </span>
            <span role="cell" className="hidden text-sm md:block">
                <Time value={record?.out} warn={status === 'early_leave'} />
            </span>
            <div role="cell" className="hidden min-w-0 md:block">
                {requests.length > 0 ? (
                    <RequestPills requests={requests} />
                ) : (
                    <span className="text-sm text-muted-foreground">
                        {holiday ?? '—'}
                    </span>
                )}
            </div>

            <div
                role="cell"
                className="flex flex-col items-end gap-1 text-sm md:hidden"
            >
                <span className="inline-flex items-center gap-2">
                    <Time value={record?.in} warn={status === 'late'} />
                    <span aria-hidden className="text-muted-foreground">
                        →
                    </span>
                    <Time value={record?.out} warn={status === 'early_leave'} />
                </span>
                {requests.length > 0 ? (
                    <RequestLines requests={requests} />
                ) : (
                    holiday && (
                        <span className="text-xs text-muted-foreground">
                            {holiday}
                        </span>
                    )
                )}
            </div>
        </div>
    );
}

/** How many units (department · Cabang) the people on the list are in. */
export function unitCount(rows: CalendarRow[]): number {
    return new Set(rows.map(unitOf)).size;
}

/** The two ways to read the list: everyone, or grouped by unit (the number is how many units there are). */
export function GroupToggle({
    grouped,
    onChange,
    units,
}: {
    grouped: boolean;
    onChange: (grouped: boolean) => void;
    units: number;
}) {
    return (
        <div
            role="group"
            aria-label="Susunan daftar"
            className="inline-flex shrink-0 items-center gap-1 rounded-full border bg-card p-1"
        >
            <button
                type="button"
                aria-pressed={!grouped}
                aria-label="Semua karyawan"
                onClick={() => onChange(false)}
                className={cn(
                    'hidden size-9 items-center justify-center rounded-full text-muted-foreground md:inline-flex',
                    !grouped && 'bg-foreground text-background',
                )}
            >
                <User className="size-4" aria-hidden />
            </button>
            <button
                type="button"
                aria-pressed={grouped}
                aria-label="Kelompokkan per unit"
                title="Kelompokkan per unit"
                onClick={() => onChange(!grouped)}
                className={cn(
                    'inline-flex h-10 items-center justify-center gap-1.5 rounded-full px-3 text-sm text-muted-foreground md:h-9',
                    grouped && 'bg-foreground text-background',
                )}
            >
                <Building2 className="size-4" aria-hidden />
                <span className="tabular-nums">{units}</span>
            </button>
        </div>
    );
}

export function DayView({
    date,
    rows,
    statuses,
    grouped,
}: {
    date: string;
    rows: CalendarRow[];
    statuses: StatusOption[];
    grouped: boolean;
}) {
    const codeOf = Object.fromEntries(
        statuses.map((status) => [status.value, status.code]),
    ) as Record<DayStatus, string>;

    const units = [...new Set(rows.map(unitOf))].sort((a, b) =>
        a.localeCompare(b, 'id'),
    );
    const groups: { unit: string | null; rows: CalendarRow[] }[] = grouped
        ? units.map((unit) => ({
              unit,
              rows: rows.filter((row) => unitOf(row) === unit),
          }))
        : [{ unit: null, rows }];

    if (rows.length === 0) {
        return (
            <p className="surface p-6 text-center text-sm text-muted-foreground">
                Tidak ada karyawan yang cocok.
            </p>
        );
    }

    return (
        <div className="surface overflow-hidden">
            <div role="table" className="md:min-w-[56rem]">
                <div
                    role="row"
                    className={cn(
                        GRID,
                        'hidden items-center gap-x-4 py-4 pr-4 md:grid',
                    )}
                >
                    <span role="columnheader" aria-hidden />
                    <span role="columnheader" className="label-mono">
                        Karyawan
                    </span>
                    <span role="columnheader" className="label-mono">
                        Unit
                    </span>
                    <span role="columnheader" className="label-mono">
                        Atasan
                    </span>
                    <span role="columnheader" className="label-mono">
                        <LogIn className="size-4" aria-hidden />
                        <span className="sr-only">Masuk</span>
                    </span>
                    <span role="columnheader" className="label-mono">
                        <LogOut className="size-4" aria-hidden />
                        <span className="sr-only">Pulang</span>
                    </span>
                    <span role="columnheader" className="label-mono">
                        Pengajuan
                    </span>
                </div>

                {groups.map((group) => (
                    <div key={group.unit ?? 'all'} role="rowgroup">
                        {group.unit !== null && (
                            <div
                                role="row"
                                className="flex items-center justify-between border-t bg-muted/40 px-4 py-2"
                            >
                                <span className="label-mono">{group.unit}</span>
                                <span className="text-xs font-semibold text-muted-foreground tabular-nums">
                                    {group.rows.length}
                                </span>
                            </div>
                        )}
                        {group.rows.map((row) => (
                            <PersonRow
                                key={row.id}
                                row={row}
                                date={date}
                                codeOf={codeOf}
                            />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
