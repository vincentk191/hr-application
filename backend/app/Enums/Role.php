<?php

namespace App\Enums;

/**
 * The application's RBAC roles (context.md §9.2).
 *
 * Management → Supervisor → Employee is the line hierarchy; HR and System Admin sit outside
 * it. These are permission roles, distinct from `App\Models\Position`, which is the job title
 * an employee holds in the org chart.
 *
 * Item-owner roles used by offboarding checklists (IT, Facilities) are deliberately not here
 * yet — they arrive with that module, which decides whether they are RBAC roles or just
 * owner labels on a checklist item.
 */
enum Role: string
{
    case SystemAdmin = 'system-admin';
    case HrAdmin = 'hr-admin';
    case Management = 'management';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Admin Sistem',
            self::HrAdmin => 'Admin HR',
            self::Management => 'Manajemen',
            self::Supervisor => 'Supervisor',
            self::Employee => 'Karyawan',
        };
    }

    /**
     * The permissions this role holds (CORE-RBA-002).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Owns accounts, roles and the company list; not HR process work.
            self::SystemAdmin => [
                Permission::ManageCompanies,
                Permission::ManageUsers,
                Permission::ManageOrganisation,
                Permission::ViewEmployees,
                Permission::ViewAllEmployees,
                Permission::AccessAdminPanel,
                Permission::ManageBackups,
            ],
            // Owns the cross-company HR processes and the dossier.
            self::HrAdmin => [
                Permission::ManageOrganisation,
                Permission::ViewEmployees,
                Permission::ViewAllEmployees,
                Permission::CreateEmployees,
                Permission::UpdateEmployees,
                Permission::AccessAdminPanel,
                Permission::ViewDocuments,
                Permission::ManageDocuments,
                Permission::ViewSensitiveData,
                Permission::ManageAttendance,
                Permission::ViewAttendance,
                Permission::CorrectAttendance,
                Permission::ManageCalendarEvents,
                Permission::ManageLeaveSettings,
                Permission::ManageOffboarding,
            ],
            // Sees its scope and decides; does not administer records.
            self::Management => [
                Permission::ViewEmployees,
                Permission::ViewAllEmployees,
            ],
            self::Supervisor => [
                Permission::ViewEmployees,
            ],
            // An employee sees their own record, which policies handle rather than a
            // blanket view permission.
            self::Employee => [],
        };
    }
}
