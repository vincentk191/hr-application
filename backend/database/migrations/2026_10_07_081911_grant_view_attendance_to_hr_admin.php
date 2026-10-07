<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The permission the attendance calendar introduced, granted once to an existing HR Admin
     * role. RoleSeeder only gives defaults to roles it creates, so without this an existing
     * installation would never receive it; a fresh database is seeded with it already. After
     * this it is managed from the admin panel like any other permission.
     */
    private const PERMISSION = 'view-attendance';

    public function up(): void
    {
        $role = Role::query()->where('name', 'hr-admin')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        Permission::findOrCreate(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->givePermissionTo(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'hr-admin')->where('guard_name', 'web')->first()
            ?->revokePermissionTo(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
