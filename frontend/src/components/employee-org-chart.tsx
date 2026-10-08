import { Link } from '@inertiajs/react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { openInDrawer } from '@/lib/open-in-drawer';
import { show } from '@/routes/employees';
import type { EmployeeRow } from '@/types/employee';

/**
 * The org-chart view (EMP-CHT): the directory drawn as a tree, manager above reports, with
 * connector lines between them. The lines are CSS (`.org-tree` in app.css) so the markup stays a
 * plain nested list.
 *
 * Anyone whose manager is not in the current result set is treated as a root, so the chart
 * still renders something sensible when a filter has removed the people above them rather
 * than silently dropping their whole branch. Each root is its own tree, side by side.
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

export function EmployeeOrgChart({
    employees,
    onOpen,
}: {
    employees: EmployeeRow[];
    onOpen?: (id: number) => void;
}) {
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
            <p className="rounded-xl border p-6 text-center text-sm text-muted-foreground">
                Tidak ada karyawan.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border p-6">
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
