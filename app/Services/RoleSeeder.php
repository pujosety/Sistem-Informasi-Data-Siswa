<?php

namespace App\Services;

use App\Models\ActivityLog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the permission catalogue and the default role set.
 *
 * Safe to re-run: permissions are firstOrCreate, and each role's permission
 * set is SYNCED to the catalogue defaults so a deployment that added a new
 * permission picks it up without manual SQL.
 */
class RoleSeeder
{
    public function __construct(private readonly AuditService $audit) {}

    public function run(): void
    {
        foreach (PermissionCatalog::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $grants = PermissionCatalog::roleGrants();

        foreach ($grants as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            // sync() rather than givePermissionsTo(): a permission removed
            // from the catalogue must also be removed from the role.
            $role->syncPermissions($permissions);
        }

        // Super Admin implicitly holds everything. Using a Gate::before means
        // a permission added tomorrow is honoured immediately, with no sync.
        Role::findOrCreate('super_admin', 'web');
    }
}
