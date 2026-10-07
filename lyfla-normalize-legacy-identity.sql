-- LYFLA legacy identity repair
-- Safe and conditional: only known legacy values are changed.
-- Existing custom values are preserved.

UPDATE settings
SET value = 'SMP 1 LYFLA', updated_at = CURRENT_TIMESTAMP
WHERE `key` = 'app.name'
  AND TRIM(BOTH '"' FROM TRIM(value)) IN ('SIMS', 'SIDA', 'Sistem Informasi Data Siswa', 'Sistem Informasi Data Siswa — SIDA');

UPDATE settings
SET value = 'LYFLA', updated_at = CURRENT_TIMESTAMP
WHERE `key` = 'app.short_name'
  AND TRIM(BOTH '"' FROM TRIM(value)) IN ('SIMS', 'SIDA', 'SMA');

UPDATE settings
SET value = 'Pendaftaran, akademik, dan informasi sekolah dalam satu portal.', updated_at = CURRENT_TIMESTAMP
WHERE `key` = 'app.tagline'
  AND TRIM(BOTH '"' FROM TRIM(value)) IN ('Sistem Informasi Managemen Siswa', 'Portal Data Siswa', 'Data siswa, satu tempat');

-- Clear Laravel cache after import:
-- php artisan cache:clear
