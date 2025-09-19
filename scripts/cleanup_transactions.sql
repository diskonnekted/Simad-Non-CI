-- Script Pembersihan Data Transaksi Percobaan
-- Tanggal: 2025-01-20
-- Deskripsi: Script untuk menghapus data transaksi percobaan sebelum masuk produksi

-- PERINGATAN: Pastikan sudah melakukan backup database sebelum menjalankan script ini!

-- ========================================
-- 1. BACKUP DATA YANG AKAN DIHAPUS
-- ========================================

-- Buat tabel backup untuk transaksi yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_transaksi_deleted (
    id INT PRIMARY KEY,
    nomor_invoice VARCHAR(50),
    desa_id INT,
    total_amount DECIMAL(15,2),
    status_transaksi ENUM('draft', 'pending', 'completed', 'cancelled'),
    catatan TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- Buat tabel backup untuk detail transaksi yang akan dihapus
CREATE TABLE IF NOT EXISTS backup_transaksi_detail_deleted (
    id INT PRIMARY KEY,
    transaksi_id INT,
    produk_id INT,
    quantity INT,
    harga_satuan DECIMAL(15,2),
    subtotal DECIMAL(15,2),
    created_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_reason VARCHAR(255)
);

-- Backup transaksi dengan status draft
INSERT INTO backup_transaksi_deleted 
(id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at, delete_reason)
SELECT 
    id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at,
    'Transaksi draft'
FROM transaksi 
WHERE status_transaksi = 'draft';

-- Backup transaksi dengan catatan test/percobaan
INSERT INTO backup_transaksi_deleted 
(id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at, delete_reason)
SELECT 
    id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at,
    'Transaksi percobaan - catatan mengandung test/percobaan'
FROM transaksi 
WHERE (catatan LIKE '%test%' OR catatan LIKE '%percobaan%' OR catatan LIKE '%coba%')
  AND id NOT IN (SELECT id FROM backup_transaksi_deleted);

-- Backup transaksi dengan amount kecil (kemungkinan test)
INSERT INTO backup_transaksi_deleted 
(id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at, delete_reason)
SELECT 
    id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at,
    'Transaksi amount kecil (kemungkinan test)'
FROM transaksi 
WHERE total_amount < 50000
  AND id NOT IN (SELECT id FROM backup_transaksi_deleted);

-- Backup transaksi yang terkait dengan desa percobaan
INSERT INTO backup_transaksi_deleted 
(id, nomor_invoice, desa_id, total_amount, status_transaksi, catatan, created_at, updated_at, delete_reason)
SELECT 
    t.id, t.nomor_invoice, t.desa_id, t.total_amount, t.status_transaksi, t.catatan, t.created_at, t.updated_at,
    'Transaksi dari desa percobaan'
FROM transaksi t
INNER JOIN desa d ON t.desa_id = d.id
WHERE (d.nama_desa LIKE '%test%' 
    OR d.nama_desa LIKE '%contoh%' 
    OR d.nama_desa LIKE '%demo%' 
    OR d.nama_desa LIKE '%percobaan%'
    OR d.status = 'nonaktif')
  AND t.id NOT IN (SELECT id FROM backup_transaksi_deleted);

-- Backup detail transaksi yang akan dihapus
INSERT INTO backup_transaksi_detail_deleted 
(id, transaksi_id, produk_id, quantity, harga_satuan, subtotal, created_at, delete_reason)
SELECT 
    td.id, td.transaksi_id, td.produk_id, td.quantity, td.harga_satuan, td.subtotal, td.created_at,
    'Detail dari transaksi yang dihapus'
FROM transaksi_detail td
INNER JOIN backup_transaksi_deleted btd ON td.transaksi_id = btd.id;

-- ========================================
-- 2. HAPUS DATA TERKAIT TRANSAKSI
-- ========================================

-- Hapus pembayaran terkait transaksi yang akan dihapus
DELETE p FROM pembayaran p
INNER JOIN backup_transaksi_deleted btd ON p.transaksi_id = btd.id;

-- Hapus mutasi kas terkait transaksi yang akan dihapus
DELETE mk FROM mutasi_kas mk
INNER JOIN backup_transaksi_deleted btd ON mk.transaksi_id = btd.id;

-- Hapus piutang terkait transaksi yang akan dihapus
DELETE pi FROM piutang pi
INNER JOIN backup_transaksi_deleted btd ON pi.transaksi_id = btd.id;

-- Hapus detail transaksi
DELETE td FROM transaksi_detail td
INNER JOIN backup_transaksi_deleted btd ON td.transaksi_id = btd.id;

-- ========================================
-- 3. HAPUS DATA TRANSAKSI UTAMA
-- ========================================

-- Hapus transaksi dengan status draft
DELETE FROM transaksi 
WHERE status_transaksi = 'draft';

-- Hapus transaksi dengan catatan test/percobaan
DELETE FROM transaksi 
WHERE catatan LIKE '%test%' 
   OR catatan LIKE '%percobaan%' 
   OR catatan LIKE '%coba%';

-- Hapus transaksi dengan amount kecil
DELETE FROM transaksi 
WHERE total_amount < 50000;

-- Hapus transaksi dari desa percobaan
DELETE t FROM transaksi t
INNER JOIN desa d ON t.desa_id = d.id
WHERE d.nama_desa LIKE '%test%' 
   OR d.nama_desa LIKE '%contoh%' 
   OR d.nama_desa LIKE '%demo%' 
   OR d.nama_desa LIKE '%percobaan%'
   OR d.status = 'nonaktif';

-- ========================================
-- 4. RESET AUTO INCREMENT
-- ========================================

-- Reset auto increment untuk tabel transaksi
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM transaksi);
SET @sql = CONCAT('ALTER TABLE transaksi AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Reset auto increment untuk tabel transaksi_detail
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM transaksi_detail);
SET @sql = CONCAT('ALTER TABLE transaksi_detail AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- 5. UPDATE NOMOR INVOICE
-- ========================================

-- Reset nomor invoice untuk transaksi yang tersisa (opsional)
-- Uncomment jika ingin mereset nomor invoice
/*
SET @counter = 0;
UPDATE transaksi 
SET nomor_invoice = CONCAT('INV-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD((@counter := @counter + 1), 4, '0'))
ORDER BY created_at;
*/

-- ========================================
-- 6. LAPORAN PEMBERSIHAN
-- ========================================

-- Tampilkan jumlah transaksi yang dihapus
SELECT 
    delete_reason,
    COUNT(*) as jumlah_dihapus,
    SUM(total_amount) as total_amount_dihapus
FROM backup_transaksi_deleted 
GROUP BY delete_reason;

-- Tampilkan jumlah detail transaksi yang dihapus
SELECT 
    COUNT(*) as detail_transaksi_dihapus,
    SUM(subtotal) as total_subtotal_dihapus
FROM backup_transaksi_detail_deleted;

-- Tampilkan total transaksi yang tersisa
SELECT 
    COUNT(*) as total_transaksi_tersisa,
    SUM(total_amount) as total_amount_tersisa
FROM transaksi;

-- Tampilkan transaksi berdasarkan status
SELECT 
    status_transaksi,
    COUNT(*) as jumlah,
    SUM(total_amount) as total_amount
FROM transaksi 
GROUP BY status_transaksi;

-- Tampilkan transaksi terbaru yang tersisa
SELECT 
    nomor_invoice,
    total_amount,
    status_transaksi,
    created_at
FROM transaksi 
ORDER BY created_at DESC 
LIMIT 10;

-- ========================================
-- CATATAN PENTING:
-- ========================================
-- 1. Script ini akan menghapus data secara permanen
-- 2. Pastikan sudah melakukan backup database lengkap
-- 3. Review dulu data yang akan dihapus dengan menjalankan SELECT query
-- 4. Data yang dihapus akan disimpan di tabel backup_transaksi_deleted
-- 5. Jalankan script ini di environment development terlebih dahulu
-- 6. Perhatikan foreign key constraints yang mungkin ada
-- ========================================