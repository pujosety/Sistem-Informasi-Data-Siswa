<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $accounts = [
            // The FIRST admin account is the Super Admin. This is an explicit,
            // designated promotion — existing admins are never silently
            // escalated by this seeder.
            ['name' => 'Admin Sekolah',   'email' => 'admin@siswa.test',     'role' => 'super_admin'],
            ['name' => 'Kesiswaan',       'email' => 'kesiswaan@siswa.test', 'role' => 'kesiswaan'],
            ['name' => 'Operator',        'email' => 'operator@siswa.test',   'role' => 'operator'],
            ['name' => 'Verifikator',     'email' => 'verifikator@siswa.test','role' => 'verifikator'],
            ['name' => 'Budi Santoso',    'email' => 'siswa@siswa.test',     'role' => 'siswa'],
            ['name' => 'Andi Pratama',    'email' => 'andi@siswa.test',      'role' => 'siswa'],
            ['name' => 'Siti Nurhaliza',  'email' => 'siti@siswa.test',      'role' => 'siswa'],
            ['name' => 'Rizky Hidayat',   'email' => 'rizky@siswa.test',     'role' => 'siswa'],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            $user->syncRoles([$account['role']]);
        }
    }
}
