-- Script Helper untuk Import SQL Database
-- Mengatasi berbagai konflik yang mungkin terjadi saat import

-- ========================================
-- SOLUSI UNTUK ERROR #1050 - Table already exists
-- ========================================

-- 1. Cek apakah tabel sudah ada sebelum membuat
SET @table_exists = 0;
SELECT COUNT(*) INTO @table_exists 
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'activity_logs';

-- 2. Buat tabel hanya jika belum ada
SET @sql = IF(@table_exists = 0, 
    'CREATE TABLE `activity_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabel activity_logs sudah ada, skip pembuatan" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- ALTERNATIF SOLUSI LAINNYA
-- ========================================

-- OPSI A: Drop dan buat ulang (HATI-HATI: Data akan hilang)
-- DROP TABLE IF EXISTS `activity_logs`;
-- CREATE TABLE `activity_logs` (...)

-- OPSI B: Gunakan CREATE TABLE IF NOT EXISTS (Recommended)
-- CREATE TABLE IF NOT EXISTS `activity_logs` (...)

-- OPSI C: Rename tabel yang ada, lalu buat baru
-- RENAME TABLE `activity_logs` TO `activity_logs_backup`;
-- CREATE TABLE `activity_logs` (...)

-- ========================================
-- SCRIPT UNTUK TABEL LAINNYA YANG MUNGKIN KONFLIK
-- ========================================

-- Untuk tabel users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','akunting','supervisor','teknisi','programmer') NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Untuk tabel desa
CREATE TABLE IF NOT EXISTS `desa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_desa` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `kecamatan` varchar(50) NOT NULL,
  `kabupaten` varchar(50) NOT NULL,
  `provinsi` varchar(50) NOT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `nama_kepala_desa` varchar(100) DEFAULT NULL,
  `no_hp_kepala_desa` varchar(20) DEFAULT NULL,
  `nama_sekdes` varchar(100) DEFAULT NULL,
  `no_hp_sekdes` varchar(20) DEFAULT NULL,
  `nama_admin_it` varchar(100) DEFAULT NULL,
  `no_hp_admin_it` varchar(20) DEFAULT NULL,
  `email_desa` varchar(100) DEFAULT NULL,
  `kategori` enum('baru','rutin','prioritas') DEFAULT 'baru',
  `tingkat_digitalisasi` enum('rendah','sedang','tinggi') DEFAULT 'rendah',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- CARA PENGGUNAAN
-- ========================================

/*
1. UNTUK MENGATASI ERROR #1050:
   - Ganti CREATE TABLE dengan CREATE TABLE IF NOT EXISTS
   - Atau jalankan script ini sebelum import SQL utama
   - Atau gunakan script check_table_conflicts.php

2. UNTUK IMPORT SQL YANG AMAN:
   - Backup database terlebih dahulu
   - Cek struktur tabel yang sudah ada
   - Gunakan IF NOT EXISTS untuk semua CREATE TABLE
   - Gunakan INSERT IGNORE atau ON DUPLICATE KEY UPDATE untuk data

3. CONTOH MODIFIKASI SCRIPT SQL:
   Dari: CREATE TABLE `activity_logs` (...)
   Ke:   CREATE TABLE IF NOT EXISTS `activity_logs` (...)

4. UNTUK CEK KONFLIK:
   - Jalankan: php check_table_conflicts.php
   - Atau akses: http://localhost:8000/check_table_conflicts.php?run=1
*/

-- Tampilkan status tabel setelah script dijalankan
SELECT 
    table_name as 'Nama Tabel',
    table_rows as 'Jumlah Baris',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Ukuran (MB)'
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('activity_logs', 'users', 'desa')
ORDER BY table_name;