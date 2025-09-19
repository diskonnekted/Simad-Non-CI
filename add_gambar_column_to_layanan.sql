-- =====================================================
-- Script SQL untuk Menambahkan Kolom Gambar ke Tabel Layanan
-- Database: manajemen_transaksi_desa
-- Tabel: layanan
-- =====================================================

-- Gunakan database yang sesuai
USE manajemen_transaksi_desa;

-- Cek apakah kolom gambar sudah ada
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM 
    INFORMATION_SCHEMA.COLUMNS 
WHERE 
    TABLE_SCHEMA = 'manajemen_transaksi_desa' 
    AND TABLE_NAME = 'layanan' 
    AND COLUMN_NAME = 'gambar';

-- Jika query di atas tidak mengembalikan hasil, maka kolom belum ada
-- Jalankan ALTER TABLE berikut untuk menambahkan kolom gambar

ALTER TABLE layanan 
ADD COLUMN gambar VARCHAR(255) NULL 
COMMENT 'Path file gambar layanan' 
AFTER deskripsi;

-- Verifikasi bahwa kolom berhasil ditambahkan
DESCRIBE layanan;

-- Atau gunakan query berikut untuk melihat struktur tabel
SHOW COLUMNS FROM layanan;

-- Optional: Update beberapa record dengan contoh path gambar
-- (Hapus komentar jika ingin menggunakan)
/*
UPDATE layanan 
SET gambar = CONCAT('img/layanan/layanan_', id, '_default.jpg') 
WHERE id IN (1, 2, 3);
*/

-- Tampilkan data layanan untuk verifikasi
SELECT 
    id,
    nama_layanan,
    deskripsi,
    gambar,
    harga,
    status
FROM layanan 
ORDER BY id;

-- =====================================================
-- Catatan Penggunaan:
-- 1. Pastikan Anda memiliki hak akses ALTER pada database
-- 2. Backup database sebelum menjalankan script ini
-- 3. Kolom gambar akan menyimpan path relatif ke file gambar
-- 4. Nilai NULL diizinkan untuk layanan tanpa gambar
-- 5. Maksimal panjang path adalah 255 karakter
-- =====================================================

-- Script selesai
SELECT 'Kolom gambar berhasil ditambahkan ke tabel layanan!' AS status_message;