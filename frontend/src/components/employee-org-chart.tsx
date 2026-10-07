import { Link } from '@inertiajs/react';
import { show } from '@/routes/employees';
import type { EmployeeRow } from '@/types/employee';

/**
 * The org-chart view (EMP-CHT): the directory drawn down the reporting line instead of as a
 * table.
 *
 * Anyone whose manager is not in the current result set is treated as a root, so the chart
 * still renders something sensible when a filter has removed the people above them rather
 * than silently dropping their whole branch.
 */
function Node({
    employee,
    childrenByManager,
    depth,
}: {
    employee: EmployeeRow;
    childrenByManager: Map<number, EmployeeRow[]>;
    depth: number;
}) {
    const reports = childrenByManager.get(employee.id) ?? [];

    return (
        <li>
            <Link
                href={show(employee.id).url}
                className="inline-flex flex-col rounded-lg border px-3 py-2 text-sm hover:bg-muted/60"
            >
                <span className="font-medium">{employee.name}</span>
                <span className="text-xs text-muted-foreground">
                    {employee.position ?? employee.number}
                    {employee.department ? ` · ${employee.department}` : ''}
                </span>
            </Link>

            {reports.length > 0 && (
                <ul className="mt-2 ml-6 flex flex-col gap-2 border-l pl-4">
                    {reports.map((report) => (
                        <Node
                            key={report.id}
                            employee={report}
                            childrenByManager={childrenByManager}
                            depth={depth + 1}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export function EmployeeOrgChart({ employees }: { employees: EmployeeRow[] }) {
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
        <div className="rounded-xl border p-4">
            <ul className="flex flex-col gap-3">
                {roots.map((root) => (
                    <Node
                        key={root.id}
                        employee={root}
                        childrenByManager={childrenByManager}
                        depth={0}
                    />
                ))}
            </ul>
        </div>
    );
}
