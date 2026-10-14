<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Seeing employees outside one's own reporting line became a permission of its own
     * (CORE-RBA-008): until now `view-employees` showed a Supervisor or Management the whole
     * company. Granted once to the roles that had that reach, so nobody loses a view they relied
     * on. RoleSeeder only gives defaults to roles it creates, so without this an existing
     * installation would never receive it. Supervisor is deliberately left out: that is the
     * change. After this it is managed from the admin panel like any other permission.
     */
    private const PERMISSION = 'view-all-employees';

    private const ROLES = ['system-admin', 'hr-admin', 'management'];

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->whereIn('name', self::ROLES)->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo(self::PERMISSION));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->whereIn('name', self::ROLES)->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->revokePermissionTo(self::PERMISSION));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
