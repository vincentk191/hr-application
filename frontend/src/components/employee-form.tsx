import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import InputError from '@/components/input-error';
import { PhotoInput } from '@/components/photo-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    EmployeeDetail,
    ManagerOption,
    Option,
    ValueOption,
} from '@/types/employee';

export type EmployeeFormOptions = {
    /** Set only while every company is being viewed: a new employee then needs one chosen. */
    companies: Option[] | null;
    departments: (Option & { company_id: number })[];
    positions: (Option & { department_id: number; company_id: number })[];
    managers: (ManagerOption & { company_id: number })[];
    employmentStatuses: ValueOption[];
    employmentTypes: ValueOption[];
};

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="surface p-4">
            <h2 className="font-medium">{title}</h2>
            {description && (
                <p className="mb-3 text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            <div className="mt-3 grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

function TextField({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    ...rest
}: {
    name: string;
    label: string;
    defaultValue?: string | number | null;
    error?: string;
    type?: string;
    // The native defaultValue cannot be null, but a dossier field very often is, so it is
    // taken over and normalised to '' below.
} & Omit<
    React.InputHTMLAttributes<HTMLInputElement>,
    'defaultValue' | 'name' | 'type'
>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue ?? ''}
                {...rest}
            />
            <InputError message={error} />
        </div>
    );
}

/**
 * The create and edit form for a dossier record (EMP-ADD).
 *
 * One component for both, because the fields are identical — only the action and method
 * differ. Everything past the employment section is optional: the initial import brings names
 * and numbers only, and a record must be creatable before anyone has chased the rest.
 */
export function EmployeeForm({
    action,
    method,
    options,
    employee,
    canEditSensitive,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put';
    options: EmployeeFormOptions;
    employee?: EmployeeDetail;
    /** Bank, payroll and health are left out of the form for anyone who may not handle them. */
    canEditSensitive: boolean;
    submitLabel: string;
}) {
    const [companyId, setCompanyId] = useState('');
    // Viewing every company at once, a new employee has no company yet: it is chosen first, and the
    // departments, positions and managers on offer are that company's.
    const choosesCompany = options.companies !== null && employee === undefined;
    const inChosenCompany = <T extends { company_id: number }>(items: T[]) =>
        choosesCompany
            ? items.filter((item) => String(item.company_id) === companyId)
            : items;

    return (
        <Form
            action={action}
            method={method}
            className="flex max-w-4xl flex-col gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <Section title="Foto profil">
                        <div className="flex items-center gap-4 sm:col-span-2">
                            <EmployeeAvatar
                                name={employee?.name ?? '?'}
                                photoUrl={employee?.photoUrl ?? null}
                                className="size-16"
                            />
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="photo">Unggah foto</Label>
                                <PhotoInput id="photo" name="photo" />
                                <InputError message={errors.photo} />
                                {employee?.photoUrl && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="remove_photo"
                                            value="1"
                                        />
                                        Hapus foto saat ini
                                    </label>
                                )}
                            </div>
                        </div>
                    </Section>

                    <Section title="Kepegawaian">
                        {choosesCompany && (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="company_id">Perusahaan</Label>
                                <select
                                    id="company_id"
                                    name="company_id"
                                    required
                                    value={companyId}
                                    onChange={(event) =>
                                        setCompanyId(event.target.value)
                                    }
                                    className={selectClasses}
                                >
                                    <option value="">Pilih perusahaan</option>
                                    {options.companies?.map((company) => (
                                        <option
                                            key={company.id}
                                            value={company.id}
                                        >
                                            {company.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.company_id} />
                            </div>
                        )}
                        <TextField
                            name="employee_number"
                            label="ID karyawan"
                            defaultValue={employee?.number}
                            error={errors.employee_number}
                            required
                        />
                        <TextField
                            name="full_name"
                            label="Nama lengkap"
                            defaultValue={employee?.name}
                            error={errors.full_name}
                            required
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="department_id">Departemen</Label>
                            <select
                                id="department_id"
                                name="department_id"
                                defaultValue={employee?.departmentId ?? ''}
                                className={selectClasses}
                            >
                                <option value="">—</option>
                                {inChosenCompany(options.departments).map(
                                    (department) => (
                                        <option
                                            key={department.id}
                                            value={department.id}
                                        >
                                            {department.name}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={errors.department_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="position_id">Jabatan</Label>
                            <select
                                id="position_id"
                                name="position_id"
                                defaultValue={employee?.positionId ?? ''}
                                className={selectClasses}
                            >
                                <option value="">—</option>
                                {inChosenCompany(options.positions).map(
                                    (position) => (
                                        <option
                                            key={position.id}
                                            value={position.id}
                                        >
                                            {position.name}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={errors.position_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="manager_id">Atasan langsung</Label>
                            <select
                                id="manager_id"
                                name="manager_id"
                                defaultValue={employee?.managerId ?? ''}
                                className={selectClasses}
                            >
                                <option value="">—</option>
                                {inChosenCompany(options.managers)
                                    .filter(
                                        (manager) =>
                                            manager.id !== employee?.id,
                                    )
                                    .map((manager) => (
                                        <option
                                            key={manager.id}
                                            value={manager.id}
                                        >
                                            {manager.label}
                                        </option>
                                    ))}
                            </select>
                            <InputError message={errors.manager_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employment_status">Status</Label>
                            <select
                                id="employment_status"
                                name="employment_status"
                                defaultValue={
                                    employee?.employmentStatus ?? 'active'
                                }
                                className={selectClasses}
                            >
                                {options.employmentStatuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employment_status} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employment_type">
                                Jenis kepegawaian
                            </Label>
                            <select
                                id="employment_type"
                                name="employment_type"
                                defaultValue={employee?.employmentType ?? ''}
                                className={selectClasses}
                            >
                                <option value="">—</option>
                                {options.employmentTypes.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employment_type} />
                        </div>

                        <TextField
                            name="notice_days"
                            label="Masa pemberitahuan kontrak (hari)"
                            type="number"
                            min={0}
                            max={365}
                            defaultValue={employee?.noticeDays}
                            error={errors.notice_days}
                        />

                        <TextField
                            name="joined_on"
                            label="Tanggal bergabung"
                            type="date"
                            defaultValue={employee?.joinedOn}
                            error={errors.joined_on}
                        />
                        <TextField
                            name="exited_on"
                            label="Tanggal keluar"
                            type="date"
                            defaultValue={employee?.exitedOn}
                            error={errors.exited_on}
                        />
                    </Section>

                    <Section title="Kontak & kontak darurat">
                        <TextField
                            name="email"
                            label="Email"
                            type="email"
                            defaultValue={employee?.contact.email}
                            error={errors.email}
                        />
                        <TextField
                            name="phone"
                            label="Telepon"
                            defaultValue={employee?.contact.phone}
                            error={errors.phone}
                        />
                        <TextField
                            name="address"
                            label="Alamat"
                            defaultValue={employee?.contact.address}
                            error={errors.address}
                        />
                        <TextField
                            name="national_id"
                            label="NIK (16 digit, dipakai karyawan untuk mendaftar)"
                            defaultValue={employee?.contact.national_id}
                            error={errors.national_id}
                            inputMode="numeric"
                            maxLength={16}
                            required
                        />
                        <TextField
                            name="emergency_contact_name"
                            label="Nama kontak darurat"
                            defaultValue={
                                employee?.contact.emergency_contact_name
                            }
                            error={errors.emergency_contact_name}
                        />
                        <TextField
                            name="emergency_contact_phone"
                            label="Telepon darurat"
                            defaultValue={
                                employee?.contact.emergency_contact_phone
                            }
                            error={errors.emergency_contact_phone}
                        />
                        <TextField
                            name="emergency_contact_relation"
                            label="Hubungan"
                            defaultValue={
                                employee?.contact.emergency_contact_relation
                            }
                            error={errors.emergency_contact_relation}
                        />
                    </Section>

                    {canEditSensitive && (
                        <>
                            <Section title="Bank & pajak">
                                <TextField
                                    name="bank_name"
                                    label="Bank"
                                    defaultValue={employee?.bank?.bank_name}
                                    error={errors.bank_name}
                                />
                                <TextField
                                    name="bank_account_number"
                                    label="No. rekening"
                                    defaultValue={
                                        employee?.bank?.bank_account_number
                                    }
                                    error={errors.bank_account_number}
                                />
                                <TextField
                                    name="bank_account_holder"
                                    label="Atas nama"
                                    defaultValue={
                                        employee?.bank?.bank_account_holder
                                    }
                                    error={errors.bank_account_holder}
                                />
                                <TextField
                                    name="tax_number"
                                    label="NPWP"
                                    defaultValue={employee?.bank?.tax_number}
                                    error={errors.tax_number}
                                />
                                <TextField
                                    name="payment_method"
                                    label="Metode pembayaran"
                                    defaultValue={
                                        employee?.bank?.payment_method
                                    }
                                    error={errors.payment_method}
                                />
                            </Section>

                            <Section
                                title="Payroll"
                                description="Nilai dalam rupiah penuh, tanpa desimal."
                            >
                                <TextField
                                    name="pay_group"
                                    label="Pay group"
                                    defaultValue={employee?.payroll?.pay_group}
                                    error={errors.pay_group}
                                />
                                <TextField
                                    name="base_salary_idr"
                                    label="Gaji pokok (Rp)"
                                    type="number"
                                    min={0}
                                    step={1}
                                    defaultValue={
                                        employee?.payroll?.base_salary_idr
                                    }
                                    error={errors.base_salary_idr}
                                />
                                <TextField
                                    name="allowances_idr"
                                    label="Tunjangan (Rp)"
                                    type="number"
                                    min={0}
                                    step={1}
                                    defaultValue={
                                        employee?.payroll?.allowances_idr
                                    }
                                    error={errors.allowances_idr}
                                />
                                <TextField
                                    name="overtime_rate_idr"
                                    label="Tarif lembur (Rp)"
                                    type="number"
                                    min={0}
                                    step={1}
                                    defaultValue={
                                        employee?.payroll?.overtime_rate_idr
                                    }
                                    error={errors.overtime_rate_idr}
                                />
                            </Section>

                            <Section
                                title="Kesehatan & asuransi"
                                description="Data sensitif untuk polis kesehatan grup."
                            >
                                <TextField
                                    name="insurance_number"
                                    label="No. asuransi / BPJS"
                                    defaultValue={
                                        employee?.health?.insurance_number
                                    }
                                    error={errors.insurance_number}
                                />
                                <TextField
                                    name="insurance_tier"
                                    label="Tingkat polis"
                                    defaultValue={
                                        employee?.health?.insurance_tier
                                    }
                                    error={errors.insurance_tier}
                                />
                                <TextField
                                    name="blood_type"
                                    label="Golongan darah"
                                    defaultValue={employee?.health?.blood_type}
                                    error={errors.blood_type}
                                />
                                <TextField
                                    name="height_cm"
                                    label="Tinggi (cm)"
                                    type="number"
                                    min={0}
                                    defaultValue={employee?.health?.height_cm}
                                    error={errors.height_cm}
                                />
                                <TextField
                                    name="weight_kg"
                                    label="Berat (kg)"
                                    type="number"
                                    min={0}
                                    defaultValue={employee?.health?.weight_kg}
                                    error={errors.weight_kg}
                                />
                                <TextField
                                    name="last_medical_check_on"
                                    label="Medical check terakhir"
                                    type="date"
                                    defaultValue={
                                        employee?.health?.last_medical_check_on
                                    }
                                    error={errors.last_medical_check_on}
                                />
                                <TextField
                                    name="declared_conditions"
                                    label="Kondisi yang dilaporkan"
                                    defaultValue={
                                        employee?.health?.declared_conditions
                                    }
                                    error={errors.declared_conditions}
                                />
                            </Section>
                        </>
                    )}

                    <div>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
