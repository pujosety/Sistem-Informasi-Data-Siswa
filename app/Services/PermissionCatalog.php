<?php

namespace App\Services;

/**
 * The single source of truth for the permission catalogue.
 *
 * Everything — the seeder, the role editor UI, the navigation, and the tests —
 * reads from here, so a permission can never be named slightly differently in
 * two places. Names are dot-namespaced by domain:
 *
 *   <domain>.<action>            e.g. student.update
 *   <domain>.<action>.<scope>    e.g. role.assign.super_admin
 */
class PermissionCatalog
{
    /**
     * domain => [label, [permission => label]]
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function domains(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'permissions' => [
                    'dashboard.admin.view' => 'Lihat dashboard administrasi',
                    'dashboard.student.view' => 'Lihat dashboard siswa',
                ],
            ],

            'student' => [
                'label' => 'Data Siswa',
                'permissions' => [
                    'student.view' => 'Lihat daftar & profil siswa',
                    'student.create' => 'Tambah siswa',
                    'student.update' => 'Ubah data siswa',
                    'student.delete' => 'Hapus siswa',
                    'student.export' => 'Ekspor data siswa',
                ],
            ],

            'academic_year' => [
                'label' => 'Tahun Ajaran',
                'permissions' => [
                    'academic_year.view' => 'Lihat tahun ajaran',
                    'academic_year.create' => 'Buat tahun ajaran',
                    'academic_year.update' => 'Ubah tahun ajaran',
                    'academic_year.activate' => 'Aktifkan / arsipkan tahun ajaran',
                ],
            ],

            'classroom' => [
                'label' => 'Kelas',
                'permissions' => [
                    'classroom.view' => 'Lihat daftar kelas',
                    'classroom.view.all' => 'Lihat SEMUA kelas (g overcame cakupan wali kelas)',
                    'classroom.create' => 'Buat kelas',
                    'classroom.update' => 'Ubah kelas',
                    'classroom.archive' => 'Arsipkan kelas',
                ],
            ],

            'enrollment' => [
                'label' => 'Penempatan Siswa',
                'permissions' => [
                    'enrollment.view' => 'Lihat penempatan siswa',
                    'enrollment.assign' => 'Tempatkan siswa ke kelas',
                    'enrollment.move' => 'Pindahkan siswa antar kelas',
                    'enrollment.promote' => 'Proses kenaikan kelas',
                    'enrollment.graduate' => 'Proses kelulusan',
                ],
            ],

            'homeroom' => [
                'label' => 'Wali Kelas',
                'permissions' => [
                    'homeroom.assign' => 'Tetapkan wali kelas',
                    'homeroom.change' => 'Ganti wali kelas',
                ],
            ],

            'classroom_scope' => [
                'label' => 'Akses Terbatas ke Kelas',
                'permissions' => [
                    'classroom.student.view' => 'Lihat siswa kelas yang ditugaskan',
                    'classroom.parent.view' => 'Lihat orang tua/wali kelas',
                    'classroom.attendance.view' => 'Lihat absensi kelas',
                    'classroom.attendance.manage' => 'Isi & koreksi absensi',
                    'classroom.academic.view' => 'Lihat rekap nilai kelas',
                    'classroom.academic.edit' => 'Ubah nilai kelas',
                    'classroom.announcement.view' => 'Lihat pengumuman kelas',
                    'classroom.announcement.create' => 'Buat pengumuman kelas',
                    'classroom.announcement.update' => 'Ubah pengumuman kelas',
                    'classroom.announcement.delete' => 'Hapus pengumuman kelas',
                    'classroom.report.view' => 'Lihat laporan kelas',
                    'classroom.report.export' => 'Ekspor laporan kelas',
                ],
            ],

            'guardian' => [
                'label' => 'Orang Tua / Wali',
                'permissions' => [
                    'guardian.view' => 'Lihat data orang tua/wali',
                    'guardian.link' => 'Tautkan akun orang tua/wali',
                    'guardian.unlink' => 'Lepas tautan orang tua/wali',
                ],
            ],

            'attendance' => [
                'label' => 'Absensi',
                'permissions' => [
                    'attendance.view' => 'Lihat absensi seluruh sekolah',
                    'attendance.manage' => 'Kelola absensi seluruh sekolah',
                ],
            ],

            'grade' => [
                'label' => 'Akademik',
                'permissions' => [
                    'grade.view' => 'Lihat nilai',
                    'grade.edit' => 'Ubah nilai',
                    'grade.publish' => 'Terbitkan nilai ke siswa & orang tua',
                ],
            ],

            'alumni' => [
                'label' => 'Alumni',
                'permissions' => [
                    'alumni.view' => 'Lihat data alumni',
                ],
            ],

            'announcement' => [
                'label' => 'Pengumuman',
                'permissions' => [
                    'announcement.view' => 'Lihat pengumuman',
                    'announcement.create' => 'Buat pengumuman',
                    'announcement.update' => 'Ubah pengumuman',
                    'announcement.delete' => 'Hapus pengumuman',
                ],
            ],

            'registration' => [
                'label' => 'Pendaftaran',
                'permissions' => [
                    'registration.view' => 'Lihat pendaftaran',
                    'registration.create' => 'Buat pendaftaran',
                    'registration.update' => 'Ubah pendaftaran',
                    'registration.verify' => 'Putuskan pendaftaran',
                    'registration.delete' => 'Hapus pendaftaran',
                ],
            ],

            'document' => [
                'label' => 'Dokumen',
                'permissions' => [
                    'document.view' => 'Lihat berkas siswa',
                    'document.download' => 'Unduh berkas siswa',
                    'document.verify' => 'Periksa & nilai berkas',
                ],
            ],

            'verification' => [
                'label' => 'Verifikasi',
                'permissions' => [
                    'verification.view' => 'Buka ruang verifikasi',
                    'verification.approve' => 'Setujui pendaftaran',
                    'verification.request_revision' => 'Minta perbaikan',
                ],
            ],

            'report' => [
                'label' => 'Laporan',
                'permissions' => [
                    'report.view' => 'Lihat halaman laporan',
                    'report.export' => 'Ekspor Excel / CSV / PDF',
                ],
            ],

            'user' => [
                'label' => 'Pengguna',
                'permissions' => [
                    'user.view' => 'Lihat daftar pengguna',
                    'user.create' => 'Buat pengguna',
                    'user.update' => 'Ubah pengguna',
                    'user.disable' => 'Nonaktifkan pengguna',
                    'user.delete' => 'Hapus pengguna',
                    'user.reset_password' => 'Atur ulang password',
                ],
            ],

            'role' => [
                'label' => 'Role & Hak Akses',
                'permissions' => [
                    'role.view' => 'Lihat daftar role',
                    'role.create' => 'Buat role',
                    'role.update' => 'Ubah role',
                    'role.delete' => 'Hapus role',
                    'role.assign' => 'Tetapkan role ke pengguna',
                    'role.assign.super_admin' => 'Tetapkan role Super Admin',
                ],
            ],

            'settings' => [
                'label' => 'Pengaturan Sistem',
                'permissions' => [
                    'settings.view' => 'Lihat pengaturan',
                    'settings.update' => 'Ubah pengaturan',
                ],
            ],

            'branding' => [
                'label' => 'Branding',
                'permissions' => [
                    'branding.view' => 'Lihat pengaturan branding',
                    'branding.update' => 'Ubah logo, nama, dan warna',
                ],
            ],

            'school' => [
                'label' => 'Profil Sekolah',
                'permissions' => [
                    'school.view' => 'Lihat profil sekolah',
                    'school.update' => 'Ubah profil sekolah',
                ],
            ],

            'master' => [
                'label' => 'Master Data',
                'permissions' => [
                    'master.view' => 'Lihat master data',
                    'master.create' => 'Tambah master data',
                    'master.update' => 'Ubah master data',
                    'master.delete' => 'Hapus master data',
                ],
            ],

            'activity' => [
                'label' => 'Log Aktivitas',
                'permissions' => [
                    'activity.view' => 'Lihat log aktivitas',
                ],
            ],

            'system' => [
                'label' => 'Sistem',
                'permissions' => [
                    'system.view' => 'Lihat informasi sistem',
                    'system.update' => 'Ubah konfigurasi kritis',
                ],
            ],
        ];
    }

    /** @return array<string, string> permission => label */
    public static function flat(): array
    {
        $flat = [];

        foreach (self::domains() as $domain) {
            foreach ($domain['permissions'] as $name => $label) {
                $flat[$name] = $label;
            }
        }

        return $flat;
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::flat());
    }

    /**
     * Default grants per role.
     *
     * Super Admin is intentionally absent: it receives every permission at
     * runtime (see RoleSeeder), so listing it here would let the UI quietly
     * diverge from reality.
     *
     * @return array<string, string[]>
     */
    public static function roleGrants(): array
    {
        $p = self::names();

        /*
         | Match by prefix. Needles may be a domain ("dashboard.admin" matches
         | every dashboard.admin.* permission) or an exact permission
         | ("student.view"). Appending a mandatory dot to the alternation broke
         | the second kind, because "^student\.view[.]" only matches a
         | permission literally named "student.view." — which silently reduced
         | every role to a single permission.
         */
        $has = function (string ...$needles) use ($p): array {
            $out = [];

            foreach ($p as $permission) {
                foreach ($needles as $needle) {
                    if ($permission === $needle || str_starts_with($permission, $needle.'.')) {
                        $out[] = $permission;
                        break;
                    }
                }
            }

            return $out;
        };

        return [
            // Day-to-day school administration. No role.*, no system.*.
            'admin' => $has(
                'dashboard.admin',
                'student.view', 'student.update', 'student.export',
                'registration.view', 'registration.update',
                'document.view', 'document.download', 'document.verify',
                'verification.view', 'verification.approve', 'verification.request_revision',
                'report.view', 'report.export',
                'master.view', 'master.create', 'master.update',
                'activity.view',
                'settings.view', 'branding.view', 'school.view',
                'academic_year', 'classroom', 'classroom.view.all', 'enrollment', 'homeroom',
                'guardian', 'attendance', 'grade', 'alumni', 'announcement',
            ),

            // Read + report. No writes to master data, no user management.
            'kesiswaan' => $has(
                'dashboard.admin',
                'student.view', 'student.export',
                // No registration.view: the registration workspace under
                // /admin/pendaftaran is the verification desk, which the spec
                // reserves for admin/verifikator. Kesiswaan reads rosters and
                // generates reports through /kesiswaan/* instead.
                'document.view',
                'report.view', 'report.export',
                'academic_year.view', 'classroom.view',
                'classroom.student.view', 'classroom.parent.view',
                'classroom.report.view', 'classroom.report.export',
                'attendance.view', 'grade.view', 'alumni.view',
                'guardian.view', 'announcement.view',
            ),

            // Data entry. Explicitly no verification, no master writes.
            'operator' => $has(
                'dashboard.admin',
                'student.view', 'student.create', 'student.update',
                'registration.view', 'registration.update',
                'document.view', 'document.download',
            ),

            // Verification only — cannot edit students or master data.
            'verifikator' => $has(
                'dashboard.admin',
                'registration.view',
                'document.view', 'document.download', 'document.verify',
                'verification.view', 'verification.approve', 'verification.request_revision',
            ),

            /*
             | Wali Kelas holds a SCOPED grant: the classroom.* permissions only
             | take effect for classrooms they are homeroom-assigned to (see
             | ClassScope). Holding the permission without an assignment opens
             | nothing, which is what keeps X RPL 2 out of reach.
             */
            'wali_kelas' => array_values(array_filter(
                $has(
                    'dashboard.admin',
                    'classroom.view',
                    'classroom.student.view', 'classroom.parent.view',
                    'classroom.attendance.view', 'classroom.attendance.manage',
                    'classroom.academic.view',
                    'classroom.announcement.view', 'classroom.announcement.create',
                    'classroom.announcement.update', 'classroom.announcement.delete',
                    'classroom.report.view', 'classroom.report.export',
                    'grade.view', 'announcement.view',
                ),
                // The prefix match that makes "classroom.view" useful also
                // swallows "classroom.view.all", which is the school-wide scope
                // bypass. A Wali Kelas must never receive it: holding it would
                // open every class and defeat the whole class-scope guarantee.
                fn (string $permission) => $permission !== 'classroom.view.all',
            )),

            // Students are handled by their own portal, not this catalogue.
            'siswa' => [],
        ];
    }

    /** Roles that must always exist and cannot be deleted. */
    public static function protectedRoles(): array
    {
        return ['super_admin', 'admin', 'siswa'];
    }

    /** Roles a non-Super Admin may assign. */
    public static function assignableRoles(): array
    {
        return ['admin', 'kesiswaan', 'operator', 'verifikator'];
    }
}
