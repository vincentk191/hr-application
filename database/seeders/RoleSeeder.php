<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the roles and permissions of context.md §9.2 / CORE-RBA-001.
 *
 * Idempotent, and the single source of truth for who holds what: the mapping lives on the
 * Role enum, so adding a permission to a role is a code change that this seeder applies
 * rather than something adjusted by hand in the database.
 */
class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        // The flush has to sit between the two loops: creating the permissions populates
        // spatie's cache, and syncPermissions below resolves names through that cache. Without
        // this, a freshly created permission is "not found" in the same run.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            $model = Role::findOrCreate($role->value);

            // syncPermissions, not givePermissionTo: a permission removed from the enum is
            // removed from the role on the next run.
            $model->syncPermissions(
                array_map(fn (PermissionEnum $permission): string => $permission->value, $role->permissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
