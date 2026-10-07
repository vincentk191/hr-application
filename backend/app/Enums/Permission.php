<?php

namespace App\Enums;

/**
 * Named permissions checked in policies and route middleware (CORE-RBA-002/003).
 *
 * Kept deliberately coarse: one permission per administrative capability that exists today.
 * Finer grain arrives with the modules that need it rather than being invented up front.
 */
enum Permission: string
{
    case ManageCompanies = 'manage-companies';
    case ManageUsers = 'manage-users';
    case ManageOrganisation = 'manage-organisation';
    case ViewEmployees = 'view-employees';
    case CreateEmployees = 'create-employees';
    case UpdateEmployees = 'update-employees';
    case AccessAdminPanel = 'access-admin-panel';
    case ViewDocuments = 'view-documents';
    case ManageDocuments = 'manage-documents';
    case ViewSensitiveData = 'view-sensitive-data';
    case ManageAttendance = 'manage-attendance';
    case ViewAttendance = 'view-attendance';
    case CorrectAttendance = 'correct-attendance';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageCompanies => 'Kelola perusahaan',
            self::ManageUsers => 'Kelola pengguna dan peran',
            self::ManageOrganisation => 'Kelola departemen dan jabatan',
            self::ViewEmployees => 'Lihat data karyawan',
            self::CreateEmployees => 'Tambah karyawan',
            self::UpdateEmployees => 'Ubah data karyawan',
            self::AccessAdminPanel => 'Akses panel admin',
            self::ViewDocuments => 'Lihat dokumen karyawan',
            self::ManageDocuments => 'Kelola dokumen karyawan',
            self::ViewSensitiveData => 'Lihat data sensitif (dokumen terbatas, bank, gaji, kesehatan)',
            self::ManageAttendance => 'Kelola data absensi (impor log, pemetaan ID pemindai)',
            self::ViewAttendance => 'Lihat kalender absensi',
            self::CorrectAttendance => 'Koreksi data absensi (tambah / batalkan scan)',
        };
    }
}
