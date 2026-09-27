# Screenshot Index

Semua gambar adalah **render nyata** dari aplikasi yang sedang berjalan,
diambil melalui sesi terautentikasi (Chrome DevTools Protocol, Microsoft Edge
headless). Tidak ada UI yang digambar, disimulasikan, atau direkonstruksi.

**Viewport**

| Folder | Ukuran | Device scale |
|---|---|---|
| `desktop/` | 1440 × 900 | 1× |
| `tablet/` | 768 × 1024 | 1× |
| `mobile/` | 390 × 844 | 2× |

**Data**
Seluruh data demonstrasi bersifat fiktif: SMK Demo Nusantara, tahun ajaran
2026/2027. Tidak ada data siswa, NIK, atau nomor telepon nyata.

**Regenerasi**

```bash
php artisan showcase:seed          # dataset
python tools/shot-batch.py         # semua screenshot
python tools/shot-batch.py --only attendance   # satu halaman saja
```

---

## Desktop

| File | Fitur | Role | Kegunaan | Status |
|---|---|---|---|---|
| `01-login.png` | Autentikasi | — | Halaman masuk | IMPLEMENTED |
| `02-registration.png` | Pendaftaran | — | Formulir pendaftaran awal | IMPLEMENTED |
| `02-super-admin-dashboard.png` | Dashboard | Super Admin | Kendali sistem | IMPLEMENTED |
| `03-ppdb-queue.png` | PPDB | Admin | Antrean pendaftaran | IMPLEMENTED |
| `04-student-list.png` | Data Siswa | Admin | Daftar siswa | IMPLEMENTED |
| `05-reports.png` | Laporan | Admin | Report builder | IMPLEMENTED |
| `06-kesiswaan-dashboard.png` | Dashboard | Kesiswaan | Siklus hidup siswa | IMPLEMENTED |
| `07-classroom-list.png` | Kelas | Kesiswaan | Daftar rombel | IMPLEMENTED |
| `08-statistics.png` | Statistik | Kesiswaan | Statistik siswa | IMPLEMENTED |
| `09-operator-dashboard.png` | Dashboard | Operator | Entri data | IMPLEMENTED |
| `11-verification-queue.png` | Verifikasi | Verifikator | Antrean verifikasi | IMPLEMENTED |
| `12-kelas-saya.png` | Kelas Saya | Wali Kelas | Kelas yang ditugaskan | IMPLEMENTED |
| `13-class-workspace.png` | Kelas | Wali Kelas | Ruang kerja kelas | IMPLEMENTED |
| `14-attendance.png` | Absensi | Wali Kelas | Absensi siswa | IMPLEMENTED |
| `15-announcements.png` | Pengumuman | Wali Kelas | Pengumuman kelas | IMPLEMENTED |
| `16-student-dashboard.png` | Dashboard | Siswa | Layanan mandiri | IMPLEMENTED |
| `17-registration-wizard.png` | Pendaftaran | Siswa | Wizard bertahap | IMPLEMENTED |
| `18-documents.png` | Dokumen | Siswa | Unggah & status berkas | IMPLEMENTED |
| `19-registration-status.png` | Status | Siswa | Status verifikasi | IMPLEMENTED |
| `20-parent-dashboard.png` | Portal Orang Tua | Orang Tua | Daftar anak | IMPLEMENTED |
| `20b-parent-child-attendance.png` | Absensi | Orang Tua | Absensi anak | IMPLEMENTED |
| `21-user-management.png` | Pengguna | Super Admin | Manajemen akun | IMPLEMENTED |
| `22-role-management.png` | RBAC | Super Admin | Matriks permission | IMPLEMENTED |
| `23-activity-log.png` | Audit | Super Admin | Log aktivitas | IMPLEMENTED |
| `24-school-profile.png` | Pengaturan | Admin | Profil sekolah | IMPLEMENTED |
| `25-branding.png` | Branding | Admin | Tampilan & logo | IMPLEMENTED |
| `26-academic-year.png` | Akademik | Admin | Tahun ajaran | IMPLEMENTED |
| `27-registration-settings.png` | Pengaturan | Admin | Pengaturan pendaftaran | IMPLEMENTED |

## Mobile (390 × 844)

| File | Fitur | Role | Status |
|---|---|---|---|
| `rp-admin-dashboard.png` | Dashboard | Admin | IMPLEMENTED |
| `rp-kesiswaan-dashboard.png` | Dashboard | Kesiswaan | IMPLEMENTED |
| `rp-kelas-saya.png` | Kelas Saya | Wali Kelas | IMPLEMENTED |
| `rp-attendance.png` | Absensi | Wali Kelas | IMPLEMENTED |
| `rp-student-dashboard.png` | Dashboard | Siswa | IMPLEMENTED |
| `rp-parent-dashboard.png` | Portal Orang Tua | Orang Tua | IMPLEMENTED |
| `rp-student-list.png` | Data Siswa | Kesiswaan | IMPLEMENTED |
| `rp-verification.png` | Verifikasi | Verifikator | IMPLEMENTED |

## Tablet (768 × 1024)

| File | Fitur | Role | Status |
|---|---|---|---|
| `tb-kesiswaan-dashboard.png` | Dashboard | Kesiswaan | IMPLEMENTED |
| `tb-kelas-saya.png` | Kelas Saya | Wali Kelas | IMPLEMENTED |
| `tb-attendance.png` | Absensi | Wali Kelas | IMPLEMENTED |

---

## Pasangan responsif

Delapan layar penting diambil pada desktop **dan** mobile, dipakai untuk
membandingkan perilaku navigasi:

| Layar | Desktop | Mobile |
|---|---|---|
| Dashboard Admin | `02-super-admin-dashboard.png` | `rp-admin-dashboard.png` |
| Dashboard Kesiswaan | `06-kesiswaan-dashboard.png` | `rp-kesiswaan-dashboard.png` |
| Kelas Saya | `12-kelas-saya.png` | `rp-kelas-saya.png` |
| Dashboard Siswa | `16-student-dashboard.png` | `rp-student-dashboard.png` |
| Dashboard Orang Tua | `20-parent-dashboard.png` | `rp-parent-dashboard.png` |
| Daftar Siswa | `04-student-list.png` | `rp-student-list.png` |
| Antrean Verifikasi | `11-verification-queue.png` | `rp-verification.png` |
| Absensi | `14-attendance.png` | `rp-attendance.png` |

Yang terlihat berbeda antar viewport:

- **Sidebar** ada di desktop dan tablet; di bawah 1024px menjadi drawer.
- **Bottom navigation** hanya di mobile, maksimal lima slot, dengan tombol Menu
  untuk sisanya.
- **Tabel** berubah menjadi kartu pada 360–430px, karena tabel empat kolom
  tidak terbaca pada layar sempit.
- **Tombol status absensi** menjadi dua baris di bawah 480px agar tetap
  memenuhi target sentuh 44px.

---

## Dokumentasi terkait

| Screenshot | Dokumen |
|---|---|
| Dashboard per role | [DASHBOARD-ARCHITECTURE.md](DASHBOARD-ARCHITECTURE.md) |
| Navigasi | [NAVIGATION-ARCHITECTURE.md](NAVIGATION-ARCHITECTURE.md) |
| TOKEN & komponen | [UI-UX-GUIDELINES.md](UI-UX-GUIDELINES.md) |
| Fitur | [FEATURES.md](FEATURES.md) |
| Tingkat pengguna | [USER-TIERS.md](USER-TIERS.md) |
| Hak akses | [ROLE-CAPABILITY-MATRIX.md](ROLE-CAPABILITY-MATRIX.md) |
