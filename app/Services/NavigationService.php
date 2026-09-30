<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;

/**
 * Single source of truth for role-scoped navigation.
 *
 * Returning this from a View Composer keeps menu definitions out of Blade and
 * guarantees the sidebar, mobile dock and breadcrumbs never drift apart.
 *
 * Two rules shape everything here:
 *
 *  1. Visibility is driven by PERMISSION, never by role name. A menu item the
 *     user cannot open is dead weight, so it is simply not built.
 *  2. The mobile dock is ROLE-SPECIFIC and capped at 5 destinations. Anything
 *     beyond that goes to the "more" sheet, because a 9-icon strip is unusable
 *     on a 360px phone.
 *
 * The two are separate concerns: the sidebar may be long, the dock may not.
 */
class NavigationService
{
    public function forUser(?User $user): array
    {
        if (! $user) {
            return ['items' => [], 'dock' => [], 'more' => []];
        }

        $items = $this->sidebar($user);

        return [
            'items' => $items,
            'dock' => $this->dock($user),
            // Everything reachable but not worth a dock slot.
            'more' => $this->more($user, $items),
        ];
    }

    /** Filter a list of items down to what this user may open. */
    private function visible(User $user, array $items): array
    {
        $out = [];

        $modules = app(ModuleService::class);

        foreach ($items as $item) {
            // A disabled module hides the item outright. Checked before
            // permission so an operator switching a module off gets it gone
            // regardless of who is looking.
            if (! $modules->allows($item['module'] ?? null)) {
                continue;
            }

            if (! empty($item['children'])) {
                $children = array_values(array_filter(
                    $item['children'],
                    // Two independent gates: the module must be on, and this
                    // user must hold the permission. Either can remove a leaf.
                    fn ($c) => $modules->allows($c['module'] ?? null)
                        && (empty($c['permission']) || $user->can($c['permission'])),
                ));

                // A group with nothing openable inside it is noise.
                if ($children === []) {
                    continue;
                }

                $item['children'] = $children;
                $out[] = $item;

                continue;
            }

            if (! empty($item['permission']) && ! $user->can($item['permission'])) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    // ------------------------------------------------------------------ sidebar

    private function sidebar(User $user): array
    {
        if ($user->isStudent()) {
            return $this->visible($user, $this->siswaItems());
        }

        if ($this->isParent($user)) {
            return $this->visible($user, $this->parentItems());
        }

        $items = $this->visible($user, $this->staffItems($user));

        // Kelas Saya is a CONTEXTUAL workspace: it appears because of an
        // assignment, not because of a role.
        if ($this->homeroomsAClass($user)) {
            array_splice($items, 1, 0, [[
                'route' => 'academic.homeroom.index',
                'active' => 'academic.homeroom.*',
                'icon' => 'users-round',
                'label' => 'Kelas Saya',
                'badge' => $this->homeroomCount($user),
            ]]);
        }

        return $items;
    }

    private function staffItems(User $user): array
    {
        $pending = Registration::where('status', Registration::STATUS_PENDING)->count();

        return [
            ['route' => 'workspace.admin', 'active' => 'workspace.admin', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'permission' => 'dashboard.admin.view'],

            ['label' => 'PPDB', 'module' => 'ppdb', 'icon' => 'clipboard-check', 'children' => [
                ['route' => 'admin.registrations', 'active' => 'admin.registrations', 'label' => 'Verifikasi', 'permission' => 'verification.view', 'badge' => $pending, 'module' => 'ppdb'],
                ['route' => 'admin.master', 'active' => 'admin.master*', 'label' => 'Master Data', 'permission' => 'master.view'],
            ]],

            ['label' => 'Akademik', 'module' => 'academic', 'icon' => 'graduation-cap', 'children' => [
                ['route' => 'academic.years.index', 'active' => 'academic.years.*', 'label' => 'Tahun Ajaran', 'permission' => 'academic_year.view', 'module' => 'academic'],
                ['route' => 'academic.classes.index', 'active' => 'academic.classes*', 'label' => 'Kelas', 'permission' => 'classroom.view', 'module' => 'academic'],
                ['route' => 'academic.enrollments.create', 'active' => 'academic.enrollments*', 'label' => 'Penempatan Siswa', 'permission' => 'enrollment.view', 'module' => 'academic'],
            ]],

            ['label' => 'Data', 'icon' => 'database', 'children' => [
                ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'label' => 'Data Siswa', 'permission' => 'student.view', 'module' => 'students'],
                ['route' => 'admin.users', 'active' => 'admin.users*', 'label' => 'Pengguna', 'permission' => 'user.view'],
                // HRIS. Module-gated AND permission-gated: the registry seeds
                // `hris` DISABLED, because Phase 3 built the table with no UI.
                // Until an operator switches it on the section is hidden
                // everywhere at once — sidebar, dock, overflow — so there is
                // no link to a screen that 403s. Both gates must be removed
                // for one, which is why `module` is repeated on the child.
                ['route' => 'admin.employees', 'active' => 'admin.employees*', 'label' => 'Kepegawaian', 'permission' => 'employee.view', 'module' => 'hris'],
                ['route' => 'admin.roles', 'active' => 'admin.roles*', 'label' => 'Role & Hak Akses', 'permission' => 'role.view'],
            ]],

            ['label' => 'Laporan', 'icon' => 'chart-bar', 'children' => [
                ['route' => 'kesiswaan.statistics', 'active' => 'kesiswaan.statistics', 'label' => 'Statistik', 'permission' => 'student.view'],
                ['route' => 'kesiswaan.rekap', 'active' => 'kesiswaan.rekap', 'label' => 'Rekapitulasi', 'permission' => 'student.view'],
                ['route' => 'laporan.index', 'active' => 'laporan.*', 'label' => 'Buat Laporan', 'permission' => 'report.view'],
            ]],

            ['label' => 'Pengaturan', 'icon' => 'settings', 'children' => [
                ['route' => 'settings.index', 'active' => 'settings.index', 'label' => 'Profil Sekolah', 'permission' => 'school.view'],
                ['route' => 'settings.branding', 'active' => 'settings.branding', 'label' => 'Tampilan & Branding', 'permission' => 'branding.view'],
                ['route' => 'settings.registration', 'active' => 'settings.registration', 'label' => 'Pendaftaran', 'permission' => 'settings.view'],
            ]],

            ['route' => 'admin.activity', 'active' => 'admin.activity', 'icon' => 'history', 'label' => 'Log Aktivitas', 'permission' => 'activity.view'],
        ];
    }

    private function siswaItems(): array
    {
        return [
            ['route' => 'siswa.dashboard', 'active' => 'siswa.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Beranda'],
            ['route' => 'siswa.wizard', 'active' => 'siswa.wizard', 'icon' => 'list-checks', 'label' => 'Data Pendaftaran'],
            ['route' => 'siswa.documents', 'active' => 'siswa.documents', 'icon' => 'files', 'label' => 'Dokumen'],
            ['route' => 'siswa.status', 'active' => 'siswa.status', 'icon' => 'history', 'label' => 'Status Verifikasi'],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil'],
        ];
    }

    private function parentItems(): array
    {
        return [
            ['route' => 'parent.dashboard', 'active' => 'parent.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Anak Saya'],
            ['route' => 'parent.attendance', 'active' => 'parent.attendance*', 'label' => 'Absensi', 'icon' => 'calendar-check', 'permission' => 'classroom.attendance.view'],
            ['route' => 'parent.academic', 'active' => 'parent.academic*', 'label' => 'Akademik', 'icon' => 'award', 'permission' => 'grade.view'],
            ['route' => 'parent.announcements', 'active' => 'parent.announcements*', 'label' => 'Pengumuman', 'icon' => 'megaphone', 'permission' => 'announcement.view'],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil'],
        ];
    }

    // --------------------------------------------------------------------- dock

    /**
     * The bottom dock: at most 5 entries, chosen per workspace.
     *
     * A verifier's dock leads with the queue, not a generic dashboard; a homeroom
     * teacher gets their class and attendance first. Same visual structure,
     * different destinations.
     */
    private function dock(User $user): array
    {
        $candidates = $this->isParent($user)
            ? $this->parentDock()
            : ($user->isStudent() ? $this->siswaDock() : $this->staffDock($user));

        return array_slice($this->visible($user, $candidates), 0, 5);
    }

    private function staffDock(User $user): array
    {
        $pending = Registration::where('status', Registration::STATUS_PENDING)->count();

        // A verifier's job IS the queue, so it leads.
        if ($user->can('verification.approve')) {
            return [
                ['route' => 'workspace.verifikator', 'active' => 'workspace.verifikator', 'icon' => 'clipboard-check', 'label' => 'Antrean', 'short' => 'Antrean', 'permission' => 'verification.approve', 'badge' => $pending],
                ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'icon' => 'users', 'label' => 'Siswa', 'short' => 'Siswa', 'permission' => 'student.view', 'module' => 'students'],
                ['route' => 'academic.classes.index', 'active' => 'academic.classes*', 'icon' => 'users-round', 'label' => 'Kelas', 'short' => 'Kelas', 'permission' => 'classroom.view', 'module' => 'academic'],
                ['route' => 'laporan.index', 'active' => 'laporan.*', 'icon' => 'chart-bar', 'label' => 'Laporan', 'short' => 'Laporan', 'permission' => 'report.view'],
                ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil', 'short' => 'Profil'],
            ];
        }

        return [
            ['route' => 'workspace.admin', 'active' => 'workspace.admin', 'icon' => 'layout-dashboard', 'label' => 'Beranda', 'short' => 'Beranda', 'permission' => 'dashboard.admin.view'],
            ['route' => 'admin.registrations', 'active' => 'admin.registrations', 'icon' => 'clipboard-check', 'label' => 'Verifikasi', 'short' => 'Verifikasi', 'permission' => 'verification.view', 'badge' => $pending, 'module' => 'ppdb'],
            ['route' => 'kesiswaan.students', 'active' => 'kesiswaan.students', 'icon' => 'users', 'label' => 'Siswa', 'short' => 'Siswa', 'permission' => 'student.view', 'module' => 'students'],
            ['route' => 'academic.classes.index', 'active' => 'academic.classes*', 'icon' => 'users-round', 'label' => 'Kelas', 'short' => 'Kelas', 'permission' => 'classroom.view', 'module' => 'academic'],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil', 'short' => 'Profil'],
        ];
    }

    private function siswaDock(): array
    {
        return [
            ['route' => 'siswa.dashboard', 'active' => 'siswa.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Beranda', 'short' => 'Beranda'],
            ['route' => 'siswa.wizard', 'active' => 'siswa.wizard', 'icon' => 'list-checks', 'label' => 'Pendaftaran', 'short' => 'Data'],
            ['route' => 'siswa.documents', 'active' => 'siswa.documents', 'icon' => 'files', 'label' => 'Dokumen', 'short' => 'Dokumen'],
            ['route' => 'siswa.status', 'active' => 'siswa.status', 'icon' => 'history', 'label' => 'Status', 'short' => 'Status'],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil', 'short' => 'Profil'],
        ];
    }

    private function parentDock(): array
    {
        return [
            ['route' => 'parent.dashboard', 'active' => 'parent.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Anak Saya', 'short' => 'Anak'],
            ['route' => 'parent.attendance', 'active' => 'parent.attendance*', 'icon' => 'calendar-check', 'label' => 'Absensi', 'short' => 'Absensi', 'permission' => 'classroom.attendance.view'],
            ['route' => 'parent.announcements', 'active' => 'parent.announcements*', 'icon' => 'megaphone', 'label' => 'Pengumuman', 'short' => 'Info', 'permission' => 'announcement.view'],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'user', 'label' => 'Profil', 'short' => 'Profil'],
        ];
    }

    // --------------------------------------------------------------------- more

    /**
     * Secondary destinations for the mobile "more" sheet.
     *
     * Built from the sidebar by flattening it, so the sheet can never offer a
     * link the sidebar does not — and both stay permission-filtered.
     */
    private function more(User $user, array $sidebar): array
    {
        $flat = [];

        foreach ($sidebar as $item) {
            if (! empty($item['children'])) {
                foreach ($item['children'] as $child) {
                    $child['group'] = $item['label'];
                    $flat[] = $child;
                }

                continue;
            }

            $item['group'] = null;
            $flat[] = $item;
        }

        return $flat;
    }

    // ------------------------------------------------------------------ helpers

    private function isParent(User $user): bool
    {
        return $user->isStudent() === false
            && \App\Models\GuardianRelationship::query()
                ->where('guardian_user_id', $user->id)
                ->where('status', 'active')
                ->exists();
    }

    private function homeroomsAClass(User $user): bool
    {
        return $this->homeroomCount($user) > 0;
    }

    private function homeroomCount(User $user): int
    {
        return \App\Models\HomeroomAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', \App\Models\HomeroomAssignment::ACTIVE)
            ->count();
    }
}
