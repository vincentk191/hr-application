import { Head, setLayoutProps } from '@inertiajs/react';
import {
    EmployeeForm,
    type EmployeeFormOptions,
} from '@/components/employee-form';
import { show } from '@/routes/recruitment';
import { convert } from '@/routes/recruitment/offers';
import type { BreadcrumbItem } from '@/types';
import type { HireDefaults } from '@/types/recruitment';

/**
 * The employee form for a hire (REC-FIL-006/007): the candidate's name and contact, and the department, Cabang, position,
 * contract, direct superior, start date and starting salary of the requisition and the offer come filled in, so HR adds
 * what is missing (the employee number and the NIK, and a Cabang when the requisition named none). It is the form of any
 * new employee, with the same checks.
 */
export default function HireEmployee({
    canEditSensitive,
    requisition,
    offerId,
    hire,
    ...options
}: EmployeeFormOptions & {
    canEditSensitive: boolean;
    requisition: { id: number; reference: string; positionName: string };
    offerId: number;
    hire: HireDefaults;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Rekrutmen', href: '/recruitment' },
        {
            title: `${requisition.reference} ${requisition.positionName}`,
            href: show(requisition.id).url,
        },
        { title: 'Jadikan karyawan', href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    return (
        <>
            <Head title={`Jadikan karyawan — ${hire.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <p className="label-mono">Jadikan karyawan</p>
                    <p className="text-sm text-muted-foreground">
                        {hire.name} · {requisition.reference} ·{' '}
                        {requisition.positionName}
                    </p>
                </div>

                <EmployeeForm
                    action={convert.url({
                        requisition: requisition.id,
                        offer: offerId,
                    })}
                    method="post"
                    options={options}
                    hire={hire}
                    canEditSensitive={canEditSensitive}
                    submitLabel="Simpan karyawan"
                />
            </div>
        </>
    );
}
