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
 * Idempotent, and it only ever creates what is missing. The mapping on the Role enum is the
 * default a role starts with; once a role exists, its permissions belong to the database and
 * are managed from the admin panel. Re-running this seeder therefore never undoes a change an
 * administrator made. The cost: a permission later added to a role's enum default reaches new
 * installs only, and has to be granted to existing roles from the panel.
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

            if (! $model->wasRecentlyCreated) {
                continue;
            }

            $model->syncPermissions(
                array_map(fn (PermissionEnum $permission): string => $permission->value, $role->permissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
