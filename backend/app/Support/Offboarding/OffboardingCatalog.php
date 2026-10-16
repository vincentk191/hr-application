<?php

namespace App\Support\Offboarding;

use App\Enums\OffboardingItemKind;
use App\Models\Department;
use App\Models\OffboardingItem;

/**
 * The master list of offboarding items, with sensible defaults for a company that has none yet
 * (OFF-TPL-001). HR can change everything afterwards from the admin panel, and the defaults are never
 * put back over their edits: they are only made when the company has no items at all.
 *
 * Each default names the kind of department it belongs to. A company's own department with a matching
 * name takes it; with no match the item has no department, and HR Admin owns it.
 */
class OffboardingCatalog
{
    /**
     * @var array<string, list<string>>
     */
    private const DEPARTMENT_NAMES = [
        'it' => ['it', 'ti', 'teknologi informasi', 'information technology'],
        'hr' => ['hr', 'hrd', 'sdm', 'human resources'],
        'facilities' => ['fasilitas', 'umum', 'general affairs', 'ga'],
        'finance' => ['keuangan', 'finance', 'akuntansi'],
    ];

    /**
     * @var list<array{name: string, kind: OffboardingItemKind, department: string|null}>
     */
    public const DEFAULTS = [
        ['name' => 'Laptop dan perangkat kerja dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'it'],
        ['name' => 'Akun email perusahaan dinonaktifkan', 'kind' => OffboardingItemKind::Digital, 'department' => 'it'],
        ['name' => 'Akses VPN dicabut', 'kind' => OffboardingItemKind::Digital, 'department' => 'it'],
        ['name' => 'Akun aplikasi kerja lain dinonaktifkan', 'kind' => OffboardingItemKind::Digital, 'department' => 'it'],
        ['name' => 'Kartu identitas karyawan dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'hr'],
        ['name' => 'Pendaftaran sidik jari di pemindai absensi dihapus', 'kind' => OffboardingItemKind::Digital, 'department' => 'hr'],
        ['name' => 'Wawancara keluar dilakukan', 'kind' => OffboardingItemKind::Process, 'department' => 'hr'],
        ['name' => 'Kunci, kartu akses, dan loker dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'facilities'],
        ['name' => 'Seragam dan alat pelindung diri dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'facilities'],
        ['name' => 'Kendaraan operasional diserahkan', 'kind' => OffboardingItemKind::Physical, 'department' => 'facilities'],
        ['name' => 'Pinjaman atau kasbon diselesaikan', 'kind' => OffboardingItemKind::Process, 'department' => 'finance'],
        ['name' => 'Serah terima pekerjaan dan pengetahuan', 'kind' => OffboardingItemKind::Process, 'department' => null],
    ];

    /**
     * Make the default items for a company that has none. Safe to call every time.
     */
    public function ensureDefaults(int $companyId): void
    {
        if (OffboardingItem::acrossCompanies()->where('company_id', $companyId)->exists()) {
            return;
        }

        $departments = Department::acrossCompanies()->where('company_id', $companyId)->get(['id', 'name']);

        foreach (self::DEFAULTS as $position => $default) {
            OffboardingItem::acrossCompanies()->create([
                'company_id' => $companyId,
                'name' => $default['name'],
                'kind' => $default['kind'],
                'department_id' => $default['department'] === null ? null : $this->departmentFor($default['department'], $departments),
                'is_active' => true,
                'sort_order' => ($position + 1) * 10,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Department>  $departments
     */
    private function departmentFor(string $key, $departments): ?int
    {
        $names = self::DEPARTMENT_NAMES[$key] ?? [];

        return $departments->first(fn (Department $department): bool => in_array(mb_strtolower(trim($department->name)), $names, true))?->id;
    }
}
