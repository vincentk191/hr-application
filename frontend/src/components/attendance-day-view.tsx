import { Link } from '@inertiajs/react';
import {
    FileText,
    LogIn,
    LogOut,
    Building2,
    ScanLine,
    Search,
    SlidersHorizontal,
    Sigma,
    User,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { StatusLegend } from '@/components/attendance-legend';
import { Input } from '@/components/ui/input';
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
 * The day of the attendance calendar: who came, when, and what they filed. Above the list sit the search, the
 * filters and one chip per status with how many people are in it (all of them to begin with), so the list is
 * read by picking a status instead of scrolling past everyone.
 *
 * People of a Cabang that sends a rekapitulasi have no daily attendance, only their Pengajuan, so they have a
 * chip of their own rather than a status.
 */

type Pick = 'all' | 'rekap' | DayStatus;

const GRID =
    'grid grid-cols-[auto_minmax(0,1fr)_auto] md:grid-cols-[4rem_minmax(12rem,1.5fr)_minmax(8rem,1fr)_minmax(7rem,1fr)_5.5rem_5.5rem_minmax(9rem,1.2fr)]';

function pickOf(row: CalendarRow, date: string): Pick {
    return row.viaRekap ? 'rekap' : (row.days[date]?.status ?? 'no_data');
}

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

export function DayView({
    date,
    rows,
    statuses,
    search,
    onSearchChange,
    onSearch,
    filterCount,
    onOpenFilters,
}: {
    date: string;
    rows: CalendarRow[];
    statuses: StatusOption[];
    search: string;
    onSearchChange: (value: string) => void;
    onSearch: () => void;
    /** How many filters are on, shown on the filter button. */
    filterCount: number;
    onOpenFilters: () => void;
}) {
    const [pick, setPick] = useState<Pick>('all');
    const [grouped, setGrouped] = useState(false);

    const codeOf = Object.fromEntries(
        statuses.map((status) => [status.value, status.code]),
    ) as Record<DayStatus, string>;

    const counts = new Map<Pick, number>();

    rows.forEach((row) => {
        const key = pickOf(row, date);

        counts.set(key, (counts.get(key) ?? 0) + 1);
    });

    // A chip only for a status somebody is in; the one that was picked stays even once it empties.
    const chips: { key: Pick; code: string; label: string }[] = [
        ...statuses.map((status) => ({
            key: status.value as Pick,
            code: status.code,
            label: status.label,
        })),
        { key: 'rekap' as const, code: 'R', label: 'Rekapitulasi cabang' },
    ].filter((chip) => (counts.get(chip.key) ?? 0) > 0 || chip.key === pick);

    const shown = rows.filter(
        (row) => pick === 'all' || pickOf(row, date) === pick,
    );

    const units = [...new Set(rows.map(unitOf))].sort((a, b) =>
        a.localeCompare(b, 'id'),
    );
    const groups: { unit: string | null; rows: CalendarRow[] }[] = grouped
        ? units
              .map((unit) => ({
                  unit,
                  rows: shown.filter((row) => unitOf(row) === unit),
              }))
              .filter((group) => group.rows.length > 0)
        : [{ unit: null, rows: shown }];

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2 md:gap-3">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSearch();
                    }}
                    className="relative min-w-0 flex-1 md:max-w-sm"
                >
                    <Search
                        className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder="Cari nama / ID"
                        aria-label="Cari karyawan"
                        className="h-12 rounded-full pl-10 md:h-11"
                    />
                </form>

                <button
                    type="button"
                    onClick={onOpenFilters}
                    aria-label="Cari & filter"
                    data-test="open-filters"
                    className="relative inline-flex size-12 shrink-0 items-center justify-center gap-2 rounded-full border bg-card text-sm font-semibold md:h-11 md:w-auto md:px-5"
                >
                    <SlidersHorizontal className="size-4" aria-hidden />
                    <span className="hidden md:inline">Filter</span>
                    {filterCount > 0 && (
                        <span className="absolute -top-1 -right-1 inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground md:static md:size-5 md:text-[11px]">
                            {filterCount}
                        </span>
                    )}
                </button>

                <span
                    aria-hidden
                    className="mx-1 hidden h-6 border-l md:block"
                />

                <div
                    role="tablist"
                    className="order-last grid w-full grid-cols-4 gap-2 md:contents"
                >
                    {[
                        { key: 'all' as const, code: 'Σ', label: 'Semua' },
                        ...chips,
                    ].map((chip) => {
                        const count =
                            chip.key === 'all'
                                ? rows.length
                                : (counts.get(chip.key) ?? 0);
                        const on = pick === chip.key;

                        return (
                            <button
                                key={chip.key}
                                type="button"
                                role="tab"
                                aria-selected={on}
                                data-test={`day-tab-${chip.key}`}
                                title={chip.label}
                                onClick={() => setPick(chip.key)}
                                className={cn(
                                    'inline-flex h-14 items-center justify-center gap-2.5 rounded-2xl border bg-card px-2 transition-colors hover:bg-muted/40 md:h-11 md:rounded-full md:pr-5 md:pl-2',
                                    on &&
                                        'border-foreground ring-1 ring-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'inline-flex size-8 items-center justify-center rounded-lg border text-sm font-bold md:size-7 md:rounded-full md:text-xs',
                                        chip.key === 'all'
                                            ? 'border-border bg-muted text-foreground'
                                            : chip.key === 'rekap'
                                              ? STATUS_TILE.off
                                              : STATUS_TILE[chip.key],
                                    )}
                                >
                                    {chip.key === 'all' ? (
                                        <Sigma className="size-4" aria-hidden />
                                    ) : (
                                        chip.code
                                    )}
                                </span>
                                <span className="text-lg font-bold tabular-nums">
                                    {count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div
                    role="group"
                    aria-label="Susunan daftar"
                    className="inline-flex shrink-0 items-center gap-1 rounded-full border bg-card p-1 md:ml-auto"
                >
                    <button
                        type="button"
                        aria-pressed={!grouped}
                        aria-label="Semua karyawan"
                        onClick={() => setGrouped(false)}
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
                        onClick={() => setGrouped((value) => !value)}
                        className={cn(
                            'inline-flex h-10 items-center justify-center gap-1.5 rounded-full px-3 text-sm text-muted-foreground md:h-9 md:px-3',
                            grouped && 'bg-foreground text-background',
                        )}
                    >
                        <Building2 className="size-4" aria-hidden />
                        <span className="tabular-nums">{units.length}</span>
                    </button>
                </div>
            </div>

            {shown.length === 0 ? (
                <p className="surface p-6 text-center text-sm text-muted-foreground">
                    {rows.length === 0
                        ? 'Tidak ada karyawan yang cocok.'
                        : 'Tidak ada karyawan di pilihan ini.'}
                </p>
            ) : (
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
                                        <span className="label-mono">
                                            {group.unit}
                                        </span>
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
            )}

            <StatusLegend statuses={statuses}>
                <li className="flex items-center gap-1.5">
                    <RequestDot status="submitted" />
                    Menunggu
                </li>
                <li className="flex items-center gap-1.5">
                    <RequestDot status="approved" />
                    Disetujui
                </li>
            </StatusLegend>
        </div>
    );
}
