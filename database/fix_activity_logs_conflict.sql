-- Script untuk mengatasi konflik tabel activity_logs yang sudah ada
-- Pilih salah satu opsi di bawah ini:

-- OPSI 1: Drop tabel yang ada dan buat ulang (HATI-HATI: Data akan hilang)
-- Uncomment baris di bawah jika ingin menghapus tabel yang ada
-- DROP TABLE IF EXISTS `activity_logs`;

-- OPSI 2: Buat tabel hanya jika belum ada (Recommended)
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OPSI 3: Alter tabel yang ada untuk memastikan struktur sesuai
-- Uncomment baris-baris di bawah jika ingin memperbarui struktur tabel yang ada

-- Pastikan kolom id adalah AUTO_INCREMENT dan PRIMARY KEY
-- ALTER TABLE `activity_logs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- ALTER TABLE `activity_logs` ADD PRIMARY KEY (`id`) IF NOT EXISTS;

-- Tambahkan index untuk performa yang lebih baik
-- ALTER TABLE `activity_logs` ADD INDEX `idx_user_id` (`user_id`) IF NOT EXISTS;
-- ALTER TABLE `activity_logs` ADD INDEX `idx_activity_type` (`activity_type`) IF NOT EXISTS;
-- ALTER TABLE `activity_logs` ADD INDEX `idx_created_at` (`created_at`) IF NOT EXISTS;

-- Verifikasi struktur tabel
DESCRIBE `activity_logs`;

-- Tampilkan jumlah record yang ada
SELECT COUNT(*) as total_records FROM `activity_logs`;

-- CATATAN PENTING:
-- 1. Error #1050 terjadi karena tabel 'activity_logs' sudah ada di database
-- 2. Gunakan OPSI 2 (CREATE TABLE IF NOT EXISTS) untuk menghindari error ini
-- 3. Jika ingin mengganti struktur tabel, gunakan OPSI 3 (ALTER TABLE)
-- 4. OPSI 1 hanya digunakan jika yakin ingin menghapus semua data yang ada

-- SOLUSI CEPAT:
-- Ganti perintah CREATE TABLE dengan CREATE TABLE IF NOT EXISTS di file SQL yang diimport