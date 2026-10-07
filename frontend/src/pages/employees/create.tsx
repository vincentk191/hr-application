import { Head } from '@inertiajs/react';
import {
    EmployeeForm,
    type EmployeeFormOptions,
} from '@/components/employee-form';
import { store } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Karyawan', href: '/employees' },
    { title: 'Tambah karyawan', href: '/employees/create' },
];

/** Add an employee (EMP-ADD-001). */
export default function EmployeeCreate({
    canEditSensitive,
    ...options
}: EmployeeFormOptions & { canEditSensitive: boolean }) {
    return (
        <>
            <Head title="Tambah karyawan" />

            <div className="p-4">
                <EmployeeForm
                    action={store().url}
                    method="post"
                    options={options}
                    canEditSensitive={canEditSensitive}
                    submitLabel="Simpan karyawan"
                />
            </div>
        </>
    );
}

EmployeeCreate.layout = { breadcrumbs };
