-- Script Pembersihan Data Terkait Lainnya
-- Tanggal: 2025-01-20
-- Deskripsi: Script untuk menghapus data desa percobaan, user test, activity logs lama, dan data tidak terpakai

-- PERINGATAN: Pastikan sudah melakukan backup database sebelum menjalankan script ini!

-- ========================================
-- 1. BACKUP DATA YANG AKAN DIHAPUS
-- ========================================

-- Buat tabel backup untuk desa yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_desa_deleted (
    id INT PRIMARY KEY,
    nama_desa VARCHAR(255),
    kecamatan VARCHAR(255),
    nama_kepala_desa VARCHAR(255),
    status ENUM('aktif', 'nonaktif'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- Buat tabel backup untuk users yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_users_deleted (
    id INT PRIMARY KEY,
    username VARCHAR(50),
    email VARCHAR(255),
    nama_lengkap VARCHAR(255),
    role ENUM('admin', 'operator', 'viewer'),
    status ENUM('aktif', 'nonaktif'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- Buat tabel backup untuk kategori yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_kategori_deleted (
    id INT PRIMARY KEY,
    nama VARCHAR(255),
    deskripsi TEXT,
    status ENUM('aktif', 'nonaktif'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- Buat tabel backup untuk biaya operasional yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_biaya_operasional_deleted (
    id INT PRIMARY KEY,
    kode_biaya VARCHAR(50),
    nama_biaya VARCHAR(255),
    kategori_biaya VARCHAR(100),
    status ENUM('aktif', 'nonaktif'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- ========================================
-- 2. BACKUP DESA PERCOBAAN
-- ========================================

-- Backup desa yang mengandung kata test/demo/contoh
INSERT INTO backup_desa_deleted 
(id, nama_desa, kecamatan, nama_kepala_desa, status, created_at, updated_at, delete_reason)
SELECT 
    id, nama_desa, kecamatan, nama_kepala_desa, status, created_at, updated_at,
    'Desa percobaan - mengandung kata test/demo/contoh'
FROM desa 
WHERE nama_desa LIKE '%test%' 
   OR nama_desa LIKE '%contoh%' 
   OR nama_desa LIKE '%demo%' 
   OR nama_desa LIKE '%percobaan%' 
   OR nama_desa LIKE '%coba%' 
   OR nama_desa LIKE '%sample%';

-- Backup desa dengan status nonaktif
INSERT INTO backup_desa_deleted 
(id, nama_desa, kecamatan, nama_kepala_desa, status, created_at, updated_at, delete_reason)
SELECT 
    id, nama_desa, kecamatan, nama_kepala_desa, status, created_at, updated_at,
    'Desa dengan status nonaktif'
FROM desa 
WHERE status = 'nonaktif'
  AND id NOT IN (SELECT id FROM backup_desa_deleted);

-- Backup desa dengan data tidak lengkap
INSERT INTO backup_desa_deleted 
(id, nama_desa, kecamatan, nama_kepala_desa, status, created_at, updated_at, delete_reason)
SELECT 
    id, nama_desa, kecamatan, nama_kepala_desa, status, created_at, updated_at,
    'Desa dengan data tidak lengkap'
FROM desa 
WHERE (nama_kepala_desa IS NULL 
    OR nama_kepala_desa = '' 
    OR nama_kepala_desa = 'Belum ada'
    OR kecamatan IS NULL 
    OR kecamatan = '')
  AND id NOT IN (SELECT id FROM backup_desa_deleted);

-- ========================================
-- 3. BACKUP USER TEST
-- ========================================

-- Backup users yang mengandung kata test/demo
INSERT INTO backup_users_deleted 
(id, username, email, nama_lengkap, role, status, created_at, updated_at, delete_reason)
SELECT 
    id, username, email, nama_lengkap, role, status, created_at, updated_at,
    'User percobaan - mengandung kata test/demo'
FROM users 
WHERE username LIKE '%test%' 
   OR username LIKE '%demo%' 
   OR username LIKE '%contoh%'
   OR nama_lengkap LIKE '%test%'
   OR nama_lengkap LIKE '%demo%'
   OR email LIKE '%test%'
   OR email LIKE '%demo%';

-- Backup users dengan status nonaktif
INSERT INTO backup_users_deleted 
(id, username, email, nama_lengkap, role, status, created_at, updated_at, delete_reason)
SELECT 
    id, username, email, nama_lengkap, role, status, created_at, updated_at,
    'User dengan status nonaktif'
FROM users 
WHERE status = 'nonaktif'
  AND id NOT IN (SELECT id FROM backup_users_deleted);

-- ========================================
-- 4. BACKUP KATEGORI TIDAK TERPAKAI
-- ========================================

-- Backup kategori yang tidak digunakan oleh produk
INSERT INTO backup_kategori_deleted 
(id, nama, deskripsi, status, created_at, updated_at, delete_reason)
SELECT 
    k.id, k.nama, k.deskripsi, k.status, k.created_at, k.updated_at,
    'Kategori tidak digunakan oleh produk'
FROM kategori k
LEFT JOIN produk p ON k.id = p.kategori_id
WHERE p.kategori_id IS NULL;

-- Backup kategori dengan status nonaktif
INSERT INTO backup_kategori_deleted 
(id, nama, deskripsi, status, created_at, updated_at, delete_reason)
SELECT 
    id, nama, deskripsi, status, created_at, updated_at,
    'Kategori dengan status nonaktif'
FROM kategori 
WHERE status = 'nonaktif'
  AND id NOT IN (SELECT id FROM backup_kategori_deleted);

-- ========================================
-- 5. BACKUP BIAYA OPERASIONAL NONAKTIF
-- ========================================

-- Backup biaya operasional dengan status nonaktif
INSERT INTO backup_biaya_operasional_deleted 
(id, kode_biaya, nama_biaya, kategori_biaya, status, created_at, updated_at, delete_reason)
SELECT 
    id, kode_biaya, nama_biaya, kategori_biaya, status, created_at, updated_at,
    'Biaya operasional dengan status nonaktif'
FROM biaya_operasional 
WHERE status = 'nonaktif';

-- ========================================
-- 6. HAPUS DATA TERKAIT DESA
-- ========================================

-- Hapus transaksi dari desa yang akan dihapus (sudah dihandle di cleanup_transactions.sql)
-- Hapus konsultasi dari desa yang akan dihapus
DELETE k FROM konsultasi k
INNER JOIN backup_desa_deleted bdd ON k.desa_id = bdd.id;

-- Hapus layanan dari desa yang akan dihapus
DELETE l FROM layanan l
INNER JOIN backup_desa_deleted bdd ON l.desa_id = bdd.id;

-- Hapus jadwal dari desa yang akan dihapus
DELETE j FROM jadwal j
INNER JOIN backup_desa_deleted bdd ON j.desa_id = bdd.id;

-- ========================================
-- 7. HAPUS DATA TERKAIT USER
-- ========================================

-- Hapus activity logs dari user yang akan dihapus
DELETE al FROM activity_logs al
INNER JOIN backup_users_deleted bud ON al.user_id = bud.id;

-- Hapus admin messages dari user yang akan dihapus
DELETE am FROM admin_messages am
INNER JOIN backup_users_deleted bud ON am.user_id = bud.id;

-- ========================================
-- 8. HAPUS DATA UTAMA
-- ========================================

-- Hapus desa percobaan
DELETE FROM desa 
WHERE nama_desa LIKE '%test%' 
   OR nama_desa LIKE '%contoh%' 
   OR nama_desa LIKE '%demo%' 
   OR nama_desa LIKE '%percobaan%' 
   OR nama_desa LIKE '%coba%' 
   OR nama_desa LIKE '%sample%'
   OR status = 'nonaktif'
   OR nama_kepala_desa IS NULL 
   OR nama_kepala_desa = '' 
   OR nama_kepala_desa = 'Belum ada'
   OR kecamatan IS NULL 
   OR kecamatan = '';

-- Hapus user test
DELETE FROM users 
WHERE username LIKE '%test%' 
   OR username LIKE '%demo%' 
   OR username LIKE '%contoh%'
   OR nama_lengkap LIKE '%test%'
   OR nama_lengkap LIKE '%demo%'
   OR email LIKE '%test%'
   OR email LIKE '%demo%'
   OR status = 'nonaktif';

-- Hapus kategori tidak terpakai
DELETE k FROM kategori k
LEFT JOIN produk p ON k.id = p.kategori_id
WHERE p.kategori_id IS NULL OR k.status = 'nonaktif';

-- Hapus biaya operasional nonaktif
DELETE FROM biaya_operasional 
WHERE status = 'nonaktif';

-- ========================================
-- 9. HAPUS ACTIVITY LOGS LAMA
-- ========================================

-- Hapus activity logs sebelum tanggal cutoff produksi
DELETE FROM activity_logs 
WHERE created_at < '2025-01-20';

-- Hapus admin messages lama
DELETE FROM admin_messages 
WHERE created_at < '2025-01-20' AND status = 'read';

-- ========================================
-- 10. RESET AUTO INCREMENT
-- ========================================

-- Reset auto increment untuk tabel desa
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM desa);
SET @sql = CONCAT('ALTER TABLE desa AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Reset auto increment untuk tabel users
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM users);
SET @sql = CONCAT('ALTER TABLE users AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Reset auto increment untuk tabel kategori
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM kategori);
SET @sql = CONCAT('ALTER TABLE kategori AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Reset auto increment untuk tabel biaya_operasional
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM biaya_operasional);
SET @sql = CONCAT('ALTER TABLE biaya_operasional AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- 11. LAPORAN PEMBERSIHAN
-- ========================================

-- Tampilkan jumlah desa yang dihapus
SELECT 
    delete_reason,
    COUNT(*) as jumlah_dihapus
FROM backup_desa_deleted 
GROUP BY delete_reason;

-- Tampilkan jumlah users yang dihapus
SELECT 
    delete_reason,
    COUNT(*) as jumlah_dihapus
FROM backup_users_deleted 
GROUP BY delete_reason;

-- Tampilkan jumlah kategori yang dihapus
SELECT 
    delete_reason,
    COUNT(*) as jumlah_dihapus
FROM backup_kategori_deleted 
GROUP BY delete_reason;

-- Tampilkan jumlah biaya operasional yang dihapus
SELECT 
    delete_reason,
    COUNT(*) as jumlah_dihapus
FROM backup_biaya_operasional_deleted 
GROUP BY delete_reason;

-- Tampilkan data yang tersisa
SELECT 
    'desa' as tabel,
    COUNT(*) as total_tersisa
FROM desa
UNION ALL
SELECT 
    'users' as tabel,
    COUNT(*) as total_tersisa
FROM users
UNION ALL
SELECT 
    'kategori' as tabel,
    COUNT(*) as total_tersisa
FROM kategori
UNION ALL
SELECT 
    'biaya_operasional' as tabel,
    COUNT(*) as total_tersisa
FROM biaya_operasional;

-- ========================================
-- CATATAN PENTING:
-- ========================================
-- 1. Script ini akan menghapus data secara permanen
-- 2. Pastikan sudah melakukan backup database lengkap
-- 3. Review dulu data yang akan dihapus dengan menjalankan SELECT query
-- 4. Data yang dihapus akan disimpan di tabel backup_*_deleted
-- 5. Jalankan script ini di environment development terlebih dahulu
-- 6. Perhatikan foreign key constraints yang mungkin ada
-- 7. Script ini sebaiknya dijalankan setelah cleanup_products.sql dan cleanup_transactions.sql
-- ========================================