<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;

/**
 * Single source of truth for role-scoped navigation.
 *
 * Returning this from a View Composer keeps menu definitions out of Blade and
 * guarantees the sidebar, mobile dock and breadcrumbs never drift apart.
 */
class NavigationService
{
    public function forUser(?User $user): array
    {
        if (! $user) {
            return ['items' => [], 'dock' => []];
        }

        $pending = Registration::where('status', Registration::STATUS_PENDING)->count();
        $revisions = Registration::where('status', Registration::STATUS_REVISION)->count();

        /*
         | Visibility is driven by permission, not by role name, so an
         | Operator or Verifikator sees exactly the sections they can open
         | instead of a menu full of dead links. Server-side `can:` middleware
         | remains the actual enforcement.
         */
        if ($user->can('dashboard.admin.view')) {
            return ['items' => $this->staffItems($user, $pending), 'dock' => $this->staffDock($user, $pending)];
        }

        return ['items' => $this->siswaItems(), 'dock' => $this->siswaDock()];
    }

    /** Filter a list of items down to what this user may open. */
    private function visible(User $user, array $items): array
    {
        return array_values(array_filter($items, function (array $item) use ($user) {
            if (! empty($item['permission'])) {
                return $user->can($item['permission']);
            }

            if (! empty($item['children'])) {
                $children = array_values(array_filter($item['children'], fn ($c) => $user->can($c['permission'])));

                if ($children === []) {
                    return false;
                }

                $item['children'] = $children;

                return true;
            }

            return true;
        }));
    }

    private function staffItems(User $user, int $pending): array
    {
        return $this->visible($user, [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'permission' => 'dashboard.admin.view'],
            ['route' => 'admin.registrations', 'active' => 'admin.registrations*', 'icon' => 'clipboard-check', 'label' => 'Verifikasi', 'permission' => 'verification.view', 'badge' => $pending],

            ['label' => 'Data', 'icon' => 'database', 'children' => [
                ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'label' => 'Data Siswa', 'permission' => 'student.view'],
                ['route' => 'admin.users', 'active' => 'admin.users*', 'label' => 'Pengguna', 'permission' => 'user.view'],
                ['route' => 'admin.roles', 'active' => 'admin.roles*', 'label' => 'Role & Hak Akses', 'permission' => 'role.view'],
            ]],

            ['label' => 'Analitik & Laporan', 'icon' => 'chart-bar', 'children' => [
                ['route' => 'kesiswaan.statistics', 'active' => 'kesiswaan.statistics', 'label' => 'Statistik', 'permission' => 'student.view'],
                ['route' => 'kesiswaan.rekap', 'active' => 'kesiswaan.rekap', 'label' => 'Rekapitulasi', 'permission' => 'student.view'],
                ['route' => 'laporan.index', 'active' => 'laporan.*', 'label' => 'Buat Laporan', 'permission' => 'report.view'],
            ]],

            ['label' => 'Pengaturan', 'icon' => 'settings', 'children' => [
                ['route' => 'settings.index', 'active' => 'settings.*', 'label' => 'Profil Sekolah', 'permission' => 'school.view'],
                ['route' => 'settings.branding', 'active' => 'settings.branding', 'label' => 'Tampilan & Branding', 'permission' => 'branding.view'],
                ['route' => 'settings.registration', 'active' => 'settings.registration', 'label' => 'Pendaftaran', 'permission' => 'settings.view'],
                ['route' => 'admin.master', 'active' => 'admin.master*', 'label' => 'Master Data', 'permission' => 'master.view'],
            ]],

            ['route' => 'admin.activity', 'active' => 'admin.activity', 'icon' => 'history', 'label' => 'Log Aktivitas', 'permission' => 'activity.view'],
        ]);
    }

    private function staffDock(User $user, int $pending): array
    {
        return $this->visible($user, [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'short' => 'Beranda', 'permission' => 'dashboard.admin.view'],
            ['route' => 'admin.registrations', 'active' => 'admin.registrations*', 'icon' => 'clipboard-check', 'label' => 'Verifikasi', 'short' => 'Verifikasi', 'permission' => 'verification.view', 'badge' => $pending],
            ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'icon' => 'users', 'label' => 'Data Siswa', 'short' => 'Siswa', 'permission' => 'student.view'],
            ['route' => 'laporan.index', 'active' => 'laporan.*', 'icon' => 'chart-bar', 'label' => 'Laporan', 'short' => 'Laporan', 'permission' => 'report.view'],
            ['route' => 'settings.index', 'active' => 'settings.*', 'icon' => 'settings', 'label' => 'Pengaturan', 'short' => 'Setelan', 'permission' => 'settings.view'],
        ]);
    }

    private function adminItems(int $pending, int $revisions): array
    {
        return [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'admin.registrations', 'active' => 'admin.registrations', 'icon' => 'clipboard-check', 'label' => 'Verifikasi', 'badge' => $pending],
            ['label' => 'Data', 'icon' => 'database', 'children' => [
                ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'label' => 'Data Siswa'],
                ['route' => 'admin.users', 'active' => 'admin.users', 'label' => 'Data Pengguna'],
            ]],
            ['label' => 'Analitik & Laporan', 'icon' => 'chart-bar', 'children' => [
                ['route' => 'kesiswaan.statistics', 'active' => 'kesiswaan.statistics', 'label' => 'Statistik'],
                ['route' => 'kesiswaan.rekap', 'active' => 'kesiswaan.rekap', 'label' => 'Rekapitulasi'],
                ['route' => 'laporan.index', 'active' => 'laporan.*', 'label' => 'Buat Laporan'],
            ]],
            ['route' => 'admin.master', 'active' => 'admin.master*', 'icon' => 'settings', 'label' => 'Master Data'],
            ['route' => 'admin.activity', 'active' => 'admin.activity', 'icon' => 'history', 'label' => 'Log Aktivitas'],
        ];
    }

    private function adminDock(int $pending): array
    {
        return [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'short' => 'Beranda'],
            ['route' => 'admin.registrations', 'active' => 'admin.registrations', 'icon' => 'clipboard-check', 'label' => 'Verifikasi', 'short' => 'Verifikasi', 'badge' => $pending],
            ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'icon' => 'users', 'label' => 'Siswa', 'short' => 'Siswa'],
            ['route' => 'laporan.index', 'active' => 'laporan.*', 'icon' => 'chart-bar', 'label' => 'Laporan', 'short' => 'Laporan'],
            ['route' => 'admin.master', 'active' => 'admin.master*', 'icon' => 'settings', 'label' => 'Master', 'short' => 'Master'],
        ];
    }

    private function kesiswaanItems(): array
    {
        return [
            ['route' => 'kesiswaan.dashboard', 'active' => 'kesiswaan.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'icon' => 'users', 'label' => 'Data Siswa'],
            ['label' => 'Analitik', 'icon' => 'chart-bar', 'children' => [
                ['route' => 'kesiswaan.statistics', 'active' => 'kesiswaan.statistics', 'label' => 'Statistik'],
                ['route' => 'kesiswaan.rekap', 'active' => 'kesiswaan.rekap', 'label' => 'Rekapitulasi'],
            ]],
            ['route' => 'laporan.index', 'active' => 'laporan.*', 'icon' => 'file-text', 'label' => 'Laporan'],
        ];
    }

    private function kesiswaanDock(): array
    {
        return [
            ['route' => 'kesiswaan.dashboard', 'active' => 'kesiswaan.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'short' => 'Beranda'],
            ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'icon' => 'users', 'label' => 'Data Siswa', 'short' => 'Siswa'],
            ['route' => 'kesiswaan.statistics', 'active' => 'kesiswaan.statistics', 'icon' => 'chart', 'label' => 'Statistik', 'short' => 'Statistik'],
            ['route' => 'laporan.index', 'active' => 'laporan.*', 'icon' => 'file-text', 'label' => 'Laporan', 'short' => 'Laporan'],
        ];
    }

    private function siswaItems(): array
    {
        return [
            ['route' => 'siswa.dashboard', 'active' => 'siswa.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'siswa.wizard', 'active' => 'siswa.wizard', 'icon' => 'list-checks', 'label' => 'Data Pendaftaran'],
            ['route' => 'siswa.documents', 'active' => 'siswa.documents', 'icon' => 'files', 'label' => 'Dokumen'],
            ['route' => 'siswa.status', 'active' => 'siswa.status', 'icon' => 'history', 'label' => 'Status Verifikasi'],
        ];
    }

    private function siswaDock(): array
    {
        return [
            ['route' => 'siswa.dashboard', 'active' => 'siswa.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Beranda', 'short' => 'Beranda'],
            ['route' => 'siswa.wizard', 'active' => 'siswa.wizard', 'icon' => 'list-checks', 'label' => 'Pendaftaran', 'short' => 'Pendaftaran'],
            ['route' => 'siswa.documents', 'active' => 'siswa.documents', 'icon' => 'files', 'label' => 'Dokumen', 'short' => 'Dokumen'],
            ['route' => 'siswa.status', 'active' => 'siswa.status', 'icon' => 'history', 'label' => 'Status', 'short' => 'Status'],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil', 'short' => 'Profil'],
        ];
    }
}
