<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Roles/permissions first: UserSeeder attaches accounts to them.
            PermissionSeeder::class,
            MasterDataSeeder::class,
            SettingsSeeder::class,
            UserSeeder::class,
            StudentSeeder::class,
        ]);
    }
}
