<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The permissions the documents feature introduced.
     *
     * RoleSeeder only gives defaults to roles it creates, so an existing installation's HR Admin
     * role would otherwise never receive them. This grants them once, and only if the role
     * exists: a fresh database is seeded with them already. After this they are managed from the
     * admin panel like any other permission, and this migration never runs again.
     */
    private const PERMISSIONS = ['view-documents', 'manage-documents', 'view-sensitive-data'];

    public function up(): void
    {
        $role = Role::query()->where('name', 'hr-admin')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'hr-admin')->where('guard_name', 'web')->first();

        $role?->revokePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
