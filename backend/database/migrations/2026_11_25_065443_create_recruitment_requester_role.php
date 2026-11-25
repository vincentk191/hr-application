<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The Pemohon Rekrutmen access, which an admin ticks on a Supervisor account so they may raise recruitment requests,
     * and the permission behind it. RoleSeeder would create both on a new installation; this gives an existing one the same,
     * once. After this it is managed from the admin panel like any other role.
     *
     * Supervisors who raised requisitions before this migration cannot start new ones until they are given the access.
     */
    private const ROLE = 'recruitment-requester';

    private const PERMISSION = 'request-recruitment';

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrCreate(self::ROLE);

        if ($role->wasRecentlyCreated) {
            $role->givePermissionTo(self::PERMISSION);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', self::ROLE)->where('guard_name', 'web')->first()?->delete();
        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->first()?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
