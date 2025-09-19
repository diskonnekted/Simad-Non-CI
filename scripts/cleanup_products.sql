-- Script Pembersihan Data Produk Percobaan
-- Tanggal: 2025-01-20
-- Deskripsi: Script untuk menghapus data produk percobaan sebelum masuk produksi

-- PERINGATAN: Pastikan sudah melakukan backup database sebelum menjalankan script ini!

-- ========================================
-- 1. BACKUP DATA YANG AKAN DIHAPUS
-- ========================================

-- Buat tabel backup untuk produk yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_produk_deleted (
    id INT PRIMARY KEY,
    kode_produk VARCHAR(50),
    nama_produk VARCHAR(255),
    kategori_id INT,
    harga_satuan DECIMAL(15,2),
    stok_tersedia INT,
    status ENUM('aktif', 'nonaktif'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- Backup produk yang mengandung kata test/demo/contoh
INSERT INTO backup_produk_deleted 
(id, kode_produk, nama_produk, kategori_id, harga_satuan, stok_tersedia, status, created_at, updated_at, delete_reason)
SELECT 
    id, kode_produk, nama_produk, kategori_id, harga_satuan, stok_tersedia, status, created_at, updated_at,
    'Produk percobaan - mengandung kata test/demo/contoh'
FROM produk 
WHERE nama_produk LIKE '%test%' 
   OR nama_produk LIKE '%contoh%' 
   OR nama_produk LIKE '%demo%' 
   OR nama_produk LIKE '%percobaan%' 
   OR nama_produk LIKE '%coba%' 
   OR nama_produk LIKE '%sample%';

-- Backup produk dengan status nonaktif
INSERT INTO backup_produk_deleted 
(id, kode_produk, nama_produk, kategori_id, harga_satuan, stok_tersedia, status, created_at, updated_at, delete_reason)
SELECT 
    id, kode_produk, nama_produk, kategori_id, harga_satuan, stok_tersedia, status, created_at, updated_at,
    'Produk nonaktif'
FROM produk 
WHERE status = 'nonaktif'
  AND id NOT IN (SELECT id FROM backup_produk_deleted);

-- Backup produk dengan stok 0 dan tidak ada transaksi
INSERT INTO backup_produk_deleted 
(id, kode_produk, nama_produk, kategori_id, harga_satuan, stok_tersedia, status, created_at, updated_at, delete_reason)
SELECT 
    p.id, p.kode_produk, p.nama_produk, p.kategori_id, p.harga_satuan, p.stok_tersedia, p.status, p.created_at, p.updated_at,
    'Produk stok 0 tanpa transaksi'
FROM produk p
LEFT JOIN transaksi_detail td ON p.id = td.produk_id
WHERE p.stok_tersedia = 0 
  AND td.produk_id IS NULL
  AND p.id NOT IN (SELECT id FROM backup_produk_deleted);

-- ========================================
-- 2. HAPUS DATA TERKAIT PRODUK
-- ========================================

-- Hapus detail transaksi untuk produk yang akan dihapus
DELETE td FROM transaksi_detail td
INNER JOIN backup_produk_deleted bpd ON td.produk_id = bpd.id;

-- Hapus relasi kategori_produk jika ada
DELETE kp FROM kategori_produk kp
INNER JOIN backup_produk_deleted bpd ON kp.produk_id = bpd.id;

-- Hapus data mutasi stok untuk produk yang akan dihapus
DELETE ms FROM mutasi_stok ms
INNER JOIN backup_produk_deleted bpd ON ms.produk_id = bpd.id;

-- ========================================
-- 3. HAPUS DATA PRODUK UTAMA
-- ========================================

-- Hapus produk yang mengandung kata test/demo/contoh
DELETE FROM produk 
WHERE nama_produk LIKE '%test%' 
   OR nama_produk LIKE '%contoh%' 
   OR nama_produk LIKE '%demo%' 
   OR nama_produk LIKE '%percobaan%' 
   OR nama_produk LIKE '%coba%' 
   OR nama_produk LIKE '%sample%';

-- Hapus produk dengan status nonaktif
DELETE FROM produk 
WHERE status = 'nonaktif';

-- Hapus produk dengan stok 0 dan tidak ada transaksi
DELETE p FROM produk p
LEFT JOIN transaksi_detail td ON p.id = td.produk_id
WHERE p.stok_tersedia = 0 
  AND td.produk_id IS NULL;

-- ========================================
-- 4. RESET AUTO INCREMENT
-- ========================================

-- Reset auto increment untuk tabel produk
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM produk);
SET @sql = CONCAT('ALTER TABLE produk AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- 5. LAPORAN PEMBERSIHAN
-- ========================================

-- Tampilkan jumlah data yang dihapus
SELECT 
    delete_reason,
    COUNT(*) as jumlah_dihapus
FROM backup_produk_deleted 
GROUP BY delete_reason;

-- Tampilkan total produk yang tersisa
SELECT COUNT(*) as total_produk_tersisa FROM produk;

-- Tampilkan produk yang masih aktif
SELECT 
    COUNT(*) as produk_aktif,
    SUM(stok_tersedia) as total_stok
FROM produk 
WHERE status = 'aktif';

-- ========================================
-- CATATAN PENTING:
-- ========================================
-- 1. Script ini akan menghapus data secara permanen
-- 2. Pastikan sudah melakukan backup database lengkap
-- 3. Review dulu data yang akan dihapus dengan menjalankan SELECT query
-- 4. Data yang dihapus akan disimpan di tabel backup_produk_deleted
-- 5. Jalankan script ini di environment development terlebih dahulu
-- ========================================