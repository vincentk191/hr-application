import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import InputError from '@/components/input-error';
import { PhotoInput } from '@/components/photo-input';
import { Button } from '@/components/ui/button';
import { RupiahInput } from '@/components/rupiah-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    EmployeeDetail,
    ManagerOption,
    Option,
    ValueOption,
} from '@/types/employee';
import type { HireDefaults } from '@/types/recruitment';

export type EmployeeFormOptions = {
    /** Set only while every company is being viewed: a new employee then needs one chosen. */
    companies: Option[] | null;
    branches: (Option & { company_id: number })[];
    departments: (Option & { company_id: number })[];
    positions: (Option & { department_id: number; company_id: number })[];
    managers: (ManagerOption & { company_id: number })[];
    employmentStatuses: ValueOption[];
    employmentTypes: ValueOption[];
    zonas: ValueOption[];
    genders: ValueOption[];
    religions: ValueOption[];
    maritalStatuses: ValueOption[];
    ptkpStatuses: ValueOption[];
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

/** An amount in rupiah, written with dots between thousands as it is typed. */
function RupiahField({
    name,
    label,
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    defaultValue?: number | string | null;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <RupiahInput id={name} name={name} defaultValue={defaultValue} />
            <InputError message={error} />
        </div>
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

function SelectField({
    name,
    label,
    options,
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    options: ValueOption[];
    defaultValue?: string | null;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <select
                id={name}
                name={name}
                defaultValue={defaultValue ?? ''}
                className={selectClasses}
            >
                <option value="">—</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
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
    hire,
    canEditSensitive,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put';
    options: EmployeeFormOptions;
    employee?: EmployeeDetail;
    /** A hire becoming an employee: the fields it knows come filled in, in the company of its requisition. */
    hire?: HireDefaults;
    /** Bank, payroll and health are left out of the form for anyone who may not handle them. */
    canEditSensitive: boolean;
    submitLabel: string;
}) {
    const [companyId, setCompanyId] = useState('');
    // Viewing every company at once, a new employee has no company yet: it is chosen first, and the
    // Cabang, departments, positions and managers on offer are that company's. A hire's company is its requisition's.
    const choosesCompany =
        options.companies !== null &&
        employee === undefined &&
        hire === undefined;
    const inChosenCompany = <T extends { company_id: number }>(items: T[]) =>
        hire !== undefined
            ? items.filter(
                  (item) => String(item.company_id) === String(hire.companyId),
              )
            : choosesCompany
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
                            defaultValue={employee?.name ?? hire?.name}
                            error={errors.full_name}
                            required
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="branch_id">Cabang</Label>
                            <select
                                id="branch_id"
                                name="branch_id"
                                defaultValue={
                                    employee?.branchId ?? hire?.branchId ?? ''
                                }
                                className={selectClasses}
                                required
                            >
                                <option value="">Pilih cabang</option>
                                {inChosenCompany(options.branches).map(
                                    (branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={errors.branch_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="department_id">Departemen</Label>
                            <select
                                id="department_id"
                                name="department_id"
                                defaultValue={
                                    employee?.departmentId ??
                                    hire?.departmentId ??
                                    ''
                                }
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
                            <Label htmlFor="position_id">Posisi</Label>
                            <select
                                id="position_id"
                                name="position_id"
                                defaultValue={
                                    employee?.positionId ??
                                    hire?.positionId ??
                                    ''
                                }
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

                        <TextField
                            name="job_title"
                            label="Jabatan"
                            defaultValue={
                                employee?.personal.job_title ?? hire?.jobTitle
                            }
                            error={errors.job_title}
                            placeholder="Mis. Staff, Supervisor, Manager"
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="manager_id">Atasan langsung</Label>
                            <select
                                id="manager_id"
                                name="manager_id"
                                defaultValue={
                                    employee?.managerId ?? hire?.managerId ?? ''
                                }
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
                                defaultValue={
                                    employee?.employmentType ??
                                    hire?.employmentType ??
                                    ''
                                }
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

                        <div className="grid gap-2">
                            <Label htmlFor="zona">Zona</Label>
                            <select
                                id="zona"
                                name="zona"
                                defaultValue={employee?.zona ?? 'hijau'}
                                className={selectClasses}
                            >
                                {options.zonas.map((zona) => (
                                    <option key={zona.value} value={zona.value}>
                                        {zona.label}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                Menentukan besar potongan absensi. Hijau bila
                                tidak diubah.
                            </p>
                            <InputError message={errors.zona} />
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
                            defaultValue={employee?.joinedOn ?? hire?.joinedOn}
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

                    <Section title="Data pribadi">
                        <SelectField
                            name="gender"
                            label="Jenis kelamin"
                            options={options.genders}
                            defaultValue={employee?.personal.gender}
                            error={errors.gender}
                        />
                        <SelectField
                            name="religion"
                            label="Agama"
                            options={options.religions}
                            defaultValue={employee?.personal.religion}
                            error={errors.religion}
                        />
                        <TextField
                            name="birth_place"
                            label="Tempat lahir"
                            defaultValue={employee?.personal.birth_place}
                            error={errors.birth_place}
                        />
                        <TextField
                            name="birth_date"
                            label="Tanggal lahir"
                            type="date"
                            defaultValue={employee?.personal.birth_date}
                            error={errors.birth_date}
                        />
                        <SelectField
                            name="marital_status"
                            label="Status perkawinan"
                            options={options.maritalStatuses}
                            defaultValue={employee?.personal.marital_status}
                            error={errors.marital_status}
                        />
                        <TextField
                            name="education_level"
                            label="Pendidikan terakhir"
                            defaultValue={employee?.personal.education_level}
                            error={errors.education_level}
                            placeholder="Mis. SMA, D3, S1"
                        />
                    </Section>

                    <Section title="Kontak & kontak darurat">
                        <TextField
                            name="email"
                            label="Email"
                            type="email"
                            defaultValue={
                                employee?.contact.email ?? hire?.email
                            }
                            error={errors.email}
                        />
                        <TextField
                            name="phone"
                            label="Telepon"
                            defaultValue={
                                employee?.contact.phone ?? hire?.phone
                            }
                            error={errors.phone}
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

                    <Section title="Alamat tinggal">
                        <div className="sm:col-span-2">
                            <TextField
                                name="address"
                                label="Alamat (jalan, nomor)"
                                defaultValue={employee?.contact.address}
                                error={errors.address}
                            />
                        </div>
                        <TextField
                            name="residence_village"
                            label="Kelurahan"
                            defaultValue={employee?.residence.residence_village}
                            error={errors.residence_village}
                        />
                        <TextField
                            name="residence_district"
                            label="Kecamatan"
                            defaultValue={
                                employee?.residence.residence_district
                            }
                            error={errors.residence_district}
                        />
                        <TextField
                            name="residence_city"
                            label="Kabupaten / kota"
                            defaultValue={employee?.residence.residence_city}
                            error={errors.residence_city}
                        />
                        <TextField
                            name="residence_province"
                            label="Provinsi"
                            defaultValue={
                                employee?.residence.residence_province
                            }
                            error={errors.residence_province}
                        />
                        <TextField
                            name="residence_postal_code"
                            label="Kode pos"
                            defaultValue={
                                employee?.residence.residence_postal_code
                            }
                            error={errors.residence_postal_code}
                            inputMode="numeric"
                            maxLength={10}
                        />
                    </Section>

                    <Section
                        title="Alamat KTP"
                        description="Sesuai yang tertulis di KTP."
                    >
                        <div className="sm:col-span-2">
                            <TextField
                                name="id_address"
                                label="Alamat"
                                defaultValue={employee?.idAddress.id_address}
                                error={errors.id_address}
                            />
                        </div>
                        <TextField
                            name="id_block"
                            label="Blok"
                            defaultValue={employee?.idAddress.id_block}
                            error={errors.id_block}
                        />
                        <TextField
                            name="id_house_number"
                            label="No. rumah"
                            defaultValue={employee?.idAddress.id_house_number}
                            error={errors.id_house_number}
                        />
                        <TextField
                            name="id_rt"
                            label="RT"
                            defaultValue={employee?.idAddress.id_rt}
                            error={errors.id_rt}
                            maxLength={10}
                        />
                        <TextField
                            name="id_rw"
                            label="RW"
                            defaultValue={employee?.idAddress.id_rw}
                            error={errors.id_rw}
                            maxLength={10}
                        />
                        <TextField
                            name="id_village"
                            label="Kelurahan"
                            defaultValue={employee?.idAddress.id_village}
                            error={errors.id_village}
                        />
                        <TextField
                            name="id_district"
                            label="Kecamatan"
                            defaultValue={employee?.idAddress.id_district}
                            error={errors.id_district}
                        />
                        <TextField
                            name="id_city"
                            label="Kabupaten / kota"
                            defaultValue={employee?.idAddress.id_city}
                            error={errors.id_city}
                        />
                        <TextField
                            name="id_province"
                            label="Provinsi"
                            defaultValue={employee?.idAddress.id_province}
                            error={errors.id_province}
                        />
                        <TextField
                            name="id_postal_code"
                            label="Kode pos"
                            defaultValue={employee?.idAddress.id_postal_code}
                            error={errors.id_postal_code}
                            inputMode="numeric"
                            maxLength={10}
                        />
                    </Section>

                    {canEditSensitive && (
                        <>
                            <Section title="Identitas keluarga">
                                <TextField
                                    name="family_card_number"
                                    label="No. KK (16 digit)"
                                    defaultValue={
                                        employee?.identity?.family_card_number
                                    }
                                    error={errors.family_card_number}
                                    inputMode="numeric"
                                    maxLength={16}
                                />
                                <TextField
                                    name="mother_name"
                                    label="Nama ibu kandung"
                                    defaultValue={
                                        employee?.identity?.mother_name
                                    }
                                    error={errors.mother_name}
                                />
                            </Section>

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
                                    name="bank_branch"
                                    label="Cabang bank"
                                    defaultValue={employee?.bank?.bank_branch}
                                    error={errors.bank_branch}
                                />
                                <TextField
                                    name="tax_number"
                                    label="NPWP"
                                    defaultValue={employee?.bank?.tax_number}
                                    error={errors.tax_number}
                                />
                                <SelectField
                                    name="ptkp_status"
                                    label="Status PTKP"
                                    options={options.ptkpStatuses}
                                    defaultValue={employee?.bank?.ptkp_status}
                                    error={errors.ptkp_status}
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
                                title="Kesehatan & asuransi"
                                description="Data lainnya dapat dilihat di situs BPJS."
                            >
                                <TextField
                                    name="bpjs_employment_number"
                                    label="No. BPJS Ketenagakerjaan"
                                    defaultValue={
                                        employee?.coverage
                                            ?.bpjs_employment_number
                                    }
                                    error={errors.bpjs_employment_number}
                                />
                                <TextField
                                    name="bpjs_health_number"
                                    label="No. BPJS Kesehatan"
                                    defaultValue={
                                        employee?.coverage?.bpjs_health_number
                                    }
                                    error={errors.bpjs_health_number}
                                />
                            </Section>

                            <Section
                                title="Penggajian"
                                description="Nilai dalam rupiah penuh, tanpa desimal."
                            >
                                <TextField
                                    name="pay_group"
                                    label="Kelompok gaji"
                                    defaultValue={employee?.payroll?.pay_group}
                                    error={errors.pay_group}
                                />
                                <RupiahField
                                    name="base_salary_idr"
                                    label="Gaji pokok (Rp)"
                                    defaultValue={
                                        employee?.payroll?.base_salary_idr ??
                                        hire?.baseSalary
                                    }
                                    error={errors.base_salary_idr}
                                />
                                <RupiahField
                                    name="allowances_idr"
                                    label="Tunjangan (Rp)"
                                    defaultValue={
                                        employee?.payroll?.allowances_idr
                                    }
                                    error={errors.allowances_idr}
                                />
                                <RupiahField
                                    name="overtime_rate_idr"
                                    label="Tarif lembur (Rp)"
                                    defaultValue={
                                        employee?.payroll?.overtime_rate_idr
                                    }
                                    error={errors.overtime_rate_idr}
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
