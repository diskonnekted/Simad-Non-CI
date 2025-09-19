-- =====================================================
-- Script SQL untuk Update Database Server Production
-- Database: simadorbitdev_simad
-- Tujuan: Menambahkan kolom tanggal_selesai ke tabel jadwal_kunjungan
-- =====================================================

-- Gunakan database yang benar
USE simadorbitdev_simad;

-- Cek apakah tabel jadwal_kunjungan ada
SELECT 'Mengecek tabel jadwal_kunjungan...' AS status;
SHOW TABLES LIKE 'jadwal_kunjungan';

-- Tampilkan struktur tabel sebelum update
SELECT 'Struktur tabel SEBELUM update:' AS status;
DESCRIBE jadwal_kunjungan;

-- Cek apakah kolom tanggal_selesai sudah ada
SELECT 'Mengecek kolom tanggal_selesai...' AS status;
SHOW COLUMNS FROM jadwal_kunjungan LIKE 'tanggal_selesai';

-- Tambahkan kolom tanggal_selesai jika belum ada
-- Gunakan IF NOT EXISTS untuk menghindari error jika kolom sudah ada
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE jadwal_kunjungan ADD COLUMN tanggal_selesai TIMESTAMP NULL AFTER status',
        'SELECT "Kolom tanggal_selesai sudah ada" AS message'
    )
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'simadorbitdev_simad' 
    AND TABLE_NAME = 'jadwal_kunjungan' 
    AND COLUMN_NAME = 'tanggal_selesai'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tampilkan struktur tabel setelah update
SELECT 'Struktur tabel SETELAH update:' AS status;
DESCRIBE jadwal_kunjungan;

-- Tampilkan sample data untuk verifikasi
SELECT 'Sample data dari tabel jadwal_kunjungan:' AS status;
SELECT 
    id,
    desa_id,
    jenis_kunjungan,
    tanggal_kunjungan,
    status,
    tanggal_selesai,
    created_at
FROM jadwal_kunjungan 
LIMIT 5;

-- Tampilkan jumlah total record
SELECT 
    COUNT(*) as total_records,
    'Total records di tabel jadwal_kunjungan' as description
FROM jadwal_kunjungan;

-- Verifikasi kolom tanggal_selesai berhasil ditambahkan
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'simadorbitdev_simad' 
AND TABLE_NAME = 'jadwal_kunjungan' 
AND COLUMN_NAME = 'tanggal_selesai';

SELECT 'Update database selesai! Kolom tanggal_selesai berhasil ditambahkan.' AS final_status;

-- =====================================================
-- CATATAN PENTING:
-- 1. Backup database sebelum menjalankan script ini
-- 2. Pastikan Anda memiliki privilege ALTER TABLE
-- 3. Script ini aman dijalankan berulang kali
-- 4. Jika kolom sudah ada, tidak akan terjadi error
-- =====================================================