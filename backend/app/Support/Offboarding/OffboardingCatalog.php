<?php

namespace App\Support\Offboarding;

use App\Enums\OffboardingItemKind;
use App\Models\OffboardingItem;

/**
 * The master list of offboarding items, with sensible defaults when it is empty (OFF-TPL-001). The list
 * is the group's, shared by every company. HR can change everything afterwards from the admin panel, and
 * the defaults are never put back over their edits: they are only made when there are no items at all.
 *
 * A default names the department whose supervisor owns it (matched by name in the leaver's company) and
 * names no person; HR can give any item a named owner.
 */
class OffboardingCatalog
{
    /**
     * @var list<array{name: string, kind: OffboardingItemKind, department: string|null}>
     */
    public const DEFAULTS = [
        ['name' => 'Laptop dan perangkat kerja dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'IT'],
        ['name' => 'Akun email perusahaan dinonaktifkan', 'kind' => OffboardingItemKind::Digital, 'department' => 'IT'],
        ['name' => 'Akses VPN dicabut', 'kind' => OffboardingItemKind::Digital, 'department' => 'IT'],
        ['name' => 'Akun aplikasi kerja lain dinonaktifkan', 'kind' => OffboardingItemKind::Digital, 'department' => 'IT'],
        ['name' => 'Kartu identitas karyawan dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'HR'],
        ['name' => 'Pendaftaran sidik jari di pemindai absensi dihapus', 'kind' => OffboardingItemKind::Digital, 'department' => 'HR'],
        ['name' => 'Wawancara keluar dilakukan', 'kind' => OffboardingItemKind::Process, 'department' => 'HR'],
        ['name' => 'Kunci, kartu akses, dan loker dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'Fasilitas'],
        ['name' => 'Seragam dan alat pelindung diri dikembalikan', 'kind' => OffboardingItemKind::Physical, 'department' => 'Fasilitas'],
        ['name' => 'Kendaraan operasional diserahkan', 'kind' => OffboardingItemKind::Physical, 'department' => 'Fasilitas'],
        ['name' => 'Pinjaman atau kasbon diselesaikan', 'kind' => OffboardingItemKind::Process, 'department' => 'Keuangan'],
        ['name' => 'Serah terima pekerjaan dan pengetahuan', 'kind' => OffboardingItemKind::Process, 'department' => null],
    ];

    /**
     * Make the default items if there are none. Safe to call every time.
     */
    public function ensureDefaults(): void
    {
        if (OffboardingItem::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $position => $default) {
            OffboardingItem::query()->create([
                'name' => $default['name'],
                'kind' => $default['kind'],
                'department_name' => $default['department'],
                'is_active' => true,
                'sort_order' => ($position + 1) * 10,
            ]);
        }
    }
}
