import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { useIsMobile } from '@/hooks/use-mobile';
import { openInDrawer } from '@/lib/open-in-drawer';
import { show } from '@/routes/employees';
import type { EmployeeRow } from '@/types/employee';

/**
 * The org-chart view (EMP-CHT): the directory drawn as a tree, manager above reports.
 *
 * Tablet and up draw it the traditional way, box above box with connector lines (CSS, `.org-tree` in
 * app.css), side by side per root. That layout needs width a phone does not have, so there it is instead
 * a collapsible folder tree: one row per person, reports indented underneath, opened a branch at a time.
 *
 * Anyone whose manager is not in the current result set is treated as a root, so the chart still
 * renders something sensible when a filter has removed the people above them rather than silently
 * dropping their whole branch. Each root is its own tree.
 */
function Node({
    employee,
    childrenByManager,
    onOpen,
}: {
    employee: EmployeeRow;
    childrenByManager: Map<number, EmployeeRow[]>;
    onOpen?: (id: number) => void;
}) {
    const reports = childrenByManager.get(employee.id) ?? [];

    return (
        <li>
            <Link
                href={show(employee.id).url}
                onClick={(event) => openInDrawer(event, employee.id, onOpen)}
                className="flex w-44 flex-col items-center gap-1 rounded-lg border bg-card px-3 py-2 text-center text-sm shadow-xs hover:bg-muted/60"
            >
                <EmployeeAvatar
                    name={employee.name}
                    photoUrl={employee.photoUrl}
                    className="size-10"
                />
                <span className="leading-tight font-medium">
                    {employee.name}
                </span>
                <span className="text-xs leading-tight text-muted-foreground">
                    {employee.position ?? employee.number}
                </span>
                {employee.department && (
                    <span className="text-xs leading-tight text-muted-foreground">
                        {employee.department}
                    </span>
                )}
            </Link>

            {reports.length > 0 && (
                <ul>
                    {reports.map((report) => (
                        <Node
                            key={report.id}
                            employee={report}
                            childrenByManager={childrenByManager}
                            onOpen={onOpen}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

/**
 * One row of the phone tree: the person, indented under their manager, with a disclosure arrow when
 * they have reports. Starts closed below the top level, so opening the chart on a large company does
 * not dump everyone on screen at once; the root level opens by itself since that is the whole point
 * of a first look at the chart.
 */
function TreeRow({
    employee,
    childrenByManager,
    depth,
    onOpen,
}: {
    employee: EmployeeRow;
    childrenByManager: Map<number, EmployeeRow[]>;
    depth: number;
    onOpen?: (id: number) => void;
}) {
    const reports = childrenByManager.get(employee.id) ?? [];
    const [open, setOpen] = useState(depth === 0);

    return (
        <li>
            <div
                className="flex items-center gap-1 border-t py-2 first:border-t-0"
                style={{ paddingLeft: `${depth * 1.25}rem` }}
            >
                {reports.length > 0 ? (
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        aria-label={open ? 'Tutup' : 'Buka'}
                        aria-expanded={open}
                        className="flex size-7 shrink-0 items-center justify-center text-muted-foreground"
                    >
                        <ChevronRight
                            aria-hidden
                            className={`size-4 transition-transform ${open ? 'rotate-90' : ''}`}
                        />
                    </button>
                ) : (
                    <span className="size-7 shrink-0" aria-hidden />
                )}

                <Link
                    href={show(employee.id).url}
                    onClick={(event) =>
                        openInDrawer(event, employee.id, onOpen)
                    }
                    className="flex min-w-0 flex-1 items-center gap-2 py-1"
                >
                    <EmployeeAvatar
                        name={employee.name}
                        photoUrl={employee.photoUrl}
                        className="size-8 shrink-0"
                    />
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium">
                            {employee.name}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {employee.position ?? employee.number}
                        </span>
                    </span>
                    {reports.length > 0 && (
                        <span className="label-mono shrink-0 text-muted-foreground">
                            {reports.length}
                        </span>
                    )}
                </Link>
            </div>

            {open && reports.length > 0 && (
                <ul>
                    {reports.map((report) => (
                        <TreeRow
                            key={report.id}
                            employee={report}
                            childrenByManager={childrenByManager}
                            depth={depth + 1}
                            onOpen={onOpen}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export function EmployeeOrgChart({
    employees,
    onOpen,
}: {
    employees: EmployeeRow[];
    onOpen?: (id: number) => void;
}) {
    const isMobile = useIsMobile();
    const present = new Set(employees.map((employee) => employee.id));

    const childrenByManager = new Map<number, EmployeeRow[]>();
    const roots: EmployeeRow[] = [];

    for (const employee of employees) {
        const managerId = employee.managerId;

        if (managerId !== null && present.has(managerId)) {
            const siblings = childrenByManager.get(managerId) ?? [];
            siblings.push(employee);
            childrenByManager.set(managerId, siblings);
        } else {
            roots.push(employee);
        }
    }

    if (employees.length === 0) {
        return (
            <p className="surface p-6 text-center text-sm text-muted-foreground">
                Tidak ada karyawan.
            </p>
        );
    }

    if (isMobile) {
        return (
            <div className="surface px-3">
                <ul>
                    {roots.map((root) => (
                        <TreeRow
                            key={root.id}
                            employee={root}
                            childrenByManager={childrenByManager}
                            depth={0}
                            onOpen={onOpen}
                        />
                    ))}
                </ul>
            </div>
        );
    }

    return (
        <div className="surface overflow-x-auto p-6">
            <div className="flex w-max min-w-full items-start justify-center gap-10">
                {roots.map((root) => (
                    <ul key={root.id} className="org-tree">
                        <Node
                            employee={root}
                            childrenByManager={childrenByManager}
                            onOpen={onOpen}
                        />
                    </ul>
                ))}
            </div>
        </div>
    );
}
