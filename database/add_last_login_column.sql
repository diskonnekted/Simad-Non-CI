-- Script untuk menambahkan kolom last_login ke tabel users
-- Jalankan script ini untuk memperbaiki error "Undefined array key 'last_login'"

USE manajemen_transaksi_desa;

-- Tambahkan kolom last_login ke tabel users
ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at;

-- Verifikasi struktur tabel
DESCRIBE users;