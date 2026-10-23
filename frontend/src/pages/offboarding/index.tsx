import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/offboarding';
import type { BreadcrumbItem } from '@/types';
import type {
    OffboardingFilters,
    OffboardingRow,
    OffboardingSummary,
} from '@/types/offboarding';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Offboarding', href: '/offboarding' },
];

const STATUSES: { value: string; label: string }[] = [
    { value: 'open', label: 'Semua terbuka' },
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Diajukan' },
    { value: 'checklist', label: 'Checklist' },
    { value: 'sign_off', label: 'Sign-off' },
    { value: 'closed', label: 'Ditutup' },
    { value: 'cancelled', label: 'Dibatalkan' },
    { value: 'all', label: 'Semua' },
];

const LAST_DAYS: { value: string; label: string }[] = [
    { value: '', label: 'Hari terakhir: semua' },
    { value: 'week', label: 'Minggu ini' },
    { value: 'month', label: 'Bulan ini' },
    { value: 'quarter', label: '3 bulan ke depan' },
];

const selectClasses =
    'border-input bg-card h-9 rounded-xl border px-3 text-sm';

function Progress({ step }: { step: number }) {
    return (
        <span className="label-mono" title={`Tahap ${step} dari 3`}>
            {step}/3
        </span>
    );
}

/**
 * The offboarding dashboard (OFF-DSH): the cases this person may see, by stage. A row opens the case
 * on its current stage; a draft opens the wizard instead.
 */
export default function OffboardingIndex({
    cases,
    filters,
    summary,
    branches,
    departments,
    canCreate,
}: {
    cases: OffboardingRow[];
    filters: OffboardingFilters;
    summary: OffboardingSummary;
    branches: string[];
    departments: string[];
    canCreate: boolean;
}) {
    setLayoutProps({ breadcrumbs });

    const go = (next: Partial<Record<string, string | null>>) => {
        const query = {
            status: filters.status,
            branch: filters.branch,
            department: filters.department,
            last_day: filters.lastDay,
            ...next,
        };

        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(query).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Offboarding" />

            <div className="space-y-5 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            Offboarding
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {summary.open} kasus terbuka
                            {summary.oldestDays !== null
                                ? ` · terlama ${summary.oldestDays} hari`
                                : ''}
                        </p>
                    </div>
                    {canCreate && (
                        <Button asChild data-test="start-case">
                            <Link href={create().url}>Mulai kasus</Link>
                        </Button>
                    )}
                </header>

                <section className="grid gap-3 sm:grid-cols-3">
                    {(
                        [
                            ['submitted', 'Diajukan'],
                            ['checklist', 'Checklist'],
                            ['sign_off', 'Sign-off'],
                        ] as const
                    ).map(([stage, label]) => (
                        <div key={stage} className="surface p-4">
                            <p className="label-mono">{label}</p>
                            <p className="text-3xl font-extrabold tracking-tight">
                                {summary.perStage[stage] ?? 0}
                            </p>
                        </div>
                    ))}
                </section>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="pill-tabs">
                        {STATUSES.map((status) => (
                            <button
                                key={status.value}
                                type="button"
                                data-on={filters.status === status.value}
                                onClick={() => go({ status: status.value })}
                            >
                                {status.label}
                            </button>
                        ))}
                    </div>
                    <select
                        className={selectClasses}
                        value={filters.branch ?? ''}
                        onChange={(event) => go({ branch: event.target.value })}
                        aria-label="Cabang"
                    >
                        <option value="">Cabang: semua</option>
                        {branches.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                    <select
                        className={selectClasses}
                        value={filters.department ?? ''}
                        onChange={(event) =>
                            go({ department: event.target.value })
                        }
                        aria-label="Departemen"
                    >
                        <option value="">Departemen: semua</option>
                        {departments.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                    <select
                        className={selectClasses}
                        value={filters.lastDay ?? ''}
                        onChange={(event) =>
                            go({ last_day: event.target.value })
                        }
                        aria-label="Hari terakhir"
                    >
                        {LAST_DAYS.map((range) => (
                            <option key={range.value} value={range.value}>
                                {range.label}
                            </option>
                        ))}
                    </select>
                </div>

                {cases.length === 0 ? (
                    <p className="surface p-6 text-center text-sm text-muted-foreground">
                        Tidak ada kasus yang cocok.
                    </p>
                ) : (
                    <div className="surface overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">
                                        Karyawan
                                    </th>
                                    <th className="hidden p-3 font-medium md:table-cell">
                                        Jabatan · Departemen
                                    </th>
                                    <th className="p-3 font-medium">
                                        Hari terakhir
                                    </th>
                                    <th className="p-3 font-medium">Tahap</th>
                                    <th className="hidden p-3 font-medium sm:table-cell">
                                        Progres
                                    </th>
                                    <th className="hidden p-3 font-medium lg:table-cell">
                                        PIC
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {cases.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t hover:bg-muted/30"
                                    >
                                        <td className="p-3">
                                            <Link
                                                href={show(row.id).url}
                                                className="font-medium hover:underline"
                                            >
                                                {row.employee.name}
                                            </Link>
                                            <div className="label-mono">
                                                {row.reference} ·{' '}
                                                {row.exitType ?? '—'}
                                            </div>
                                            <Badge
                                                variant={
                                                    row.stage === 'closed'
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                                className="mt-1"
                                            >
                                                {row.statusLabel}
                                            </Badge>
                                        </td>
                                        <td className="hidden p-3 md:table-cell">
                                            {[
                                                row.employee.position,
                                                row.employee.department,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </td>
                                        <td className="p-3 tabular-nums">
                                            {row.lastWorkingOn ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {row.stageLabel}
                                        </td>
                                        <td className="hidden p-3 sm:table-cell">
                                            <Progress step={row.step} />
                                        </td>
                                        <td className="hidden p-3 lg:table-cell">
                                            {row.createdBy ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
