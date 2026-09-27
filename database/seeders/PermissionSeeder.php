<?php

namespace Database\Seeders;

use App\Services\PermissionCatalog;
use App\Services\RoleSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the roles and permissions described by PermissionCatalog.
 *
 * Runs before UserSeeder so accounts attach to the new role set, and before
 * StudentSeeder so it can grant student-facing permissions.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(RoleSeeder::class)->run();

        $this->command?->info(sprintf(
            '  %d permissions, %d roles.',
            count(PermissionCatalog::names()),
            count(PermissionCatalog::roleGrants()) + 1 // + super_admin
        ));
    }
}
