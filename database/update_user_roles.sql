-- Script untuk mengupdate kolom role di tabel users
-- Mengganti role 'sales' dan 'finance' dengan 'akunting' dan 'supervisor'

USE manajemen_transaksi_desa;

-- Update existing users dengan role 'sales' menjadi 'supervisor'
UPDATE users SET role = 'supervisor' WHERE role = 'sales';

-- Update existing users dengan role 'finance' menjadi 'akunting'
UPDATE users SET role = 'akunting' WHERE role = 'finance';

-- Alter table untuk mengubah ENUM values
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'akunting', 'supervisor', 'teknisi', 'programmer') NOT NULL;

-- Verifikasi perubahan
SELECT id, username, nama_lengkap, role, status FROM users;

-- Tampilkan struktur tabel yang sudah diupdate
DESCRIBE users;