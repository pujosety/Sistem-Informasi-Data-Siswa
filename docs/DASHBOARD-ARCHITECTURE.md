# Dashboard Architecture

Setiap workspace menjawab tiga pertanyaan **dengan urutan yang sama**:

1. **Apa yang perlu perhatian saya?** (urgent)
2. **Apa yang perlu saya ketahui?** (metrics)
3. **Apa yang bisa saya lakukan berikutnya?** (workflow)

Role berbeda pada **isi**, bukan pada struktur. Semua angka berasal dari query
nyata — tidak ada metrik produksi yang dikarang.

---

## 1. Super Admin

| | |
|---|---|
| Tujuan | Kendali sistem |
| Route | `/ruang-kerja/admin` |
| Permission | `dashboard.admin.view` |

**Metrics** — Total Siswa · Terverifikasi · Menunggu Verifikasi · Perlu Perbaikan
**Sections** — Antrean pendaftaran · Data belum lengkap · Aktivitas terakhir
**Quick actions** — Tambah Siswa · Buat Laporan
**Mobile** — 2 kolom metrics, antrean menjadi kartu

---

## 2. Admin

| | |
|---|---|
| Tujuan | Administrasi harian |
| Route | `/ruang-kerja/admin` |
| Permission | `dashboard.admin.view` |

Struktur sama dengan Super Admin. Admin **tidak** diberi konfigurasi sistem
kritis — itu batas permission, bukan sekadar menu yang disembunyikan.

---

## 3. Kesiswaan

| | |
|---|---|
| Tujuan | Siklus hidup siswa |
| Route | `/ruang-kerja/kesiswaan` |
| Permission | `student.view` |

**Metrics** — Siswa Aktif · Kelas · Belum ada kelas · Data belum lengkap
**Sections** — Siswa per tingkat (bar) · Kelas per jurusan
**Quick actions** — Cari Siswa · Lihat Kelas
**Catatan** — "Belum ada kelas" adalah metrik operasional, bukan statistik.

---

## 4. Operator

| | |
|---|---|
| Tujuan | Entri data |
| Route | `/ruang-kerja/operator` |
| Permission | `registration.update` |

**Metrics** — Draft · Terkirim · Data belum lengkap · Belum ada kelas
**Sections** — Pendaftaran tersimpan sebagai draft
**Prinsip** — sengaja sederhana. Tanpa analitik.

---

## 5. Verifikator

| | |
|---|---|
| Tujuan | Verifikasi cepat |
| Route | `/ruang-kerja/verifikator` |
| Permission | `verification.approve` |

Dashboard **adalah** work queue.

**Metrics** — Menunggu · Perlu Perbaikan · Selesai Hari Ini · Terlama (hari)
**Sections** — Antrean menunggu, terlama di atas
**Perilaku** — Setelah memproses satu item, antrean berikutnya langsung terlihat.
Tidak ada analitik: pekerjaan verifikasi adalah membersihkan antrean.

---

## 6. Wali Kelas — "Kelas Saya"

| | |
|---|---|
| Tujuan | Mengelola kelas sendiri |
| Route | `/kelas-saya` |
| Pemicu | `homeroom_assignments` (**bukan role**) |

**Per kelas** — Nama kelas · Tahun ajaran · Jurusan · Ruang
**Metrics** — Siswa (x/y) · Kehadiran hari ini · Data belum lengkap · Pengumuman aktif
**Quick actions** — Isi Absensi · Lihat Siswa · Orang Tua/Wali · Buat Pengumuman
**Mobile** — kartu per kelas, tombol besar, absensi jadi aksi utama

Wali kelas yang juga ber-role kesiswaan mendapat **dua** workspace dan dapat
berpindah lewat workspace switcher.

---

## 7. Siswa

| | |
|---|---|
| Tujuan | Self service |
| Route | `/siswa/dashboard` |
| Permission | portal siswa |

Prioritas: **status pendaftaran → aksi berikutnya → kelengkapan → dokumen →
notifikasi**. Tanpa analitik admin.

---

## 8. Orang Tua / Wali

| | |
|---|---|
| Tujuan | Memantau anak |
| Route | `/orang-tua` |
| Pemicu | `guardian_relationships` (**bukan role**) |

**Per anak** — Nama · Kelas · Kehadiran % · Kelengkapan data · Status
**Sub-halaman** — Absensi · Akademik (hanya `published`) · Pengumuman
**Tidak ditampilkan** — catatan admin internal · siswa lain · nilai draf

---

## 9. Prioritas informasi

Urutan di setiap dashboard:

1. Antrean / tugas mendesak
2. Metrics inti
3. Workflow utama
4. Sekunder
5. Aktivitas terakhir

Chart dekoratif tidak pernah berada di atas tugas mendesak.
