# Navigation Architecture

Tiga permukaan, satu sumber data: `NavigationService`. Sidebar, dock mobile, dan
sheet "Menu Lainnya" berasal dari pemanggilan yang sama, sehingga tidak mungkin
saling berbeda.

---

## 1. Desktop (≥ 1024px)

```
┌────────────┬──────────────────────────────────┐
│  SIDEBAR   │ TOPBAR  [breadcrumb] [search]   │
│            │                    [bell] [user]│
│  brand     ├──────────────────────────────────┤
│  workspace │                                  │
│  groups    │  PAGE CONTENT                    │
│  account   │                                  │
└────────────┴──────────────────────────────────┘
```

- Lebar: **256px** expanded, **72px** collapsed
- Pilihan collapsed disimpan di `localStorage` (`sida.sidebar.collapsed`)
- Active state memakai **background + bar + weight**, bukan hanya warna teks
- Sidebar boleh panjang; dock tidak boleh

## 2. Tablet (768–1023px)

Sidebar menjadi **drawer** di bawah 1024px. Menyamakan sidebar 256px di layar
768px akan menyisakan 512px untuk konten — terlalu sempit.

## 3. Mobile (< 768px)

```
┌──────────────────────────┐
│ Logo / Judul      🔔     │  topbar ringkas
├──────────────────────────┤
│                          │
│  CONTENT                 │
│                          │
├──────────────────────────┤
│ Beranda  Data  Dok  Menu │  dock
└──────────────────────────┘
```

## 4. Bottom navigation — role specific

**Maksimal 4 tujuan + tombol Menu = 5 slot.** Dock 9 ikon tidak usable di 360px.

| Workspace | Dock |
|---|---|
| Admin | Beranda · Verifikasi · Siswa · Kelas · Menu |
| Verifikator | **Antrean** · Siswa · Kelas · Laporan · Menu |
| Wali Kelas | Beranda · Verifikasi · Siswa · Kelas · Menu |
| Siswa | Beranda · Data · Dokumen · Status · Menu |
| Orang Tua | Anak · Absensi · Info · Menu |

Verifikator melihat antrean lebih dulu karena itu pekerjaannya.

Struktur visual sama; **isi berbeda** sesuai peran.

## 5. Menu Lainnya

Bottom sheet berisi seluruh tujuan yang tidak masuk dock. Daftar ini dibangun
dari sidebar yang sudah difilter permission, jadi sheet tidak mungkin
menawarkan link yang tidak ada di desktop.

## 6. Safe area

Dock memakai `padding-bottom: env(safe-area-inset-bottom)`. Sticky action di
atar dock tetap berada **di atas** bar navigasi.

## 7. Workspace switcher

Muncul di sidebar hanya bila user punya **lebih dari satu** workspace.
Label tier: Sistem · Manajemen · Operasional · Portal · Penugasan.

## 8. Peta menu per role

| Grup | Item | Permission |
|---|---|---|
| — | Dashboard | `dashboard.admin.view` |
| PPDB | Verifikasi | `verification.view` |
| PPDB | Master Data | `master.view` |
| Akademik | Tahun Ajaran | `academic_year.view` |
| Akademik | Kelas | `classroom.view` |
| Akademik | Penempatan Siswa | `enrollment.view` |
| Data | Data Siswa | `student.view` |
| Data | Pengguna | `user.view` |
| Data | Role & Hak Akses | `role.view` |
| Laporan | Statistik / Rekapitulasi | `student.view` |
| Laporan | Buat Laporan | `report.view` |
| Pengaturan | Profil Sekolah | `school.view` |
| Pengaturan | Branding | `branding.view` |
| Pengaturan | Pendaftaran | `settings.view` |
| — | Log Aktivitas | `activity.view` |
| — | **Kelas Saya** | assignment (homeroom aktif) |

Grup dengan **tidak ada** item yang boleh dibuka tidak dirender sama sekali.
