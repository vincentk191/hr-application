export type EmployeeRow = {
    id: number;
    number: string;
    name: string;
    department: string | null;
    position: string | null;
    manager: string | null;
    managerId: number | null;
    status: string;
    isActive: boolean;
    /** Warehouse staff are data-only records with no login (CORE-RBA-006). */
    hasLogin: boolean;
    joinedOn: string | null;
};

/** Money is integer rupiah throughout — never a float. */
export type EmployeePayroll = {
    pay_group: string | null;
    base_salary_idr: number | null;
    allowances_idr: number | null;
    overtime_rate_idr: number | null;
};

export type EmployeeDetail = EmployeeRow & {
    companyCode: string | null;
    departmentId: number | null;
    positionId: number | null;
    employmentStatus: string;
    employmentType: string | null;
    exitedOn: string | null;
    contact: {
        email: string | null;
        phone: string | null;
        address: string | null;
        national_id: string | null;
        emergency_contact_name: string | null;
        emergency_contact_phone: string | null;
        emergency_contact_relation: string | null;
    };
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_holder: string | null;
        tax_number: string | null;
        payment_method: string | null;
    };
    payroll: EmployeePayroll;
    health: {
        insurance_number: string | null;
        insurance_tier: string | null;
        blood_type: string | null;
        height_cm: number | null;
        weight_kg: number | null;
        declared_conditions: string | null;
        last_medical_check_on: string | null;
    };
};

export type EmployeeFilters = {
    search: string;
    departments: number[];
    positions: number[];
    statuses: string[];
    sort: 'name' | 'joined';
    view: 'list' | 'grid' | 'chart';
};

export type Option = { id: number; name: string };
export type ValueOption = { value: string; label: string };
export type ManagerOption = { id: number; label: string };
