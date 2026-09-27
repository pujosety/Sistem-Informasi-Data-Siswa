# Role Feature Coverage

Status: **Manage** (kelola) · **View** (baca) · **Own** (data sendiri)
· **Assigned** (penugasan) · **None**.

| Modul | Super Admin | Admin | Kesiswaan | Operator | Verifikator | Wali Kelas | Siswa | Orang Tua |
|---|---|---|---|---|---|---|---|---|
| Auth & profil | Own | Own | Own | Own | Own | Own | Own | Own |
| Multi-akun | — | — | — | — | — | — | — | — |
| Dashboard | Manage | Manage | View | View | View | Assigned | Own | Own |
| Workspace switcher | Ya | Ya | Ya | Ya | Ya | Ya | — | — |
| Pendaftaran | Manage | Manage | View | Manage | View | None | Own | None |
| Bio-data | Manage | Manage | View | Manage | View | None | Own | None |
| Orang tua/wali | Manage | Manage | View | View | None | Assigned | Own | Own |
| Dokumen | Manage | Manage | View | View | Manage | None | Own | Own |
| Verifikasi | Manage | Manage | None | None | Manage | None | None | None |
| Data siswa | Manage | Manage | View | Manage | View | Assigned | Own | Assigned |
| Tahun ajaran | Manage | Manage | View | None | None | None | None | None |
| Kelas | Manage | Manage | View | None | None | Assigned | None | None |
| Penempatan | Manage | Manage | None | None | None | Assigned | None | None |
| Absensi | Manage | Manage | View | None | None | Assigned | Own | Own |
| Nilai | Manage | Manage | View | None | None | Assigned (view) | Own | Own |
| Pengumuman | Manage | Manage | View | None | None | Assigned | Assigned | Assigned |
| Kenaikan kelas | Manage | Manage | None | None | None | None | None | None |
| Kelulusan | Manage | Manage | None | None | None | None | None | None |
| Alumni | Manage | Manage | View | None | None | None | None | None |
| Laporan | Manage | Manage | Manage | None | None | Assigned | None | None |
| Statistik | Manage | Manage | View | None | None | None | None | None |
| Import | Manage | Manage | None | Manage | None | None | None | None |
| Notifikasi | Own | Own | Own | Own | Own | Own | Own | Own |
| Log aktivitas | Manage | View | None | None | None | None | None | None |
| Pengguna | Manage | None | None | None | None | None | None | None |
| Role & hak akses | Manage | None | None | None | None | None | None | None |
| Pengaturan | Manage | None | None | None | None | None | None | None |
| Branding | Manage | None | None | None | None | None | None | None |
| Master data | Manage | Manage | View | None | None | None | None | None |
| PWA | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |
| API | Manage | None | None | None | None | None | None | None |

## Catatan

- **None** berarti route ada tetapi middleware menolaknya — bukan sekadar
  tombol yang disembunyikan.
- Wali Kelas **View** pada sebagian modul secara teknis **Assigned**: aksesnya
  selalu digabung dengan penugasan kelas.
- Orang tua hanya melihat data anak yang tertaut, dan hanya nilai berstatus
  `published`.
