import { Head, setLayoutProps } from '@inertiajs/react';
import {
    EmployeeForm,
    type EmployeeFormOptions,
} from '@/components/employee-form';
import { update } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { EmployeeDetail } from '@/types/employee';

/** Edit an employee (EMP-ADD-003). Every change is written to the profile audit. */
export default function EmployeeEdit({
    employee,
    ...options
}: EmployeeFormOptions & { employee: EmployeeDetail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/employees' },
        { title: employee.name, href: `/employees/${employee.id}` },
        { title: 'Ubah', href: `/employees/${employee.id}/edit` },
    ];

    setLayoutProps({ breadcrumbs });

    return (
        <>
            <Head title={`Ubah ${employee.name}`} />

            <div className="p-4">
                <EmployeeForm
                    action={update(employee.id).url}
                    method="put"
                    options={options}
                    employee={employee}
                    submitLabel="Simpan perubahan"
                />
            </div>
        </>
    );
}
