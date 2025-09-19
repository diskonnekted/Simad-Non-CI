-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 14 Sep 2025 pada 19.28
-- Versi server: 10.11.14-MariaDB-cll-lve
-- Versi PHP: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `simadorbitdev_smd`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `activity_type`, `description`, `target_table`, `target_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'Admin berhasil login ke sistem', 'users', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-26 01:07:21'),
(2, 1, 'create_desa', 'Admin menambahkan desa baru: Desa Contoh', 'desa', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-26 01:07:21'),
(3, 1, 'reset_pin', 'Admin mereset PIN untuk Desa Contoh', 'desa', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-26 01:07:21'),
(4, 1, 'reset_pin_desa', 'Reset PIN untuk desa Aribaya, Pagentan', 'desa', 6, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 01:17:25'),
(5, 1, 'reset_pin_desa', 'Reset PIN untuk desa kalibombong, ', 'desa', 188, '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:52:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` int(11) NOT NULL,
  `maintenance_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin_messages`
--

INSERT INTO `admin_messages` (`id`, `maintenance_id`, `admin_id`, `message`, `created_at`, `updated_at`) VALUES
(1, 26, 1, 'ini adalah tes halaman maintenance', '2025-09-11 19:42:42', '2025-09-11 19:42:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bank`
--

CREATE TABLE `bank` (
  `id` int(11) NOT NULL,
  `kode_bank` varchar(10) NOT NULL,
  `nama_bank` varchar(100) NOT NULL,
  `jenis_bank` enum('bkk','bank_umum','cash') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `nomor_rekening` varchar(50) DEFAULT NULL,
  `atas_nama` varchar(100) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `bank`
--

INSERT INTO `bank` (`id`, `kode_bank`, `nama_bank`, `jenis_bank`, `deskripsi`, `nomor_rekening`, `atas_nama`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BKK001', 'BKK Clasnet', 'bkk', 'Bank Kredit Kecamatan Clasnet untuk transaksi desa', NULL, NULL, 'aktif', '2025-09-09 05:50:45', '2025-09-09 05:50:45'),
(2, 'BKK002', 'BKK BBS', 'bkk', 'Bank Kredit Kecamatan BBS untuk transaksi desa', NULL, NULL, 'aktif', '2025-09-09 05:50:45', '2025-09-09 05:50:45'),
(3, 'BJC001', 'Bank Jateng Clasnet', 'bank_umum', 'Bank Jawa Tengah cabang Clasnet', NULL, NULL, 'aktif', '2025-09-09 05:50:45', '2025-09-09 05:50:45'),
(4, 'CASH01', 'Cash di Kantor', 'cash', 'Pembayaran tunai langsung di kantor', NULL, NULL, 'aktif', '2025-09-09 05:50:45', '2025-09-09 05:50:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `biaya_operasional`
--

CREATE TABLE `biaya_operasional` (
  `id` int(11) NOT NULL,
  `kode_biaya` varchar(20) NOT NULL,
  `nama_biaya` varchar(100) NOT NULL,
  `kategori` enum('transportasi','akomodasi','konsumsi','komunikasi','peralatan','administrasi','lainnya') DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `tarif_standar` decimal(15,2) NOT NULL,
  `satuan` varchar(20) NOT NULL COMMENT 'per km, per hari, per orang, dll',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `biaya_operasional`
--

INSERT INTO `biaya_operasional` (`id`, `kode_biaya`, `nama_biaya`, `kategori`, `deskripsi`, `tarif_standar`, `satuan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BOP001', 'Bensin Motor', 'transportasi', 'Biaya bahan bakar motor untuk kunjungan', 15000.00, 'per liter', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(2, 'BOP002', 'Bensin Mobil', 'transportasi', 'Biaya bahan bakar mobil untuk kunjungan', 18000.00, 'per liter', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(3, 'BOP003', 'Tol', 'transportasi', 'Biaya tol perjalanan', 25000.00, 'per trip', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(4, 'BOP004', 'Parkir', 'transportasi', 'Biaya parkir kendaraan', 5000.00, 'per lokasi', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(5, 'BOP005', 'Makan Siang', 'konsumsi', 'Biaya makan siang tim', 25000.00, 'per orang', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(6, 'BOP006', 'Snack', 'konsumsi', 'Biaya snack untuk tim', 10000.00, 'per orang', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(7, 'BOP007', 'Hotel Budget', 'akomodasi', 'Biaya menginap hotel budget', 200000.00, 'per malam', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(8, 'BOP008', 'Hotel Standard', 'akomodasi', 'Biaya menginap hotel standard', 350000.00, 'per malam', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(9, 'BOP009', 'Pulsa Internet', 'komunikasi', 'Biaya pulsa internet untuk koordinasi', 50000.00, 'per hari', 'aktif', '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(10, 'SID001', 'Setup Sistem Informasi Desa', 'lainnya', 'Pembayaran untuk install. setup database dan seting awal SID.', 500000.00, 'Per web', 'aktif', '2025-08-28 14:51:58', '2025-08-28 14:52:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `desa`
--

CREATE TABLE `desa` (
  `id` int(11) NOT NULL,
  `nama_desa` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `kecamatan` varchar(50) NOT NULL,
  `kabupaten` varchar(50) NOT NULL,
  `provinsi` varchar(50) NOT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `nama_kepala_desa` varchar(100) DEFAULT NULL,
  `jabatan_kepala_desa` varchar(100) DEFAULT NULL,
  `no_hp_kepala_desa` varchar(20) DEFAULT NULL,
  `nama_sekdes` varchar(100) DEFAULT NULL,
  `no_hp_sekdes` varchar(20) DEFAULT NULL,
  `nama_admin_it` varchar(100) DEFAULT NULL,
  `no_hp_admin_it` varchar(20) DEFAULT NULL,
  `email_desa` varchar(100) DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `kategori` enum('baru','rutin','prioritas') DEFAULT 'baru',
  `tingkat_digitalisasi` enum('rendah','sedang','tinggi') DEFAULT 'rendah',
  `limit_kredit` decimal(15,2) DEFAULT 0.00,
  `foto_kantor` varchar(255) DEFAULT NULL,
  `catatan_khusus` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `desa`
--

INSERT INTO `desa` (`id`, `nama_desa`, `alamat`, `kecamatan`, `kabupaten`, `provinsi`, `kode_pos`, `nama_kepala_desa`, `jabatan_kepala_desa`, `no_hp_kepala_desa`, `nama_sekdes`, `no_hp_sekdes`, `nama_admin_it`, `no_hp_admin_it`, `email_desa`, `pin`, `kategori`, `tingkat_digitalisasi`, `limit_kredit`, `foto_kantor`, `catatan_khusus`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Kalisemi', 'Kalisemi', 'Banjarnegara', 'Banjarnegara', 'Jawa Tengah', '6660', 'Bihun', 'Kepala Desa', '097689768567', NULL, NULL, NULL, NULL, 'email@email.com', NULL, 'baru', 'rendah', 0.00, NULL, 'Kosong', 'nonaktif', '2025-08-24 08:40:25', '2025-09-11 22:43:18'),
(2, 'Gsdfgdafg', 'ghss sh', 'Fgdfsg', 'Sdfgsdg', 'Jawa Barat', '4566', 'yrtyrty r', 'Kepala Desa', '086575675', NULL, NULL, NULL, NULL, 'hhh@bbbb.com', NULL, 'baru', 'rendah', 0.00, NULL, '', 'nonaktif', '2025-08-24 08:41:29', '2025-08-25 04:26:49'),
(3, 'Adipasir', 'Desa Adipasir, Kecamatan Rakit, Kabupaten Banjarnegara', 'Rakit', 'Banjarnegara', 'Jawa Tengah', '44545', 'Belum ada', 'Sekdes', '087567858', NULL, NULL, NULL, NULL, 'adipasir@kbk.com', NULL, 'baru', 'rendah', 0.00, NULL, 'Belum ada catatan.', 'aktif', '2025-08-24 15:36:05', '2025-09-11 10:59:26'),
(4, 'Ambal', 'Desa Ambal, Kecamatan Karangkobar, Kabupaten Banjarnegara', 'Karangkobar', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(5, 'Ampelsari', 'Desa Ampelsari, Kecamatan Banjarnegara, Kabupaten Banjarnegara', 'Banjarnegara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(6, 'Aribaya', 'Desa Aribaya, Kecamatan Pagentan, Kabupaten Banjarnegara', 'Pagentan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$WS5/OfwnZAlfZ/9LRCyMl.mfaJC.AZ83X5H534zvdrWEJsmunkr3q', 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-26 01:17:25'),
(7, 'Asinan', 'Desa Asinan, Kecamatan Kalibening, Kabupaten Banjarnegara', 'Kalibening', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(8, 'Babadan', 'Desa Babadan, Kecamatan Pagentan, Kabupaten Banjarnegara', 'Pagentan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(9, 'Badakarya', 'Desa Badakarya, Kecamatan Punggelan, Kabupaten Banjarnegara', 'Punggelan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(10, 'Badamita', 'Desa Badamita, Kecamatan Rakit, Kabupaten Banjarnegara', 'Rakit', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(11, 'Bakal', 'Desa Bakal, Kecamatan Batur, Kabupaten Banjarnegara', 'Batur', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(12, 'Balun', 'Desa Balun, Kecamatan Wanayasa, Kabupaten Banjarnegara', 'Wanayasa', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(13, 'Bandingan', 'Desa Bandingan, Kecamatan Bawang, Kabupaten Banjarnegara', 'Bawang', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(14, 'Bandingan', 'Desa Bandingan, Kecamatan Rakit, Kabupaten Banjarnegara', 'Rakit', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(15, 'Bandingan', 'Desa Bandingan, Kecamatan Sigaluh, Kabupaten Banjarnegara', 'Sigaluh', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(16, 'Banjarkulon', 'Desa Banjarkulon, Kecamatan Banjarmangu, Kabupaten Banjarnegara', 'Banjarmangu', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(17, 'Banjarmangu', 'Desa Banjarmangu, Kecamatan Banjarmangu, Kabupaten Banjarnegara', 'Banjarmangu', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:05', '2025-08-25 04:24:32'),
(18, 'Banjengan', 'Desa Banjengan, Kecamatan Mandiraja, Kabupaten Banjarnegara', 'Mandiraja', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(19, 'Bantar', 'Desa Bantar, Kecamatan Wanayasa, Kabupaten Banjarnegara', 'Wanayasa', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(20, 'Bantarwaru', 'Desa Bantarwaru, Kecamatan Madukara, Kabupaten Banjarnegara', 'Madukara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(21, 'Batur', 'Desa Batur, Kecamatan Batur, Kabupaten Banjarnegara', 'Batur', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(22, 'Bawang', 'Desa Bawang, Kecamatan Bawang, Kabupaten Banjarnegara', 'Bawang', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(23, 'Bedana', 'Desa Bedana, Kecamatan Kalibening, Kabupaten Banjarnegara', 'Kalibening', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(24, 'Beji', 'Desa Beji, Kecamatan Banjarmangu, Kabupaten Banjarnegara', 'Banjarmangu', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(25, 'Beji-Pandanarum', 'Desa Beji, Kecamatan Pandanarum, Kabupaten Banjarnegara', 'Pandanarum', 'Banjarnegara', 'Jawa Tengah', '', 'Belum Ada', 'Kepala Desa', '0000000000', NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, '', 'aktif', '2025-08-24 15:36:06', '2025-09-02 06:00:43'),
(26, 'Beji-Pejawaran', 'Desa Beji, Kecamatan Pejawaran, Kabupaten Banjarnegara', 'Pejawaran', 'Banjarnegara', 'Jawa Tengah', '', 'Belum Ada', 'Kepala Desa', '0000000000', NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, '', 'aktif', '2025-08-24 15:36:06', '2025-09-02 05:59:06'),
(27, 'Berta', 'Desa Berta, Kecamatan Susukan, Kabupaten Banjarnegara', 'Susukan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(28, 'Binangun', 'Desa Binangun, Kecamatan Karangkobar, Kabupaten Banjarnegara', 'Karangkobar', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(29, 'Binorong', 'Desa Binorong, Kecamatan Bawang, Kabupaten Banjarnegara', 'Bawang', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(30, 'Biting', 'Desa Biting, Kecamatan Pejawaran, Kabupaten Banjarnegara', 'Pejawaran', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(31, 'Blambangan', 'Desa Blambangan, Kecamatan Bawang, Kabupaten Banjarnegara', 'Bawang', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(32, 'Blimbing', 'Desa Blimbing, Kecamatan Mandiraja, Kabupaten Banjarnegara', 'Mandiraja', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(33, 'Blitar', 'Desa Blitar, Kecamatan Madukara, Kabupaten Banjarnegara', 'Madukara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(34, 'Bojanegara', 'Desa Bojanegara, Kecamatan Sigaluh, Kabupaten Banjarnegara', 'Sigaluh', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(35, 'Bondolharjo', 'Desa Bondolharjo, Kecamatan Punggelan, Kabupaten Banjarnegara', 'Punggelan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(36, 'Brengkok', 'Desa Brengkok, Kecamatan Susukan, Kabupaten Banjarnegara', 'Susukan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(37, 'Candiwulan', 'Desa Candiwulan, Kecamatan Mandiraja, Kabupaten Banjarnegara', 'Mandiraja', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(38, 'Cendana', 'Desa Cendana, Kecamatan Banjarnegara, Kabupaten Banjarnegara', 'Banjarnegara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(39, 'Clapar', 'Desa Clapar, Kecamatan Madukara, Kabupaten Banjarnegara', 'Madukara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(40, 'Condongcampur', 'Desa Condongcampur, Kecamatan Pejawaran, Kabupaten Banjarnegara', 'Pejawaran', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(41, 'Danakerta', 'Desa Danakerta, Kecamatan Punggelan, Kabupaten Banjarnegara', 'Punggelan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(42, 'Danaraja', 'Desa Danaraja, Kecamatan Purwanegara, Kabupaten Banjarnegara', 'Purwanegara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(43, 'Darmayasa', 'Desa Darmayasa, Kecamatan Pejawaran, Kabupaten Banjarnegara', 'Pejawaran', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(44, 'Dawuhan', 'Desa Dawuhan, Kecamatan Madukara, Kabupaten Banjarnegara', 'Madukara', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:06', '2025-08-25 04:24:32'),
(45, 'Dawuhan', 'Desa Dawuhan, Kecamatan Wanayasa, Kabupaten Banjarnegara', 'Wanayasa', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(46, 'Depok', 'Desa Depok, Kecamatan Bawang, Kabupaten Banjarnegara', 'Bawang', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(47, 'Derik', 'Desa Derik, Kecamatan Susukan, Kabupaten Banjarnegara', 'Susukan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(48, 'Dermasari', 'Desa Dermasari, Kecamatan Susukan, Kabupaten Banjarnegara', 'Susukan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(49, 'Dieng Kulon', 'Desa Dieng Kulon, Kecamatan Batur, Kabupaten Banjarnegara', 'Batur', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(50, 'Duren', 'Desa Duren, Kecamatan Pagedongan, Kabupaten Banjarnegara', 'Pagedongan', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(51, 'Gelang', 'Desa Gelang, Kecamatan Rakit, Kabupaten Banjarnegara', 'Rakit', 'Banjarnegara', 'Jawa Tengah', NULL, NULL, 'Kepala Desa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-24 15:36:07', '2025-08-25 04:24:32'),
(52, 'Gemuruh', '', 'Bawang', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:02', '2025-09-12 01:38:41'),
(53, 'Gembongan', '', 'Sigaluh', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:02', '2025-09-12 01:38:41'),
(54, 'Panawaren', '', 'Sigaluh', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:02', '2025-09-12 01:38:41'),
(55, 'Prigi', '', 'Sigaluh', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(56, 'Pringamba', '', 'Sigaluh', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 02:20:03'),
(57, 'Wanacipta', '', 'Sigaluh', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(58, 'Gripit', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(59, 'Jenggawur', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(60, 'Kalilunjar', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(61, 'Pekandangan', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(63, 'Sijeruk', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(64, 'Sipedang', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(65, 'Kutayasa', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(66, 'Limbangan', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(67, 'Madukara', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(68, 'Pekauman', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(69, 'Penawangan', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:03', '2025-09-12 01:38:41'),
(70, 'Sered', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:38:42'),
(71, 'Gumiwang', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 02:20:03'),
(72, 'Kalipelus', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:38:42'),
(73, 'Karanganyar', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:38:42'),
(74, 'Mertasari', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:38:42'),
(75, 'Petir', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:38:42'),
(76, 'Pucungbedug', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:38:42'),
(77, 'Karangkemiri', '', 'Wanadadi', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:40:59'),
(78, 'Kasilib', '', 'Wanadadi', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:40:59'),
(79, 'Linggasari', '', 'Wanadadi', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:40:59'),
(80, 'Tapen', '', 'Wanadadi', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:40:59'),
(82, 'Jembangan', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:40:59'),
(83, 'Karangsari', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:04', '2025-09-12 01:40:59'),
(84, 'Kecepit', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(85, 'Klapa', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(86, 'Mlaya', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(87, 'Petuguran', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(88, 'Punggelan', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(89, 'Purwasana', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(90, 'Sambong', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(91, 'Sawangan', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(92, 'Sidarata', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(93, 'Tanjungtirta', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(94, 'Tlaga', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:40:59'),
(95, 'Tribuana', '', 'Punggelan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:46:41'),
(96, 'Kebakalan', '', 'Mandiraja', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:46:41'),
(97, 'Panggisari', '', 'Mandiraja', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:05', '2025-09-12 01:46:41'),
(98, 'Kertayasa', '', 'Mandiraja', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(99, 'Purwasaba', '', 'Mandiraja', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(100, 'Jlegong', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(101, 'Karanggondang', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 02:20:03'),
(102, 'Leksana', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 02:20:03'),
(103, 'Paweden', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(104, 'Slatri', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(105, 'Jatilawang', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(106, 'Karangtengah', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(107, 'Kasimpar', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(108, 'Kubang', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(109, 'Legoksayem', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:06', '2025-09-12 01:46:41'),
(110, 'Pagergunung', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(111, 'Pandansari', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(112, 'Penanggungan', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(113, 'Pesantren', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(114, 'Suwidak', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(115, 'Tempuran', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(116, 'Wanaraja', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(117, 'Wanayasa', '', 'Wanayasa', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(118, 'Kincang', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(119, 'Lengkong', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(120, 'Luwung', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(121, 'Pingit', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:07', '2025-09-12 01:46:41'),
(122, 'Rakit', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 02:20:03'),
(123, 'Situwangi', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:41'),
(124, 'Tanjunganom', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:42'),
(125, 'Panerusankulon', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:42'),
(126, 'Karangsalam', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:42'),
(127, 'Giritirta', '', 'Pejawaran', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:42'),
(128, 'Pejawaran', '', 'Pejawaran', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:42'),
(129, 'Sarwodadi', '', 'Pejawaran', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:46:42'),
(130, 'Semangkung', '', 'Kalibening', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 02:18:36'),
(131, 'Gumingsir', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:49:22'),
(132, 'Karekan', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:49:22'),
(133, 'Kasmaran', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:49:22'),
(134, 'Majasari', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:49:22'),
(135, 'Pagentan', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:08', '2025-09-12 01:49:22'),
(136, 'Plumbungan', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:09', '2025-09-12 01:49:22'),
(137, 'Sumberejo', '', 'Batur', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:09', '2025-09-12 02:20:03'),
(138, 'Gununglangit', '', 'Kalibening', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:09', '2025-09-12 01:52:46'),
(139, 'Kalibening', '', 'Kalibening', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:09', '2025-09-12 02:20:03'),
(140, 'Majatengah-Banjarmangu', '', 'Banjarmangu', 'Banjarnegara', '', '', 'Eka', 'Sekretaris Desa', '0000000000', NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, '', 'aktif', '2025-08-25 04:59:09', '2025-09-03 06:01:16'),
(141, 'Plorengan', '', 'Kalibening', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:09', '2025-09-12 02:18:36'),
(142, 'Sembawa', '', 'Kalibening', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 04:59:09', '2025-09-12 01:52:46'),
(143, 'Kendaga', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:03:33', '2025-09-12 02:20:03'),
(144, 'Kesenet', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:03:39', '2025-09-12 02:20:03'),
(145, 'Paseh', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:03:51', '2025-09-12 02:20:03'),
(146, 'Prendengan', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:03:57', '2025-09-12 02:20:03'),
(147, 'Rejasari', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:04:05', '2025-09-12 02:20:03'),
(148, 'Sigeblog', '', 'Banjarmangu', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:04:11', '2025-09-12 02:20:03'),
(149, 'Gununggiana', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:04:18', '2025-09-12 02:20:03'),
(150, 'Kaliurip', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:04:32', '2025-09-12 02:20:03'),
(151, 'Pagelak', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:04:43', '2025-09-12 02:20:03'),
(152, 'Pakelen', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:04:52', '2025-09-12 02:20:03'),
(153, 'Petambakan', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:01', '2025-09-12 02:20:03'),
(154, 'Rakitan', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:06', '2025-09-12 02:20:03'),
(155, 'Talunamba', '', 'Madukara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:12', '2025-09-12 02:20:03'),
(156, 'Merden', '', 'Purwanegara', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:18', '2025-09-12 02:20:03'),
(157, 'Kandangwangi', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:26', '2025-09-12 02:20:03'),
(158, 'Karangjambe', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:33', '2025-09-12 02:20:03'),
(159, 'Medayu', '', 'Rakit', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:40', '2025-09-12 02:20:03'),
(160, 'Kebanaran', '', 'Mandiraja', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:48', '2025-09-12 02:20:03'),
(161, 'Somawangi', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:05:54', '2025-09-12 02:20:03'),
(162, 'Karangkobar', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:01', '2025-09-12 02:20:03'),
(163, 'Sampang', '', 'Karangkobar', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:06', '2025-09-12 02:20:03'),
(164, 'Susukan', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:12', '2025-09-12 02:20:03'),
(165, 'Purwareja', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:22', '2025-09-12 02:20:03'),
(166, 'Kecitran', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:29', '2025-09-12 02:20:03'),
(167, 'Sirkandi', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:35', '2025-09-12 02:20:03'),
(168, 'Pagak', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:40', '2025-09-12 02:20:03'),
(169, 'Kalilandak', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:46', '2025-09-12 02:20:03'),
(171, 'Kalimandi', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:06:57', '2025-09-12 02:20:03'),
(172, 'Kaliwinasuh', '', 'Klampok', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:04', '2025-09-12 02:20:03'),
(173, 'Piasawetan', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:12', '2025-09-12 02:20:03'),
(174, 'Pekikiran', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:18', '2025-09-12 02:20:03'),
(175, 'Panerusanwetan', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:26', '2025-09-12 02:20:03'),
(176, 'Gumelemkulon', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:34', '2025-09-12 02:20:03'),
(177, 'Gumelemwetan', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:42', '2025-09-12 02:20:03'),
(178, 'Karangjati', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:48', '2025-09-12 02:20:03'),
(179, 'Kedawung', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:07:54', '2025-09-12 02:20:03'),
(180, 'Kemranggon', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:09', '2025-09-12 02:20:03'),
(181, 'Kalitlaga', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:15', '2025-09-12 02:20:03'),
(182, 'Karangnangka', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:21', '2025-09-12 02:20:03'),
(183, 'Kayuares', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:26', '2025-09-12 02:20:03'),
(184, 'Larangan', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:32', '2025-09-12 02:20:03'),
(185, 'Nagasari', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:37', '2025-09-12 02:20:03'),
(186, 'Sokaraja', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:42', '2025-09-12 02:20:03'),
(187, 'Tegaljeruk', '', 'Pagentan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:08:48', '2025-09-12 02:20:03'),
(188, 'Kalibombong', 'Desa Kalibombong', 'Kalibening', 'Banjarnegara', 'Jawa Tengah', '', 'Firman', 'Kaur Perencanaan', '082198304022', NULL, NULL, NULL, NULL, 'kali@bombong.com', '$2y$10$Xw1t9cc0XDffI.Hq30hb5.1DqJv4b85Uoj6dmyMBUST5x3tjBisvS', 'baru', 'rendah', 0.00, NULL, 'sedang prospek', 'aktif', '2025-08-25 05:08:54', '2025-09-12 02:20:03'),
(189, 'Lawen', '', 'Pandanarum', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:09:00', '2025-09-12 02:20:03'),
(190, 'Pandanarum', '', 'Pandanarum', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:09:05', '2025-09-12 02:20:03'),
(191, 'Pasegeran', '', 'Pandanarum', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:09:11', '2025-09-12 02:20:03'),
(192, 'Pingitlor', '', 'Pandanarum', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:09:16', '2025-09-12 02:20:03'),
(194, 'Sinduaji', '', 'Pandanarum', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:09:29', '2025-09-12 02:20:03'),
(195, 'Sirongge', '', 'Pandanarum', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 05:09:56', '2025-09-12 02:20:03'),
(196, 'Sijenggung', 'sjg', 'Banjarmangu', 'Banjarnegara', 'Jawa Tengah', NULL, 'Suzono', NULL, '096567575765', NULL, NULL, NULL, NULL, 'suyo@no.com', NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-25 06:06:44', '2025-08-25 06:06:44'),
(197, 'Susukan Susukan', '', 'Susukan', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-08-28 14:24:21', '2025-09-12 01:52:47'),
(198, 'Majatengah-Kalibening', '', 'Kalibening', 'Banjarnegara', 'Jawa Tengah', '', 'Belum ada', '', '000000', NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, '', 'aktif', '2025-09-03 06:02:54', '2025-09-03 06:02:54'),
(209, 'Wanadadi', '', 'Wanadadi', 'Banjarnegara', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-09-12 01:39:11', '2025-09-12 01:39:11'),
(210, 'Klampok', '', 'Klampok', 'Banjarnegara', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'baru', 'rendah', 0.00, NULL, NULL, 'aktif', '2025-09-12 01:49:48', '2025-09-12 01:49:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `dokumen`
--

CREATE TABLE `dokumen` (
  `id` int(11) NOT NULL,
  `nama_dokumen` varchar(100) NOT NULL,
  `jenis_dokumen` enum('kontrak','sop','panduan','manual','laporan') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `desa_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_upload` date NOT NULL,
  `tanggal_berlaku` date DEFAULT NULL,
  `tanggal_berakhir` date DEFAULT NULL,
  `status` enum('aktif','expired','draft') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `jawaban` text NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `faq`
--

INSERT INTO `faq` (`id`, `pertanyaan`, `jawaban`, `kategori`, `urutan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Bagaimana cara menggunakan aplikasi desa?', 'Anda dapat mengakses menu-menu yang tersedia di dashboard. Setiap menu memiliki panduan penggunaan yang dapat diakses melalui tombol bantuan.', 'Umum', 1, 'aktif', '2025-08-25 06:14:58', '2025-08-25 06:14:58'),
(2, 'Apa yang harus dilakukan jika lupa password?', 'Silakan hubungi admin IT desa atau tim support kami untuk reset password. Pastikan Anda memberikan informasi yang valid untuk verifikasi.', 'Akun', 2, 'aktif', '2025-08-25 06:14:58', '2025-08-25 06:14:58'),
(3, 'Bagaimana cara melakukan backup data?', 'Backup data dilakukan secara otomatis setiap hari. Namun Anda juga dapat melakukan backup manual melalui menu pengaturan.', 'Teknis', 3, 'aktif', '2025-08-25 06:14:58', '2025-08-25 06:14:58'),
(4, 'Siapa yang dapat dihubungi untuk support teknis?', 'Anda dapat menghubungi tim support melalui halaman konsultasi atau langsung menghubungi nomor support yang tertera di dashboard.', 'Support', 4, 'aktif', '2025-08-25 06:14:58', '2025-08-25 06:14:58'),
(5, 'Bagaimana cara mengajukan fitur baru?', 'Anda dapat mengajukan fitur baru melalui halaman konsultasi dengan memilih kategori \"Pengembangan Fitur\". Tim kami akan mengevaluasi dan memberikan respons.', 'Pengembangan', 5, 'aktif', '2025-08-25 06:14:58', '2025-08-25 06:14:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hutang`
--

CREATE TABLE `hutang` (
  `id` int(11) NOT NULL,
  `pembelian_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `jumlah_hutang` decimal(15,2) NOT NULL,
  `tanggal_jatuh_tempo` date NOT NULL,
  `status` enum('belum_jatuh_tempo','mendekati_jatuh_tempo','terlambat','lunas') DEFAULT 'belum_jatuh_tempo',
  `denda` decimal(15,2) DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `hutang`
--

INSERT INTO `hutang` (`id`, `pembelian_id`, `vendor_id`, `jumlah_hutang`, `tanggal_jatuh_tempo`, `status`, `denda`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 22, 3, 1960000.00, '2025-10-10', '', 0.00, NULL, '2025-09-10 06:31:37', '2025-09-10 06:31:37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_biaya`
--

CREATE TABLE `jadwal_biaya` (
  `id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `biaya_operasional_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `harga_satuan` decimal(15,2) NOT NULL,
  `total_biaya` decimal(15,2) NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_biaya`
--

INSERT INTO `jadwal_biaya` (`id`, `jadwal_id`, `biaya_operasional_id`, `quantity`, `harga_satuan`, `total_biaya`, `catatan`, `created_at`) VALUES
(1, 8, 2, 1.00, 18000.00, 18000.00, '', '2025-08-28 16:13:33'),
(2, 8, 5, 2.00, 25000.00, 50000.00, '', '2025-08-28 16:13:33'),
(3, 9, 2, 1.00, 18000.00, 18000.00, '', '2025-08-29 00:19:43'),
(4, 10, 2, 2.00, 18000.00, 36000.00, '', '2025-08-29 02:32:25'),
(5, 11, 2, 1.00, 18000.00, 18000.00, '', '2025-09-11 23:08:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_kunjungan`
--

CREATE TABLE `jadwal_kunjungan` (
  `id` int(11) NOT NULL,
  `desa_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `teknisi_id` int(11) DEFAULT NULL,
  `jenis_kunjungan` enum('atk_keliling','maintenance','pelatihan','instalasi') NOT NULL,
  `tanggal_kunjungan` date NOT NULL,
  `waktu_mulai` time DEFAULT NULL,
  `waktu_selesai` time DEFAULT NULL,
  `estimasi_durasi` int(11) DEFAULT NULL,
  `status` enum('dijadwalkan','sedang_berlangsung','selesai','dibatalkan') DEFAULT 'dijadwalkan',
  `tanggal_selesai` timestamp NULL DEFAULT NULL,
  `urgensi` enum('rendah','sedang','tinggi') DEFAULT 'sedang',
  `catatan_kunjungan` text DEFAULT NULL,
  `foto_kunjungan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_kunjungan`
--

INSERT INTO `jadwal_kunjungan` (`id`, `desa_id`, `user_id`, `teknisi_id`, `jenis_kunjungan`, `tanggal_kunjungan`, `waktu_mulai`, `waktu_selesai`, `estimasi_durasi`, `status`, `tanggal_selesai`, `urgensi`, `catatan_kunjungan`, `foto_kunjungan`, `created_at`, `updated_at`) VALUES
(3, 3, 1, NULL, 'instalasi', '2025-08-28', '09:00:00', NULL, NULL, 'dijadwalkan', '2025-08-28 13:45:12', 'sedang', 'hanya test kunjungan\\n\\n[28/08/2025 20:42 - Arif Susilo] tunda', NULL, '2025-08-25 02:29:16', '2025-08-29 00:17:09'),
(7, 18, 1, 5, 'maintenance', '2025-09-05', '02:03:00', NULL, 50, 'dijadwalkan', NULL, 'tinggi', 'Urgent: Harus segera ditangani hari ini', NULL, '2025-08-28 16:11:03', '2025-08-29 00:18:21'),
(8, 12, 1, 6, '', '2025-09-06', '02:03:00', NULL, 120, 'dijadwalkan', NULL, 'tinggi', 'rgent: Harus segera ditangani hari ini', NULL, '2025-08-28 16:13:33', '2025-08-28 16:13:33'),
(9, 15, 1, 6, '', '2025-09-06', '01:00:00', NULL, 120, 'dijadwalkan', NULL, '', 'Normal: Kunjungan rutin sesuai jadwal', NULL, '2025-08-29 00:19:43', '2025-08-29 00:20:47'),
(10, 127, 1, 5, '', '2025-08-30', '09:00:00', NULL, 120, 'dijadwalkan', NULL, '', 'retwe rwe tewtrte', NULL, '2025-08-29 02:32:25', '2025-08-29 02:32:25'),
(11, 1, 1, NULL, '', '2025-09-27', '09:00:00', NULL, 120, 'dijadwalkan', NULL, '', 'sediakan minuman', NULL, '2025-09-11 23:08:41', '2025-09-12 02:24:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_peralatan`
--

CREATE TABLE `jadwal_peralatan` (
  `id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `peralatan_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `kondisi_awal` enum('baik','rusak_ringan','rusak_berat') DEFAULT 'baik',
  `kondisi_akhir` enum('baik','rusak_ringan','rusak_berat') DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_peralatan`
--

INSERT INTO `jadwal_peralatan` (`id`, `jadwal_id`, `peralatan_id`, `quantity`, `kondisi_awal`, `kondisi_akhir`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 8, 4, 1, 'baik', NULL, '', '2025-08-28 16:13:33', '2025-08-28 16:13:33'),
(2, 8, 6, 1, 'baik', NULL, '', '2025-08-28 16:13:33', '2025-08-28 16:13:33'),
(3, 9, 4, 1, 'baik', NULL, '', '2025-08-29 00:19:43', '2025-08-29 00:19:43'),
(4, 10, 3, 1, 'baik', NULL, '', '2025-08-29 02:32:25', '2025-08-29 02:32:25'),
(5, 11, 1, 1, 'baik', NULL, '', '2025-09-11 23:08:41', '2025-09-11 23:08:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_personal`
--

CREATE TABLE `jadwal_personal` (
  `id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_dalam_kunjungan` enum('teknisi_utama','teknisi_pendamping','sales','supervisor') NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_personal`
--

INSERT INTO `jadwal_personal` (`id`, `jadwal_id`, `user_id`, `role_dalam_kunjungan`, `catatan`, `created_at`) VALUES
(1, 8, 11, 'teknisi_utama', '', '2025-08-28 16:13:33'),
(2, 8, 10, 'teknisi_pendamping', '', '2025-08-28 16:13:33'),
(3, 9, 10, 'supervisor', '', '2025-08-29 00:19:43'),
(4, 10, 4, 'teknisi_pendamping', '', '2025-08-29 02:32:25'),
(5, 11, 13, 'teknisi_utama', '', '2025-09-11 23:08:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_produk`
--

CREATE TABLE `jadwal_produk` (
  `id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_produk`
--

INSERT INTO `jadwal_produk` (`id`, `jadwal_id`, `produk_id`, `quantity`, `catatan`, `created_at`) VALUES
(2, 3, 3, 5, 'Kertas untuk dokumentasi', '2025-08-25 03:01:49'),
(4, 3, 10, 5, 'Kertas untuk dokumentasi', '2025-08-25 03:03:15'),
(5, 3, 9, 10, 'Pulpen untuk administrasi', '2025-08-25 03:03:15'),
(6, 3, 8, 2, 'Stapler untuk berkas', '2025-08-25 03:03:16'),
(7, 3, 10, 5, 'Kertas untuk dokumentasi', '2025-08-25 03:03:45'),
(8, 3, 9, 10, 'Pulpen untuk administrasi', '2025-08-25 03:03:45'),
(9, 3, 8, 2, 'Stapler untuk berkas', '2025-08-25 03:03:45'),
(10, 8, 3, 1, '', '2025-08-28 16:13:33'),
(11, 8, 9, 2, '', '2025-08-28 16:13:33'),
(12, 9, 4, 1, '', '2025-08-29 00:19:43'),
(13, 10, 7, 1, '', '2025-08-29 02:32:25'),
(14, 10, 9, 1, '', '2025-08-29 02:32:25'),
(15, 11, 43, 1, '', '2025-09-11 23:08:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Elektronik', 'Produk elektronik dan gadget', 'aktif', '2025-09-11 03:04:23', '2025-09-11 03:04:23'),
(2, 'Pakaian', 'Pakaian dan fashion', 'aktif', '2025-09-11 03:04:23', '2025-09-11 03:04:23'),
(3, 'Makanan', 'Makanan dan minuman', 'aktif', '2025-09-11 03:04:23', '2025-09-11 03:04:23'),
(4, 'Kesehatan', 'Produk kesehatan dan kecantikan', 'aktif', '2025-09-11 03:04:23', '2025-09-11 03:04:23'),
(5, 'Olahraga', 'Peralatan olahraga dan fitness', 'aktif', '2025-09-11 03:04:23', '2025-09-11 03:04:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_produk`
--

CREATE TABLE `kategori_produk` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kategori_produk`
--

INSERT INTO `kategori_produk` (`id`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(1, 'Komputer & Laptop', 'Perangkat komputer, laptop, dan aksesorisnya', '2025-08-23 13:30:13'),
(2, 'Printer & Scanner', 'Perangkat printer, scanner, dan consumables', '2025-08-23 13:30:13'),
(3, 'Jaringan & Internet', 'Router, switch, kabel, dan perangkat jaringan', '2025-08-23 13:30:13'),
(10, 'ATK Umum', 'Alat tulis kantor umum seperti pulpen, kertas, map', '2025-08-24 12:07:47'),
(11, 'ATK Khusus', 'Alat tulis kantor khusus seperti stempel, tinta, formulir', '2025-08-24 12:07:47'),
(12, 'Furniture Kantor', 'Meja, kursi, lemari, dan furniture kantor lainnya', '2025-08-24 12:07:47'),
(13, 'Aplikasi', 'Aplikasi custom untuk', '2025-09-11 22:11:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `konsultasi`
--

CREATE TABLE `konsultasi` (
  `id` int(11) NOT NULL,
  `desa_id` int(11) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `subjek` varchar(200) NOT NULL,
  `pesan` text NOT NULL,
  `prioritas` enum('low','normal','high','urgent') DEFAULT 'normal',
  `kontak_balik` varchar(100) DEFAULT NULL,
  `status` enum('pending','in_progress','resolved','closed') DEFAULT 'pending',
  `respons` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `tanggal_respons` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `layanan`
--

CREATE TABLE `layanan` (
  `id` int(11) NOT NULL,
  `kode_layanan` varchar(20) NOT NULL,
  `nama_layanan` varchar(100) NOT NULL,
  `jenis_layanan` enum('maintenance','pelatihan','instalasi','konsultasi','pengembangan') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `harga` decimal(15,2) NOT NULL,
  `durasi_hari` int(11) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `harga_diskon` decimal(15,2) DEFAULT NULL COMMENT 'Harga setelah diskon',
  `is_featured` tinyint(1) DEFAULT 0 COMMENT '1 = layanan unggulan, 0 = layanan biasa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `layanan`
--

INSERT INTO `layanan` (`id`, `kode_layanan`, `nama_layanan`, `jenis_layanan`, `deskripsi`, `gambar`, `harga`, `durasi_hari`, `status`, `created_at`, `updated_at`, `harga_diskon`, `is_featured`) VALUES
(1, 'MNT001', 'Maintenance Aplikasi Desa Bulanan', 'maintenance', 'Maintenance rutin aplikasi desa setiap bulan', 'uploads/layanan/layanan_1_1756154135.jpeg', 1500001.00, 1, 'aktif', '2025-08-23 13:30:13', '2025-08-25 20:35:35', NULL, 1),
(2, 'MNT002', 'Maintenance Aplikasi Desa Tahunan', 'maintenance', 'Maintenance aplikasi desa paket tahunan', NULL, 15000000.00, 365, 'aktif', '2025-08-23 13:30:13', '2025-08-25 08:04:30', NULL, 1),
(3, 'TRN001', 'Pelatihan SIMDes Dasar', 'pelatihan', 'Pelatihan penggunaan Sistem Informasi Manajemen Desa', NULL, 2000000.00, 1, 'aktif', '2025-08-23 13:30:13', '2025-08-23 13:30:13', NULL, 0),
(4, 'TRN002', 'Pelatihan Siskeudes', 'pelatihan', 'Pelatihan penggunaan Sistem Keuangan Desa', NULL, 2500000.00, 2, 'aktif', '2025-08-23 13:30:13', '2025-08-23 13:30:13', NULL, 0),
(5, 'INS001', 'Sistem Informasi Desa Standar', 'pengembangan', 'Instalasi dan setup sistem aplikasi desa baru tanpa database penduduk dan fitur lainnya. 1 (satu) tahun domain dan hosting unlimited.', 'uploads/layanan/layanan_5_1756337511.jpeg', 7000001.00, 3, 'aktif', '2025-08-23 13:30:13', '2025-08-27 23:31:51', NULL, 0),
(6, 'KON001', 'Konsultasi IT', 'konsultasi', 'Konsultasi dan troubleshooting masalah IT', NULL, 500000.00, 1, 'aktif', '2025-08-23 13:30:13', '2025-08-23 13:30:13', NULL, 0),
(7, 'LYN2508001', 'Rtyryty', 'instalasi', 'tysr y sdrtyr', NULL, 60000.00, 5, '', '2025-08-24 10:13:13', '2025-08-27 23:35:19', NULL, 0),
(26, 'LYN2509001', 'Pembuatan Aplikasi Pertanahan Web', '', '', NULL, 3920000.00, 0, 'aktif', '2025-09-08 05:28:37', '2025-09-08 05:28:37', NULL, 0),
(27, 'LYN2509002', 'Domain', '', '', NULL, 55000.00, 0, 'aktif', '2025-09-08 05:37:06', '2025-09-08 05:37:06', NULL, 0),
(28, 'LYN2509003', 'Hosting', '', '', NULL, 600000.00, 0, 'aktif', '2025-09-08 05:37:39', '2025-09-08 05:37:39', NULL, 0),
(29, 'LYN2509004', 'SID', '', '', NULL, 9800000.00, 0, 'aktif', '2025-09-08 05:51:54', '2025-09-08 05:51:54', NULL, 0),
(30, 'LYN2509005', 'SID', '', '', NULL, 9819000.00, 0, 'aktif', '2025-09-08 05:54:17', '2025-09-08 05:54:17', NULL, 0),
(31, 'LYN2509006', 'Update & Maintanance', '', '', NULL, 2000000.00, 0, 'aktif', '2025-09-08 05:57:14', '2025-09-08 05:57:14', NULL, 0),
(32, 'LYN2509007', 'Website Desa', '', '', NULL, 9800000.00, 0, 'aktif', '2025-09-08 06:00:17', '2025-09-08 06:00:17', NULL, 0),
(33, 'LYN2509008', 'Pengadan Aplikasi & Pemeliharaan Website Desa', '', '', NULL, 9800000.00, 0, 'aktif', '2025-09-08 06:02:47', '2025-09-08 06:02:47', NULL, 0),
(34, 'LYN2509009', 'SID', '', '', NULL, 10270000.00, 0, 'aktif', '2025-09-08 06:11:31', '2025-09-08 06:11:31', NULL, 0),
(35, 'LYN2509010', 'Pengembangan SID', '', '', NULL, 1000000.00, 0, 'aktif', '2025-09-08 06:28:25', '2025-09-08 06:28:25', NULL, 0),
(36, 'LYN2509011', 'Pengembangan SID', '', '', NULL, 8820000.00, 0, 'aktif', '2025-09-08 06:29:45', '2025-09-08 06:29:45', NULL, 0),
(37, 'LYN2509012', 'SID', '', '', NULL, 9674000.00, 0, 'aktif', '2025-09-08 06:56:27', '2025-09-08 06:56:27', NULL, 0),
(38, 'LYN2509013', 'SID', '', '', NULL, 12740000.00, 0, 'aktif', '2025-09-08 06:58:28', '2025-09-08 06:58:28', NULL, 0),
(39, 'LYN2509014', 'SID', '', '', NULL, 11711000.00, 0, 'aktif', '2025-09-08 07:04:06', '2025-09-08 07:04:06', NULL, 0),
(40, 'LYN2509015', 'SID', '', '', NULL, 6860000.00, 0, '', '2025-09-09 01:42:14', '2025-09-09 01:44:08', NULL, 0),
(41, 'LYN2509016', 'Kegiatan Pelatihan SID', '', '', NULL, 6860000.00, 0, 'aktif', '2025-09-09 01:45:19', '2025-09-09 01:45:19', NULL, 0),
(42, 'LYN2509017', 'Paket Desa Prendengan', '', '', NULL, 6871000.00, 0, 'aktif', '2025-09-09 01:52:50', '2025-09-09 01:52:50', NULL, 0),
(43, 'LYN2509018', 'Paket Desa Jembangan', '', 'Jasa Sewa Peralatan/Perlengkaan', NULL, 6860000.00, 0, 'aktif', '2025-09-09 02:00:46', '2025-09-09 02:00:46', NULL, 0),
(44, 'LYN2509019', 'Kursus Pelatihan SID & DESAKTI', '', '', NULL, 6860000.00, 0, '', '2025-09-09 02:04:06', '2025-09-09 02:09:49', NULL, 0),
(45, 'LYN2509020', 'Pelatihan SID & DESAKTI', '', '', NULL, 6860000.00, 0, 'aktif', '2025-09-09 02:11:03', '2025-09-09 02:11:03', NULL, 0),
(46, 'LYN2509021', 'Pengembangan SID', '', '', NULL, 6860000.00, 0, 'aktif', '2025-09-09 02:17:13', '2025-09-09 02:17:13', NULL, 0),
(47, 'LYN2509022', 'Sewa Alat Kesenian', '', '', NULL, 8368000.00, 0, 'aktif', '2025-09-09 02:24:03', '2025-09-09 02:24:03', NULL, 0),
(48, 'LYN2509023', 'Iuran BIMTEK DESAKTI', '', '', NULL, 7000000.00, 0, 'aktif', '2025-09-09 02:50:14', '2025-09-09 02:50:14', NULL, 0),
(49, 'LYN2509024', 'Layanan Garansi', 'maintenance', 'Perbaikan SID dalam waktu garansi.', NULL, 10000.00, 1, 'aktif', '2025-09-09 08:28:04', '2025-09-09 08:28:04', NULL, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `username`, `ip_address`, `user_agent`, `login_time`, `created_at`) VALUES
(1, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-24 14:23:10', '2025-08-24 15:23:10'),
(2, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-24 14:53:10', '2025-08-24 15:23:10'),
(3, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-24 15:32:59', '2025-08-24 15:32:59'),
(4, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-24 21:34:28', '2025-08-24 21:34:28'),
(5, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-24 21:44:19', '2025-08-24 21:44:19'),
(6, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-24 22:52:50', '2025-08-24 22:52:50'),
(7, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-24 22:54:44', '2025-08-24 22:54:44'),
(8, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 00:09:46', '2025-08-25 00:09:46'),
(9, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 00:10:29', '2025-08-25 00:10:29'),
(10, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 00:15:41', '2025-08-25 00:15:41'),
(11, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 00:47:18', '2025-08-25 00:47:18'),
(12, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 01:14:49', '2025-08-25 01:14:49'),
(13, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 01:52:51', '2025-08-25 01:52:51'),
(14, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 01:58:54', '2025-08-25 01:58:54'),
(15, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 02:21:55', '2025-08-25 02:21:55'),
(16, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 02:59:37', '2025-08-25 02:59:37'),
(17, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 03:00:00', '2025-08-25 03:00:00'),
(18, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 03:25:13', '2025-08-25 03:25:13'),
(19, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 04:02:47', '2025-08-25 04:02:47'),
(20, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 04:15:12', '2025-08-25 04:15:12'),
(21, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 04:25:47', '2025-08-25 04:25:47'),
(22, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 05:17:54', '2025-08-25 05:17:54'),
(23, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 05:22:41', '2025-08-25 05:22:41'),
(24, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 05:25:59', '2025-08-25 05:25:59'),
(25, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 07:12:47', '2025-08-25 07:12:47'),
(26, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 07:20:19', '2025-08-25 07:20:19'),
(27, 1, 'admin', '192.168.1.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 12:20:06', '2025-08-25 12:20:06'),
(28, 1, 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-08-25 12:49:36', '2025-08-25 12:49:36'),
(29, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 14:37:27', '2025-08-25 14:37:27'),
(30, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 20:18:26', '2025-08-25 20:18:26'),
(31, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 21:17:09', '2025-08-25 21:17:09'),
(32, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-25 21:42:22', '2025-08-25 21:42:22'),
(33, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 00:52:08', '2025-08-26 00:52:08'),
(34, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 12:18:28', '2025-08-26 12:18:28'),
(35, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 12:52:27', '2025-08-26 12:52:27'),
(36, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 22:06:09', '2025-08-26 22:06:09'),
(37, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 22:06:49', '2025-08-26 22:06:49'),
(38, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 23:08:49', '2025-08-26 23:08:49'),
(39, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 23:11:23', '2025-08-26 23:11:23'),
(40, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 23:17:20', '2025-08-26 23:17:20'),
(41, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-26 23:54:32', '2025-08-26 23:54:32'),
(42, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.6613.186 Mobile Safari/537.36', '2025-08-27 00:04:09', '2025-08-27 00:04:09'),
(43, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 00:30:03', '2025-08-27 00:30:03'),
(44, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.6613.186 Mobile Safari/537.36', '2025-08-27 01:11:18', '2025-08-27 01:11:18'),
(45, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 15:37:54', '2025-08-27 15:37:54'),
(46, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.6613.186 Mobile Safari/537.36', '2025-08-27 15:40:14', '2025-08-27 15:40:14'),
(47, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 16:39:40', '2025-08-27 16:39:40'),
(48, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-27 16:40:39', '2025-08-27 16:40:39'),
(49, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 21:13:14', '2025-08-27 21:13:14'),
(50, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-27 21:15:17', '2025-08-27 21:15:17'),
(51, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-27 22:16:37', '2025-08-27 22:16:37'),
(52, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 22:51:24', '2025-08-27 22:51:24'),
(53, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-27 23:05:16', '2025-08-27 23:05:16'),
(54, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-27 23:18:27', '2025-08-27 23:18:27'),
(55, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 23:51:34', '2025-08-27 23:51:34'),
(56, 11, 'nadia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-08-28 00:04:58', '2025-08-28 00:04:58'),
(57, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-28 00:23:15', '2025-08-28 00:23:15'),
(58, 12, 'denysha', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-08-28 00:28:09', '2025-08-28 00:28:09'),
(59, 11, 'nadia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-08-28 01:47:59', '2025-08-28 01:47:59'),
(60, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 09:40:45', '2025-08-28 09:40:45'),
(61, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-08-28 09:49:15', '2025-08-28 09:49:15'),
(62, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 13:15:46', '2025-08-28 13:15:46'),
(63, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 14:16:59', '2025-08-28 14:16:59'),
(64, 11, 'nadia', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-08-28 14:54:24', '2025-08-28 14:54:24'),
(65, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 16:08:57', '2025-08-28 16:08:57'),
(66, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 21:03:36', '2025-08-28 21:03:36'),
(67, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 22:08:40', '2025-08-28 22:08:40'),
(68, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 23:35:41', '2025-08-28 23:35:41'),
(69, 11, 'nadia', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-08-29 00:24:14', '2025-08-29 00:24:14'),
(70, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:08:41', '2025-08-29 02:08:41'),
(71, 11, 'nadia', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:28:58', '2025-08-29 02:28:58'),
(72, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:30:04', '2025-08-29 02:30:04'),
(73, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:50:49', '2025-08-29 02:50:49'),
(74, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:53:36', '2025-08-29 02:53:36'),
(75, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 02:57:38', '2025-08-29 02:57:38'),
(76, 11, 'nadia', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-29 03:24:52', '2025-08-29 03:24:52'),
(77, 11, 'nadia', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-29 03:24:54', '2025-08-29 03:24:54'),
(78, 4, 'windy', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 03:42:26', '2025-08-29 03:42:26'),
(79, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-29 04:36:45', '2025-08-29 04:36:45'),
(80, 11, 'nadia', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-29 05:44:17', '2025-08-29 05:44:17'),
(81, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 06:42:57', '2025-08-29 06:42:57'),
(82, 12, 'denysha', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-29 07:08:53', '2025-08-29 07:08:53'),
(83, 11, 'nadia', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-29 07:29:10', '2025-08-29 07:29:10'),
(84, 1, 'admin', '36.73.35.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 12:44:05', '2025-08-29 12:44:05'),
(85, 11, 'nadia', '114.10.124.155', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-29 15:47:30', '2025-08-29 15:47:30'),
(86, 11, 'nadia', '114.10.18.140', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-29 16:48:49', '2025-08-29 16:48:49'),
(87, 1, 'admin', '36.73.32.254', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 21:08:59', '2025-08-29 21:08:59'),
(88, 1, 'admin', '182.253.8.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-30 03:01:30', '2025-08-30 03:01:30'),
(89, 11, 'nadia', '114.10.19.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-30 15:02:33', '2025-08-30 15:02:33'),
(90, 1, 'admin', '36.73.34.189', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 00:56:47', '2025-08-31 00:56:47'),
(91, 1, 'admin', '36.73.34.189', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 02:06:00', '2025-08-31 02:06:00'),
(92, 12, 'denysha', '36.73.32.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-31 13:31:46', '2025-08-31 13:31:46'),
(93, 11, 'nadia', '114.10.8.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-31 14:07:42', '2025-08-31 14:07:42'),
(94, 12, 'denysha', '36.73.32.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-31 14:37:51', '2025-08-31 14:37:51'),
(95, 11, 'nadia', '114.10.19.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-31 15:24:47', '2025-08-31 15:24:47'),
(96, 1, 'admin', '36.73.35.193', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 01:51:41', '2025-09-01 01:51:41'),
(97, 12, 'denysha', '36.73.33.148', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 14:32:27', '2025-09-01 14:32:27'),
(98, 1, 'admin', '182.253.8.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 05:53:33', '2025-09-02 05:53:33'),
(99, 1, 'admin', '36.73.33.121', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-09-02 10:54:10', '2025-09-02 10:54:10'),
(100, 1, 'admin', '182.253.8.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 05:59:09', '2025-09-03 05:59:09'),
(101, 1, 'admin', '103.255.132.201', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-09-06 06:18:04', '2025-09-06 06:18:04'),
(102, 1, 'admin', '103.255.132.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-06 07:59:13', '2025-09-06 07:59:13'),
(103, 1, 'admin', '182.253.8.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-09-08 04:56:09', '2025-09-08 04:56:09'),
(104, 1, 'admin', '182.253.8.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-09-08 05:50:26', '2025-09-08 05:50:26'),
(105, 1, 'admin', '182.253.8.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-09-08 06:51:18', '2025-09-08 06:51:18'),
(106, 1, 'admin', '182.253.8.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-09-08 06:54:16', '2025-09-08 06:54:16'),
(107, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-09-09 01:40:34', '2025-09-09 01:40:34'),
(108, 1, 'admin', '149.102.225.48', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 02:33:46', '2025-09-09 02:33:46'),
(109, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-09-09 02:40:39', '2025-09-09 02:40:39'),
(110, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 03:06:16', '2025-09-09 03:06:16'),
(111, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-09 03:26:12', '2025-09-09 03:26:12'),
(112, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 04:07:15', '2025-09-09 04:07:15'),
(113, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 05:34:57', '2025-09-09 05:34:57'),
(114, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-09 05:37:00', '2025-09-09 05:37:00'),
(115, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 06:40:17', '2025-09-09 06:40:17'),
(116, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 07:45:47', '2025-09-09 07:45:47'),
(117, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:55:39', '2025-09-09 08:55:39'),
(118, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-09 09:47:07', '2025-09-09 09:47:07'),
(119, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:57:27', '2025-09-09 09:57:27'),
(120, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-09 10:48:46', '2025-09-09 10:48:46'),
(121, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 11:10:40', '2025-09-09 11:10:40'),
(122, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-10 01:37:15', '2025-09-10 01:37:15'),
(123, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-10 02:40:25', '2025-09-10 02:40:25'),
(124, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-10 03:45:52', '2025-09-10 03:45:52'),
(125, 1, 'admin', '192.168.1.53', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 04:09:06', '2025-09-10 04:09:06'),
(126, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-10 05:44:23', '2025-09-10 05:44:23'),
(127, 1, 'admin', '192.168.1.53', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 05:48:49', '2025-09-10 05:48:49'),
(128, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-10 05:59:15', '2025-09-10 05:59:15'),
(129, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-10 06:05:21', '2025-09-10 06:05:21'),
(130, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-10 07:23:28', '2025-09-10 07:23:28'),
(131, 1, 'admin', '192.168.1.53', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 07:23:47', '2025-09-10 07:23:47'),
(132, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-10 07:59:18', '2025-09-10 07:59:18'),
(133, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 02:36:22', '2025-09-11 02:36:22'),
(134, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 02:45:02', '2025-09-11 02:45:02'),
(135, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 03:42:30', '2025-09-11 03:42:30'),
(136, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 03:49:27', '2025-09-11 03:49:27'),
(137, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 04:43:46', '2025-09-11 04:43:46'),
(138, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:15:32', '2025-09-11 06:15:32'),
(139, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 10:31:03', '2025-09-11 10:31:03'),
(140, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 10:54:21', '2025-09-11 10:54:21'),
(141, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 17:00:50', '2025-09-11 17:00:50'),
(142, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 17:28:34', '2025-09-11 17:28:34'),
(143, 11, 'nadia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 17:57:20', '2025-09-11 17:57:20'),
(144, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 18:28:02', '2025-09-11 18:28:02'),
(145, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 18:31:23', '2025-09-11 18:31:23'),
(146, 11, 'nadia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 19:17:06', '2025-09-11 19:17:06'),
(147, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 19:36:12', '2025-09-11 19:36:12'),
(148, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 19:42:16', '2025-09-11 19:42:16'),
(149, 11, 'nadia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 20:19:31', '2025-09-11 20:19:31'),
(150, 12, 'denysha', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 20:31:40', '2025-09-11 20:31:40'),
(151, 4, 'windy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 20:32:31', '2025-09-11 20:32:31'),
(152, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 20:38:42', '2025-09-11 20:38:42'),
(153, 3, 'fika', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 21:07:15', '2025-09-11 21:07:15'),
(154, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-11 21:44:59', '2025-09-11 21:44:59'),
(155, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 21:49:56', '2025-09-11 21:49:56'),
(156, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 21:56:30', '2025-09-11 21:56:30'),
(157, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 22:58:26', '2025-09-11 22:58:26'),
(158, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 23:00:01', '2025-09-11 23:00:01'),
(159, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 23:04:42', '2025-09-11 23:04:42'),
(160, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-11 23:28:35', '2025-09-11 23:28:35'),
(161, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-12 02:45:13', '2025-09-12 02:45:13'),
(162, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 02:48:14', '2025-09-12 02:48:14'),
(163, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 02:50:29', '2025-09-12 02:50:29'),
(164, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 02:55:10', '2025-09-12 02:55:10'),
(165, 1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Trae/1.100.3 Chrome/132.0.6834.210 Electron/34.5.1 Safari/537.36', '2025-09-12 02:59:44', '2025-09-12 02:59:44'),
(166, 1, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 03:58:37', '2025-09-12 03:58:37'),
(167, 1, 'admin', '36.73.33.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 04:07:52', '2025-09-12 04:07:52'),
(168, 4, 'windy', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 04:23:10', '2025-09-12 04:23:10'),
(169, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 04:24:00', '2025-09-12 04:24:00'),
(170, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 04:24:40', '2025-09-12 04:24:40'),
(171, 4, 'windy', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 04:25:47', '2025-09-12 04:25:47'),
(172, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 04:30:25', '2025-09-12 04:30:25'),
(173, 11, 'nadia', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-12 05:21:55', '2025-09-12 05:21:55'),
(174, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 05:25:01', '2025-09-12 05:25:01'),
(175, 11, 'nadia', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-12 05:26:19', '2025-09-12 05:26:19'),
(176, 4, 'windy', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 06:20:52', '2025-09-12 06:20:52'),
(177, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 06:40:11', '2025-09-12 06:40:11'),
(178, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 07:40:56', '2025-09-12 07:40:56'),
(179, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 07:42:04', '2025-09-12 07:42:04'),
(180, 11, 'nadia', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-12 07:53:39', '2025-09-12 07:53:39'),
(181, 4, 'windy', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 07:59:24', '2025-09-12 07:59:24'),
(182, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 08:42:24', '2025-09-12 08:42:24'),
(183, 11, 'nadia', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-12 09:02:36', '2025-09-12 09:02:36'),
(184, 4, 'windy', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 09:22:48', '2025-09-12 09:22:48'),
(185, 1, 'admin', '36.73.34.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 23:15:35', '2025-09-12 23:15:35'),
(186, 1, 'admin', '36.73.34.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 00:18:53', '2025-09-13 00:18:53'),
(187, 4, 'windy', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 02:39:04', '2025-09-13 02:39:04'),
(188, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 03:43:00', '2025-09-13 03:43:00'),
(189, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 04:44:39', '2025-09-13 04:44:39'),
(190, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:56:50', '2025-09-13 05:56:50'),
(191, 11, 'nadia', '114.10.19.190', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-13 07:12:11', '2025-09-13 07:12:11'),
(192, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 07:48:44', '2025-09-13 07:48:44'),
(193, 1, 'admin', '182.253.8.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:48:56', '2025-09-13 08:48:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maintenance_checklist`
--

CREATE TABLE `maintenance_checklist` (
  `id` int(11) NOT NULL,
  `maintenance_id` int(11) NOT NULL,
  `install_website` tinyint(1) DEFAULT 0,
  `setup_info_desa` tinyint(1) DEFAULT 0,
  `import_database` tinyint(1) DEFAULT 0,
  `menu_standar` tinyint(1) DEFAULT 0,
  `foto_gambar` tinyint(1) DEFAULT 0,
  `berita_dummy` tinyint(1) DEFAULT 0,
  `no_404_page` tinyint(1) DEFAULT 0,
  `no_505_page` tinyint(1) DEFAULT 0,
  `sinkron_opendata` tinyint(1) DEFAULT 0,
  `domain_resmi_kominfo` tinyint(1) DEFAULT 0,
  `cek_fitur_surat_cetak` tinyint(1) DEFAULT 0,
  `copy_template_surat` tinyint(1) DEFAULT 0,
  `rubah_foto_background_login` tinyint(1) DEFAULT 0,
  `rubah_foto_profil_desa` tinyint(1) DEFAULT 0,
  `cek_semua_fitur` tinyint(1) DEFAULT 0,
  `hidupkan_fitur_banner` tinyint(1) DEFAULT 0,
  `pengecekan` tinyint(1) DEFAULT 0,
  `proses` tinyint(1) DEFAULT 0,
  `selesai` tinyint(1) DEFAULT 0,
  `submitted_for_verification` tinyint(1) DEFAULT 0,
  `verified_by_admin` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` int(11) DEFAULT NULL,
  `verified_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `maintenance_checklist`
--

INSERT INTO `maintenance_checklist` (`id`, `maintenance_id`, `install_website`, `setup_info_desa`, `import_database`, `menu_standar`, `foto_gambar`, `berita_dummy`, `no_404_page`, `no_505_page`, `sinkron_opendata`, `domain_resmi_kominfo`, `cek_fitur_surat_cetak`, `copy_template_surat`, `rubah_foto_background_login`, `rubah_foto_profil_desa`, `cek_semua_fitur`, `hidupkan_fitur_banner`, `pengecekan`, `proses`, `selesai`, `submitted_for_verification`, `verified_by_admin`, `verified_at`, `verified_by_id`, `verified_by`, `created_at`, `updated_at`) VALUES
(5, 5, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-28 01:48:37', '2025-09-11 18:15:57'),
(7, 7, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 02:29:24', '2025-08-31 14:26:15'),
(8, 19, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 03:28:52', '2025-08-31 14:40:24'),
(10, 8, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 16:07:43', '2025-08-31 14:46:13'),
(11, 9, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 16:15:42', '2025-08-31 15:32:12'),
(12, 14, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 16:27:24', '2025-08-31 15:35:11'),
(13, 15, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 16:41:55', '2025-08-31 15:41:51'),
(14, 16, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-29 16:49:46', '2025-08-31 15:43:55'),
(15, 6, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:04:47', '2025-08-31 14:47:33'),
(16, 18, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:08:53', '2025-08-31 14:42:30'),
(17, 17, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:10:18', '2025-08-31 14:43:55'),
(18, 13, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:11:38', '2025-08-31 14:44:34'),
(19, 12, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:14:50', '2025-08-31 14:45:10'),
(20, 11, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:16:20', '2025-08-31 14:45:52'),
(21, 10, 1, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-08-31 14:17:25', '2025-08-31 14:46:55'),
(22, 26, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 0, 0, NULL, NULL, NULL, '2025-09-11 18:49:29', '2025-09-11 18:55:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maintenance_checklist_backup`
--

CREATE TABLE `maintenance_checklist_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `maintenance_id` int(11) NOT NULL,
  `install_website` tinyint(1) DEFAULT 0,
  `setup_info_desa` tinyint(1) DEFAULT 0,
  `import_database` tinyint(1) DEFAULT 0,
  `menu_standar` tinyint(1) DEFAULT 0,
  `foto_gambar` tinyint(1) DEFAULT 0,
  `berita_dummy` tinyint(1) DEFAULT 0,
  `no_404_page` tinyint(1) DEFAULT 0,
  `no_505_page` tinyint(1) DEFAULT 0,
  `sinkron_opendata` tinyint(1) DEFAULT 0,
  `domain_resmi_kominfo` tinyint(1) DEFAULT 0,
  `submitted_for_verification` tinyint(1) DEFAULT 0,
  `verified_by_admin` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `maintenance_checklist_backup`
--

INSERT INTO `maintenance_checklist_backup` (`id`, `maintenance_id`, `install_website`, `setup_info_desa`, `import_database`, `menu_standar`, `foto_gambar`, `berita_dummy`, `no_404_page`, `no_505_page`, `sinkron_opendata`, `domain_resmi_kominfo`, `submitted_for_verification`, `verified_by_admin`, `verified_at`, `verified_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2025-08-25 05:38:44', '2025-08-25 05:38:44'),
(2, 2, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2025-08-25 05:38:44', '2025-08-25 05:38:44'),
(3, 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, NULL, NULL, '2025-08-25 05:38:44', '2025-08-25 05:38:44'),
(1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2025-08-25 05:38:44', '2025-08-25 05:38:44'),
(2, 2, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2025-08-25 05:38:44', '2025-08-25 05:38:44'),
(3, 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, NULL, NULL, '2025-08-25 05:38:44', '2025-08-25 05:38:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maintenance_checklist_simple`
--

CREATE TABLE `maintenance_checklist_simple` (
  `id` int(11) NOT NULL,
  `maintenance_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_order` int(11) DEFAULT 0,
  `status` enum('pending','completed') DEFAULT 'pending',
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `maintenance_checklist_simple`
--

INSERT INTO `maintenance_checklist_simple` (`id`, `maintenance_id`, `item_name`, `item_order`, `status`, `is_completed`, `completed_at`, `created_at`) VALUES
(1, 26, 'Diterima', 1, 'pending', 0, NULL, '2025-09-11 18:39:57'),
(2, 26, 'Diproses', 2, 'pending', 0, NULL, '2025-09-11 18:39:57'),
(3, 26, 'Selesai', 3, 'pending', 0, NULL, '2025-09-11 18:39:57');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mutasi_kas`
--

CREATE TABLE `mutasi_kas` (
  `id` int(11) NOT NULL,
  `bank_id` int(11) NOT NULL,
  `jenis_mutasi` enum('masuk','keluar') NOT NULL,
  `jenis_transaksi` enum('penjualan','pembelian','pembayaran_piutang','pembayaran_hutang','lainnya') NOT NULL,
  `referensi_id` int(11) DEFAULT NULL COMMENT 'ID transaksi/pembelian/pembayaran terkait',
  `referensi_tabel` varchar(50) DEFAULT NULL COMMENT 'Nama tabel referensi',
  `jumlah` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal_mutasi` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mutasi_kas`
--

INSERT INTO `mutasi_kas` (`id`, `bank_id`, `jenis_mutasi`, `jenis_transaksi`, `referensi_id`, `referensi_tabel`, `jumlah`, `keterangan`, `tanggal_mutasi`, `user_id`, `created_at`) VALUES
(1, 1, 'keluar', 'pembelian', 6, 'pembelian', 50000.00, 'Pembayaran pembelian PO-20250909-002', '2025-09-09', 1, '2025-09-09 09:46:00'),
(2, 4, 'keluar', 'pembelian', 7, 'pembelian', 1683000.00, 'Pembayaran pembelian PO-20250909-003', '2025-09-09', 1, '2025-09-09 10:04:10'),
(3, 4, 'keluar', 'pembelian', 8, 'pembelian', 6561000.00, 'Pembayaran pembelian PO-20250909-004', '2025-09-09', 1, '2025-09-09 10:20:31'),
(4, 4, 'keluar', 'pembelian', 11, 'pembelian', 5000000.00, 'Pembayaran pembelian PO-20250909-006', '2025-09-09', 1, '2025-09-09 10:29:49'),
(5, 2, 'keluar', 'pembelian', 12, 'pembelian', 7500000.00, 'Pembayaran pembelian PO-20250909-007', '2025-09-09', 1, '2025-09-09 10:31:36'),
(6, 4, 'keluar', 'pembelian', 13, 'pembelian', 7500000.00, 'Pembayaran pembelian PO-20250909-008', '2025-09-09', 1, '2025-09-09 10:38:44'),
(7, 1, 'keluar', 'pembelian', 14, 'pembelian', 12500000.00, 'Pembayaran pembelian PO-20250909-009', '2025-09-09', 1, '2025-09-09 10:51:38'),
(8, 2, 'keluar', 'pembelian', 15, 'pembelian', 20000.00, 'Pembayaran pembelian PO-20250910-001', '2025-09-10', 1, '2025-09-10 04:11:19'),
(9, 4, 'keluar', 'pembelian', 16, 'pembelian', 2672000.00, 'Pembayaran pembelian PO-20250910-002', '2025-09-10', 1, '2025-09-10 04:20:25'),
(10, 2, 'keluar', 'pembelian', 17, 'pembelian', 12422000.00, 'Pembayaran pembelian PO-20250910-003', '2025-09-10', 1, '2025-09-10 05:52:58'),
(11, 3, 'keluar', 'pembelian', 18, 'pembelian', 12422000.00, 'Pembayaran pembelian PO-20250910-004', '2025-09-10', 1, '2025-09-10 06:06:13'),
(12, 2, 'keluar', 'pembelian', 23, 'pembelian', 10904245.00, 'Pembayaran pembelian PO-20250910-134', '2025-09-10', 1, '2025-09-10 07:24:04'),
(13, 4, 'keluar', 'pembelian', 24, 'pembelian', 1960000.00, 'Pembayaran pembelian PO-20250910-135', '2025-09-10', 1, '2025-09-10 07:25:24'),
(14, 1, 'keluar', 'pembelian', 25, 'pembelian', 525000.00, 'Pembayaran pembelian PO-20250910-136', '2025-09-10', 1, '2025-09-10 07:51:43'),
(15, 1, 'keluar', 'pembelian', 26, 'pembelian', 20000.00, 'Pembayaran pembelian PO-20250911-001', '2025-09-11', 1, '2025-09-11 02:37:35');

--
-- Trigger `mutasi_kas`
--
DELIMITER $$
CREATE TRIGGER `update_saldo_after_mutasi` AFTER INSERT ON `mutasi_kas` FOR EACH ROW BEGIN
    DECLARE current_periode_bulan INT;
    DECLARE current_periode_tahun INT;
    
    SET current_periode_bulan = MONTH(NEW.tanggal_mutasi);
    SET current_periode_tahun = YEAR(NEW.tanggal_mutasi);
    
    
    INSERT INTO saldo_bank (bank_id, periode_bulan, periode_tahun, saldo_masuk, saldo_keluar, saldo_akhir)
    VALUES (
        NEW.bank_id, 
        current_periode_bulan, 
        current_periode_tahun,
        CASE WHEN NEW.jenis_mutasi = 'masuk' THEN NEW.jumlah ELSE 0 END,
        CASE WHEN NEW.jenis_mutasi = 'keluar' THEN NEW.jumlah ELSE 0 END,
        CASE WHEN NEW.jenis_mutasi = 'masuk' THEN NEW.jumlah ELSE -NEW.jumlah END
    )
    ON DUPLICATE KEY UPDATE
        saldo_masuk = saldo_masuk + CASE WHEN NEW.jenis_mutasi = 'masuk' THEN NEW.jumlah ELSE 0 END,
        saldo_keluar = saldo_keluar + CASE WHEN NEW.jenis_mutasi = 'keluar' THEN NEW.jumlah ELSE 0 END,
        saldo_akhir = saldo_awal + saldo_masuk - saldo_keluar,
        updated_at = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelatihan`
--

CREATE TABLE `pelatihan` (
  `id` int(11) NOT NULL,
  `kode_pelatihan` varchar(20) NOT NULL,
  `nama_pelatihan` varchar(100) NOT NULL,
  `topik` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal_pelatihan` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `lokasi` varchar(200) DEFAULT NULL,
  `desa_id` int(11) DEFAULT NULL,
  `trainer_id` int(11) NOT NULL,
  `max_peserta` int(11) DEFAULT 20,
  `status` enum('dijadwalkan','berlangsung','selesai','dibatalkan') DEFAULT 'dijadwalkan',
  `materi_pelatihan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `piutang_id` int(11) DEFAULT NULL,
  `jumlah_bayar` decimal(15,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('tunai','transfer','qris') NOT NULL,
  `bank_id` int(11) DEFAULT NULL,
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran_pembelian`
--

CREATE TABLE `pembayaran_pembelian` (
  `id` int(11) NOT NULL,
  `pembelian_id` int(11) NOT NULL,
  `hutang_id` int(11) DEFAULT NULL,
  `jumlah_bayar` decimal(15,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('tunai','transfer','cek','giro') NOT NULL,
  `bank_id` int(11) DEFAULT NULL,
  `nomor_referensi` varchar(50) DEFAULT NULL COMMENT 'Nomor transfer/cek/giro',
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `pembayaran_pembelian`
--
DELIMITER $$
CREATE TRIGGER `update_jumlah_terbayar_after_payment` AFTER INSERT ON `pembayaran_pembelian` FOR EACH ROW BEGIN
            UPDATE pembelian 
            SET jumlah_terbayar = (
                SELECT COALESCE(SUM(jumlah_bayar), 0) 
                FROM pembayaran_pembelian 
                WHERE pembelian_id = NEW.pembelian_id
            ),
            status_pembayaran = CASE 
                WHEN (
                    SELECT COALESCE(SUM(jumlah_bayar), 0) 
                    FROM pembayaran_pembelian 
                    WHERE pembelian_id = NEW.pembelian_id
                ) >= total_amount THEN 'lunas'
                WHEN (
                    SELECT COALESCE(SUM(jumlah_bayar), 0) 
                    FROM pembayaran_pembelian 
                    WHERE pembelian_id = NEW.pembelian_id
                ) > 0 THEN 'dp'
                ELSE 'belum_bayar'
            END
            WHERE id = NEW.pembelian_id;
        END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian`
--

CREATE TABLE `pembelian` (
  `id` int(11) NOT NULL,
  `nomor_po` varchar(20) NOT NULL COMMENT 'Purchase Order Number',
  `vendor_id` int(11) NOT NULL,
  `desa_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User yang membuat PO',
  `tanggal_pembelian` date NOT NULL,
  `tanggal_dibutuhkan` date DEFAULT NULL COMMENT 'Tanggal barang dibutuhkan',
  `metode_pembayaran` enum('tunai','transfer','tempo') NOT NULL,
  `bank_id` int(11) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `dp_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Down Payment',
  `sisa_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Sisa pembayaran',
  `jumlah_terbayar` decimal(15,2) DEFAULT 0.00,
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `status_pembelian` enum('draft','dikirim','diterima_sebagian','diterima_lengkap','dibatalkan') DEFAULT 'draft',
  `status_pembayaran` enum('belum_bayar','dp','lunas') DEFAULT 'belum_bayar',
  `catatan` text DEFAULT NULL,
  `alamat_pengiriman` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembelian`
--

INSERT INTO `pembelian` (`id`, `nomor_po`, `vendor_id`, `desa_id`, `user_id`, `tanggal_pembelian`, `tanggal_dibutuhkan`, `metode_pembayaran`, `bank_id`, `total_amount`, `dp_amount`, `sisa_amount`, `jumlah_terbayar`, `tanggal_jatuh_tempo`, `status_pembelian`, `status_pembayaran`, `catatan`, `alamat_pengiriman`, `created_at`, `updated_at`) VALUES
(5, 'PO-20250909-001', 1, NULL, 1, '2025-09-09', '2025-09-13', 'tunai', 1, 5000000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 05:40:08', '2025-09-10 04:17:08'),
(6, 'PO-20250909-002', 1, 171, 1, '2025-09-09', '2025-09-11', 'tunai', 1, 50000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 09:46:00', '2025-09-09 09:50:07'),
(7, 'PO-20250909-003', 3, 105, 1, '2025-09-09', '2025-09-13', 'tunai', 4, 1683000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 10:04:10', '2025-09-09 10:13:55'),
(8, 'PO-20250909-004', 3, 14, 1, '2025-09-09', '2025-09-25', 'tunai', 4, 6561000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 10:20:31', '2025-09-09 10:20:41'),
(10, 'PO-20250909-005', 2, NULL, 1, '2025-09-09', NULL, 'transfer', 1, 28844200.00, 0.00, 28844200.00, 0.00, NULL, 'diterima_lengkap', 'belum_bayar', NULL, NULL, '2025-09-09 10:28:11', '2025-09-09 10:28:11'),
(11, 'PO-20250909-006', 2, 33, 1, '2025-09-09', '2025-09-27', 'tunai', 4, 5000000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 10:29:49', '2025-09-09 10:29:58'),
(12, 'PO-20250909-007', 2, 5, 1, '2025-09-09', '2025-09-27', 'tunai', 2, 7500000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 10:31:36', '2025-09-09 10:31:41'),
(13, 'PO-20250909-008', 2, 10, 1, '2025-09-09', '2025-09-20', 'tunai', 4, 7500000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 10:38:44', '2025-09-09 10:38:49'),
(14, 'PO-20250909-009', 2, 14, 1, '2025-09-09', '2025-09-13', 'tunai', 1, 12500000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-09 10:51:38', '2025-09-09 10:53:02'),
(15, 'PO-20250910-001', 1, 17, 1, '2025-09-10', '2025-09-20', 'tunai', 2, 20000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 04:11:19', '2025-09-10 04:11:25'),
(16, 'PO-20250910-002', 1, 3, 1, '2025-09-10', '2025-09-24', 'tunai', 4, 2672000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 04:20:25', '2025-09-10 04:20:29'),
(17, 'PO-20250910-003', 1, 10, 1, '2025-09-10', '2025-09-25', 'tunai', 2, 12422000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 05:52:58', '2025-09-10 06:12:11'),
(18, 'PO-20250910-004', 1, 14, 1, '2025-09-10', '2025-09-24', 'tunai', 3, 12422000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 06:06:12', '2025-09-10 06:12:11'),
(21, 'PO-TEST-20250910-132', 1, NULL, 1, '2025-09-10', NULL, 'transfer', 1, 500000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'belum_bayar', NULL, NULL, '2025-09-10 06:25:31', '2025-09-10 07:50:59'),
(22, 'PO-20250910-133', 3, 13, 1, '2025-09-10', '2025-09-13', 'tempo', 1, 1960000.00, 0.00, 1960000.00, 1960000.00, '2025-10-10', 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 06:31:37', '2025-09-10 07:37:09'),
(23, 'PO-20250910-134', 3, 13, 1, '2025-09-10', '2025-09-19', 'tunai', 2, 10904245.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 07:24:04', '2025-09-10 07:24:17'),
(24, 'PO-20250910-135', 1, 8, 1, '2025-09-10', '2025-09-27', 'tunai', 4, 1960000.00, 0.00, 0.00, 0.00, NULL, 'diterima_lengkap', 'lunas', '', NULL, '2025-09-10 07:25:24', '2025-09-10 07:25:49'),
(25, 'PO-20250910-136', 3, 13, 1, '2025-09-10', '2025-09-19', 'tunai', 1, 525000.00, 0.00, 0.00, 0.00, NULL, 'dikirim', 'lunas', '', NULL, '2025-09-10 07:51:43', '2025-09-10 07:52:13'),
(26, 'PO-20250911-001', 3, 12, 1, '2025-09-11', '2025-09-20', 'tunai', 1, 20000.00, 0.00, 0.00, 0.00, NULL, 'dikirim', 'lunas', 'tes', NULL, '2025-09-11 02:37:35', '2025-09-11 02:38:58'),
(27, 'PO-20250911-002', 1, NULL, 1, '2025-09-11', '2025-09-18', 'tempo', 1, 19950000.00, 0.00, 19950000.00, 0.00, '2025-10-11', 'dikirim', 'belum_bayar', 'Simulasi pembelian untuk testing sistem', NULL, '2025-09-11 02:46:21', '2025-09-11 02:46:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_detail`
--

CREATE TABLE `pembelian_detail` (
  `id` int(11) NOT NULL,
  `pembelian_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `nama_item` varchar(100) NOT NULL,
  `quantity_pesan` int(11) NOT NULL COMMENT 'Jumlah yang dipesan',
  `quantity_terima` int(11) DEFAULT 0 COMMENT 'Jumlah yang sudah diterima',
  `harga_satuan` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembelian_detail`
--

INSERT INTO `pembelian_detail` (`id`, `pembelian_id`, `produk_id`, `nama_item`, `quantity_pesan`, `quantity_terima`, `harga_satuan`, `subtotal`, `catatan`, `created_at`) VALUES
(1, 5, 4, 'Printer HP LaserJet', 2, 2, 2500000.00, 5000000.00, NULL, '2025-09-09 05:40:08'),
(2, 6, 10, 'Stapler Kenko', 2, 2, 25000.00, 50000.00, NULL, '2025-09-09 09:46:00'),
(3, 7, 30, 'SEAGATE HDD 1.0TB 3.5\"', 1, 1, 525000.00, 525000.00, NULL, '2025-09-09 10:04:10'),
(4, 7, 28, 'DAHUA XVR B04-1 4CH 2MP', 1, 1, 490000.00, 490000.00, NULL, '2025-09-09 10:04:10'),
(5, 7, 29, 'DAHUA XVR B04-1 8CH 2MP', 1, 1, 668000.00, 668000.00, NULL, '2025-09-09 10:04:10'),
(6, 8, 30, 'SEAGATE HDD 1.0TB 3.5\"', 2, 2, 525000.00, 1050000.00, NULL, '2025-09-09 10:20:31'),
(7, 8, 17, 'Smartphone Android', 1, 1, 3511000.00, 3511000.00, NULL, '2025-09-09 10:20:31'),
(8, 8, 27, 'Ubiquity LR', 1, 1, 2000000.00, 2000000.00, NULL, '2025-09-09 10:20:31'),
(9, 10, 28, '', 1, 1, 343000.00, 343000.00, NULL, '2025-09-09 10:28:11'),
(10, 10, 3, '', 2, 2, 5950000.00, 11900000.00, NULL, '2025-09-09 10:28:11'),
(11, 10, 24, '', 2, 2, 8300600.00, 16601200.00, NULL, '2025-09-09 10:28:11'),
(12, 11, 4, 'Printer HP LaserJet', 2, 2, 2500000.00, 5000000.00, NULL, '2025-09-09 10:29:49'),
(13, 12, 4, 'Printer HP LaserJet', 3, 3, 2500000.00, 7500000.00, NULL, '2025-09-09 10:31:36'),
(14, 13, 4, 'Printer HP LaserJet', 3, 3, 2500000.00, 7500000.00, NULL, '2025-09-09 10:38:44'),
(15, 14, 4, 'Printer HP LaserJet', 5, 5, 2500000.00, 12500000.00, NULL, '2025-09-09 10:51:38'),
(16, 15, 31, 'Produk Percobaan', 2, 2, 10000.00, 20000.00, NULL, '2025-09-10 04:11:19'),
(17, 16, 29, 'DAHUA XVR B04-1 8CH 2MP', 4, 4, 668000.00, 2672000.00, NULL, '2025-09-10 04:20:25'),
(18, 17, 20, 'laptop', 2, 2, 6211000.00, 12422000.00, NULL, '2025-09-10 05:52:58'),
(19, 18, 20, 'laptop', 2, 2, 6211000.00, 12422000.00, NULL, '2025-09-10 06:06:13'),
(20, 21, 3, '', 5, 5, 100000.00, 500000.00, NULL, '2025-09-10 06:25:31'),
(21, 22, 28, 'DAHUA XVR B04-1 4CH 2MP', 4, 0, 490000.00, 1960000.00, NULL, '2025-09-10 06:31:37'),
(22, 23, 14, 'paket cctv', 1, 0, 10904245.00, 10904245.00, NULL, '2025-09-10 07:24:04'),
(23, 24, 28, 'DAHUA XVR B04-1 4CH 2MP', 4, 0, 490000.00, 1960000.00, NULL, '2025-09-10 07:25:24'),
(24, 25, 30, 'SEAGATE HDD 1.0TB 3.5\"', 1, 0, 525000.00, 525000.00, NULL, '2025-09-10 07:51:43'),
(25, 26, 31, 'Produk Percobaan', 2, 0, 10000.00, 20000.00, NULL, '2025-09-11 02:37:35'),
(26, 27, 3, 'Laptop Asus VivoBook 14', 2, 2, 8500000.00, 17000000.00, NULL, '2025-09-11 02:46:21'),
(27, 27, 4, 'Printer HP LaserJet', 1, 1, 2500000.00, 2500000.00, NULL, '2025-09-11 02:46:21'),
(28, 27, 7, 'Mouse Wireless Logitech', 3, 3, 150000.00, 450000.00, NULL, '2025-09-11 02:46:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penerimaan_barang`
--

CREATE TABLE `penerimaan_barang` (
  `id` int(11) NOT NULL,
  `pembelian_id` int(11) NOT NULL,
  `nomor_penerimaan` varchar(20) NOT NULL,
  `tanggal_terima` date NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User yang menerima barang',
  `catatan` text DEFAULT NULL,
  `foto_bukti` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penerimaan_barang`
--

INSERT INTO `penerimaan_barang` (`id`, `pembelian_id`, `nomor_penerimaan`, `tanggal_terima`, `user_id`, `catatan`, `foto_bukti`, `created_at`) VALUES
(1, 7, 'GR-20250909-001', '2025-09-09', 1, 'Penerimaan barang produk Dahua - Auto processed', NULL, '2025-09-09 10:12:43'),
(2, 7, 'GR-20250909-002', '2025-09-09', 1, 'Penerimaan barang sisa dari PO yang belum lengkap', NULL, '2025-09-09 10:18:16'),
(3, 8, 'GR-20250909-003', '2025-09-09', 1, 'Penerimaan barang lengkap untuk PO-20250909-004', NULL, '2025-09-09 10:23:13'),
(4, 6, 'GR-20250909-004', '2025-09-09', 1, 'Penerimaan otomatis untuk melengkapi PO', NULL, '2025-09-09 10:25:02'),
(5, 10, 'GR-20250909-005', '2025-09-09', 1, 'Test penerimaan otomatis', NULL, '2025-09-09 10:28:11'),
(6, 13, 'GR-20250909-006', '2025-09-09', 1, 'Penerimaan barang untuk PO-20250909-008', NULL, '2025-09-09 10:46:48'),
(7, 12, 'GR-20250909-007', '2025-09-09', 1, 'Penerimaan barang untuk PO-20250909-007', NULL, '2025-09-09 10:48:25'),
(8, 11, 'GR-20250909-008', '2025-09-09', 1, 'Penerimaan barang untuk PO-20250909-006', NULL, '2025-09-09 10:48:25'),
(9, 14, 'GR-20250909-009', '2025-09-09', 1, 'Penerimaan barang untuk PO-20250909-009', NULL, '2025-09-09 11:04:47'),
(10, 15, 'GR-20250910-001', '2025-09-10', 1, 'Penerimaan otomatis untuk produk percobaan', NULL, '2025-09-10 04:17:27'),
(11, 18, 'GR-20250910-002', '2025-09-10', 1, 'Penerimaan otomatis untuk perbaikan data', NULL, '2025-09-10 06:12:11'),
(12, 17, 'GR-20250910-003', '2025-09-10', 1, 'Penerimaan otomatis untuk perbaikan data', NULL, '2025-09-10 06:12:11'),
(13, 16, 'GR-20250910-004', '2025-09-10', 1, 'Penerimaan otomatis untuk perbaikan data konsistensi', NULL, '2025-09-10 06:14:29'),
(14, 5, 'GR-20250910-005', '2025-09-10', 1, 'Penerimaan otomatis untuk perbaikan data konsistensi', NULL, '2025-09-10 06:14:29'),
(15, 21, 'GR-20250910-006', '2025-09-10', 1, '', NULL, '2025-09-10 07:50:59'),
(16, 27, 'GR-20250911-001', '2025-09-11', 1, 'Simulasi penerimaan barang lengkap', NULL, '2025-09-11 02:46:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penerimaan_detail`
--

CREATE TABLE `penerimaan_detail` (
  `id` int(11) NOT NULL,
  `penerimaan_id` int(11) NOT NULL,
  `pembelian_detail_id` int(11) NOT NULL,
  `quantity_terima` int(11) NOT NULL,
  `kondisi` enum('baik','rusak','cacat') DEFAULT 'baik',
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penerimaan_detail`
--

INSERT INTO `penerimaan_detail` (`id`, `penerimaan_id`, `pembelian_detail_id`, `quantity_terima`, `kondisi`, `catatan`) VALUES
(1, 1, 4, 1, 'baik', 'Produk Dahua dalam kondisi baik'),
(2, 2, 3, 1, 'baik', 'Penerimaan sisa barang'),
(3, 2, 5, 1, 'baik', 'Penerimaan sisa barang'),
(4, 3, 6, 2, 'baik', 'Penerimaan lengkap'),
(5, 3, 7, 1, 'baik', 'Penerimaan lengkap'),
(6, 3, 8, 1, 'baik', 'Penerimaan lengkap'),
(7, 4, 2, 2, 'baik', NULL),
(8, 5, 9, 1, 'baik', NULL),
(9, 5, 10, 2, 'baik', NULL),
(10, 5, 11, 2, 'baik', NULL),
(11, 6, 14, 3, 'baik', 'Penerimaan lengkap untuk Printer HP LaserJet'),
(12, 7, 13, 3, 'baik', 'Penerimaan lengkap untuk Printer HP LaserJet'),
(13, 8, 12, 2, 'baik', 'Penerimaan lengkap untuk Printer HP LaserJet'),
(14, 9, 15, 5, 'baik', 'Penerimaan lengkap untuk Printer HP LaserJet'),
(15, 10, 16, 2, 'baik', 'Kondisi baik - penerimaan otomatis'),
(16, 11, 19, 2, 'baik', 'Penerimaan otomatis untuk perbaikan data'),
(17, 12, 18, 2, 'baik', 'Penerimaan otomatis untuk perbaikan data'),
(18, 13, 17, 4, 'baik', 'Penerimaan otomatis untuk perbaikan data konsistensi'),
(19, 14, 1, 2, 'baik', 'Penerimaan otomatis untuk perbaikan data konsistensi'),
(20, 15, 20, 5, 'baik', 'diterima'),
(21, 16, 26, 2, 'baik', 'Kondisi barang baik'),
(22, 16, 27, 1, 'baik', 'Kondisi barang baik'),
(23, 16, 28, 3, 'baik', 'Kondisi barang baik');

--
-- Trigger `penerimaan_detail`
--
DELIMITER $$
CREATE TRIGGER `update_stok_after_penerimaan` AFTER INSERT ON `penerimaan_detail` FOR EACH ROW BEGIN
    DECLARE produk_id_var INT;
    
    
    SELECT pd.produk_id INTO produk_id_var
    FROM pembelian_detail pd
    WHERE pd.id = NEW.pembelian_detail_id;
    
    
    IF NEW.kondisi = 'baik' THEN
        UPDATE produk 
        SET stok_tersedia = stok_tersedia + NEW.quantity_terima
        WHERE id = produk_id_var;
    END IF;
    
    
    UPDATE pembelian_detail 
    SET quantity_terima = quantity_terima + NEW.quantity_terima
    WHERE id = NEW.pembelian_detail_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `peralatan`
--

CREATE TABLE `peralatan` (
  `id` int(11) NOT NULL,
  `kode_peralatan` varchar(20) NOT NULL,
  `nama_peralatan` varchar(100) NOT NULL,
  `kategori` enum('elektronik','tools','kendaraan','lainnya') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kondisi` enum('baik','rusak_ringan','rusak_berat','maintenance') DEFAULT 'baik',
  `lokasi_penyimpanan` varchar(100) DEFAULT NULL,
  `tanggal_beli` date DEFAULT NULL,
  `harga_beli` decimal(15,2) DEFAULT NULL,
  `masa_garansi` int(11) DEFAULT NULL COMMENT 'dalam bulan',
  `status` enum('tersedia','digunakan','maintenance','hilang') DEFAULT 'tersedia',
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `peralatan`
--

INSERT INTO `peralatan` (`id`, `kode_peralatan`, `nama_peralatan`, `kategori`, `deskripsi`, `kondisi`, `lokasi_penyimpanan`, `tanggal_beli`, `harga_beli`, `masa_garansi`, `status`, `foto`, `created_at`, `updated_at`) VALUES
(1, 'PRL001', 'Laptop Asus VivoBook', 'elektronik', 'Laptop untuk presentasi dan demo aplikasi', 'baik', 'Gudang Utama', NULL, NULL, NULL, 'tersedia', NULL, '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(2, 'PRL002', 'Proyektor Epson', 'elektronik', 'Proyektor untuk pelatihan dan presentasi', 'baik', 'Gudang Utama', NULL, NULL, NULL, 'tersedia', NULL, '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(3, 'PRL003', 'Kabel HDMI 5m', 'elektronik', 'Kabel penghubung laptop ke proyektor', 'baik', 'Gudang Utama', NULL, NULL, NULL, 'tersedia', NULL, '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(4, 'PRL004', 'Extension Cable 10m', 'elektronik', 'Kabel ekstensi listrik', 'baik', 'Gudang Utama', NULL, NULL, NULL, 'tersedia', NULL, '2025-08-24 12:07:48', '2025-08-26 12:55:31'),
(5, 'PRL005', 'Toolkit Komputer', 'tools', 'Set peralatan untuk maintenance komputer', 'baik', 'Gudang Utama', NULL, NULL, NULL, 'tersedia', NULL, '2025-08-24 12:07:48', '2025-08-24 12:07:48'),
(6, 'PRL006', 'UPS 1000VA', 'elektronik', 'Uninterruptible Power Supply', 'baik', 'Gudang Utama', NULL, NULL, NULL, 'tersedia', NULL, '2025-08-24 12:07:48', '2025-08-24 12:07:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peserta_pelatihan`
--

CREATE TABLE `peserta_pelatihan` (
  `id` int(11) NOT NULL,
  `pelatihan_id` int(11) NOT NULL,
  `nama_peserta` varchar(100) NOT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
  `desa_id` int(11) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status_kehadiran` enum('hadir','tidak_hadir','terlambat') DEFAULT NULL,
  `nilai_evaluasi` int(11) DEFAULT NULL,
  `sertifikat` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `piutang`
--

CREATE TABLE `piutang` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `desa_id` int(11) NOT NULL,
  `jumlah_piutang` decimal(15,2) NOT NULL,
  `tanggal_jatuh_tempo` date NOT NULL,
  `status` enum('belum_jatuh_tempo','mendekati_jatuh_tempo','terlambat') DEFAULT 'belum_jatuh_tempo',
  `denda` decimal(15,2) DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `piutang`
--

INSERT INTO `piutang` (`id`, `transaksi_id`, `desa_id`, `jumlah_piutang`, `tanggal_jatuh_tempo`, `status`, `denda`, `catatan`, `created_at`, `updated_at`) VALUES
(5, 65, 3, 500000.00, '2025-10-11', 'belum_jatuh_tempo', 0.00, NULL, '2025-09-11 04:41:03', '2025-09-11 04:41:03'),
(6, 66, 3, 700000.00, '2025-10-11', 'belum_jatuh_tempo', 0.00, NULL, '2025-09-11 04:41:03', '2025-09-11 04:41:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `kode_produk` varchar(20) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `jenis` enum('barang_it','atk','layanan') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `spesifikasi` text DEFAULT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `harga_grosir` decimal(15,2) DEFAULT NULL,
  `satuan` varchar(20) NOT NULL,
  `stok_minimal` int(11) DEFAULT 0,
  `stok_tersedia` int(11) DEFAULT 0,
  `gambar` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `harga_diskon` decimal(15,2) DEFAULT NULL COMMENT 'Harga setelah diskon',
  `is_featured` tinyint(1) DEFAULT 0 COMMENT '1 = produk unggulan, 0 = produk biasa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id`, `kode_produk`, `nama_produk`, `kategori_id`, `vendor_id`, `jenis`, `deskripsi`, `spesifikasi`, `harga_satuan`, `harga_grosir`, `satuan`, `stok_minimal`, `stok_tersedia`, `gambar`, `status`, `created_at`, `updated_at`, `harga_diskon`, `is_featured`) VALUES
(3, 'PRD001', 'Asus VivoBook 14 Intel Core i5 Laptop', 1, 1, 'barang_it', 'Laptop untuk keperluan administrasi desa', 'Asus VivoBook 14 Intel Core i5, RAM 8GB, SSD 512GB', 8500000.00, NULL, 'unit', 2, 9, '68b0ef8e9cb50.jpeg', 'nonaktif', '2025-08-25 03:01:49', '2025-09-13 02:50:09', NULL, 1),
(4, 'PRD002', 'HP LaserJet Printer', 2, 1, 'barang_it', 'Printer laser untuk dokumen desa', 'Monochrome, A4, USB/Network', 2500000.00, NULL, 'unit', 1, 4, '68b0efc1803d8.jpeg', 'aktif', '2025-08-25 03:01:49', '2025-09-11 06:21:14', NULL, 1),
(7, 'PRD005', 'Logitech M 170 Mouse Wireless', 1, 1, 'barang_it', 'Mouse wireless untuk komputer', '2.4GHz, Battery included', 150000.00, NULL, 'unit', 3, 14, '68ac19983815b.jpeg', 'aktif', '2025-08-25 03:01:49', '2025-09-13 04:45:51', 100000.00, 1),
(8, 'PRD003', 'Sinar Dunia Kertas A4 80gsm', 10, 1, 'atk', 'Kertas fotocopy ukuran A4', 'Putih, 500 lembar per rim', 55000.00, NULL, 'rim', 10, 95, '68c26d9de8016.jpeg', 'aktif', '2025-08-25 03:03:15', '2025-09-11 06:35:09', NULL, 0),
(9, 'PRD006', 'Pulpen Pilot', 10, 1, 'atk', 'Pulpen tinta biru', 'Tinta gel, 0.7mm', 5000.00, NULL, 'pcs', 10, 45, '68b0efec497f8.jpeg', 'aktif', '2025-08-25 03:03:15', '2025-09-12 02:43:52', NULL, 0),
(10, 'PRD007', 'Stapler Kenko', 10, 1, 'atk', 'Stapler ukuran sedang', 'Kapasitas 20 lembar', 25000.00, NULL, 'pcs', 5, 20, '68b0f006a534b.jpeg', 'aktif', '2025-08-25 03:03:15', '2025-08-29 00:10:46', NULL, 0),
(14, 'P-616', 'paket cctv', NULL, 1, 'barang_it', 'cctv 8ch', '', 10904245.00, NULL, 'paket', 10, 9, NULL, 'aktif', '2025-09-08 05:09:34', '2025-09-08 05:14:58', NULL, 0),
(16, 'P-439', 'Paket Desa Beji', NULL, 1, 'barang_it', 'Peralatan Elektronik & Studio', '', 81506500.00, NULL, 'paket', 10, 9, NULL, 'aktif', '2025-09-08 05:45:04', '2025-09-08 05:46:57', NULL, 0),
(17, 'H-550', 'Smartphone Android', NULL, 1, 'barang_it', '', '', 3511000.00, NULL, '1 pcs', 10, 9, NULL, 'aktif', '2025-09-08 06:32:41', '2025-09-08 06:38:38', NULL, 0),
(19, 'P-756', 'Paket Desa Kalilunjar', NULL, 1, 'barang_it', 'Scanner & Printer Epson', '', 9983000.00, NULL, 'paket', 10, 9, NULL, 'aktif', '2025-09-08 06:41:34', '2025-09-08 06:46:56', NULL, 0),
(20, 'L-896', 'laptop', NULL, 1, 'barang_it', '', '', 6211000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-08 07:01:08', '2025-09-08 07:01:52', NULL, 0),
(21, 'S-928', 'Seragam Pelatihan', NULL, 1, 'barang_it', '', '', 4650000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-09 01:49:26', '2025-09-09 01:50:24', NULL, 0),
(22, 'M-029', 'Mesin Potong Rumput', NULL, 1, 'barang_it', '', '', 7000000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-09 02:39:30', '2025-09-09 02:40:16', NULL, 0),
(23, 'M-338', 'Matrial Kayu PAUD', NULL, 1, 'barang_it', '', '', 9000000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-09 02:41:50', '2025-09-09 02:56:49', NULL, 0),
(24, 'M-551', 'Matrial PAUD', NULL, 1, 'barang_it', '', '', 11858000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-09 02:43:58', '2025-09-09 02:44:36', NULL, 0),
(25, 'M-522', 'Mesin Potong Rumput', NULL, 1, 'barang_it', '', '', 6201000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-09 02:46:29', '2025-09-09 02:47:03', NULL, 0),
(26, 'M-546', 'Matrial Kayu PAUD', NULL, 1, 'barang_it', '', '', 4162000.00, NULL, '', 10, 9, NULL, 'aktif', '2025-09-09 02:48:05', '2025-09-09 02:57:19', NULL, 0),
(27, 'KAOS-LENGA-344', 'Kaos Lengan Pendek Sablon (Ukuran S-XL)', 10, 1, 'barang_it', 'Kaos Lengan Pendek Sablon (Ukuran S-XL), sablon 1 tempat 1 warna', 'Kaos Lengan Pendek Sablon (Ukuran S-XL), sablon 1 tempat 1 warna', 70000.00, NULL, 'pcs', 10, 0, '68c27dfb47730.png', 'aktif', '2025-09-11 02:55:13', '2025-09-11 07:44:59', NULL, 0),
(28, 'KAOS-LENGA-969', 'Kaos Lengan Pendek Sablon (Ukuran XXL)', 10, 1, 'barang_it', 'Kaos Lengan Pendek Sablon (Ukuran XXL), sablon 1 tempat 1 warna', 'Kaos Lengan Pendek Sablon (Ukuran XXL), sablon 1 tempat 1 warna', 80000.00, NULL, 'pcs', 10, 0, '68c27e0c0154f.png', 'aktif', '2025-09-11 03:06:12', '2025-09-11 07:45:16', NULL, 0),
(29, 'KAOS-LENGA-155', 'Kaos Lengan Pendek Sablon (Ukuran XXXL)', 10, 1, 'barang_it', 'Kaos Lengan Pendek Sablon (Ukuran XXXL), sablon 1 tempat 1 warna', 'Kaos Lengan Pendek Sablon (Ukuran XXXL), sablon 1 tempat 1 warna', 90000.00, NULL, 'pcs', 10, 0, '68c27e1930f41.png', 'aktif', '2025-09-11 03:31:40', '2025-09-11 07:45:29', NULL, 0),
(30, 'KAOS-LENGA-248', 'Kaos Lengan Panjang Sablon (Ukuran S-XL)', 10, 1, 'barang_it', 'Kaos Lengan Panjang Sablon (Ukuran S-XL), sablon 1 tempat 1 warna', 'Kaos Lengan Panjang Sablon (Ukuran S-XL), sablon 1 tempat 1 warna', 80000.00, NULL, 'pcs', 10, 0, '68c27e4734bdc.jpg', 'aktif', '2025-09-11 03:48:32', '2025-09-11 07:46:15', NULL, 0),
(31, 'KAOS-LENGA-779', 'Kaos Lengan Panjang Sablon (Ukuran XXL)', 10, 1, 'barang_it', 'Kaos Lengan Panjang Sablon (Ukuran XXL), sablon 1 tempat 1 warna', 'Kaos Lengan Panjang Sablon (Ukuran XXL), sablon 1 tempat 1 warna', 90000.00, NULL, 'pcs', 10, 0, '68c27e5314468.jpg', 'aktif', '2025-09-11 03:53:40', '2025-09-11 07:46:27', NULL, 0),
(32, 'KAOS-LENGA-258', 'Kaos Lengan Panjang Sablon (Ukuran XXXL)', 10, 1, 'barang_it', 'Kaos Lengan Panjang Sablon (Ukuran XXXL), sablon 1 tempat 1 warna', 'Kaos Lengan Panjang Sablon (Ukuran XXXL), sablon 1 tempat 1 warna', 100000.00, NULL, '', 10, 0, '68c27e5f0e7b8.jpg', 'aktif', '2025-09-11 03:54:51', '2025-09-11 07:46:39', NULL, 0),
(33, 'BROTHER-S-756', 'Brother ADS-3100 Scanner', 2, 1, 'barang_it', 'ADS-3100 memiliki desain yang ringkas sangat ideal untuk bisnis kecil atau ruang kerja kantor maupun rumahan. Tidak perlu menyediakan ruang untuk mesin yang besar. Spesifikasi amp; Features : - Kecepatan Scan : 40 ppm colour/mono - Resolusi : up to 600x600 dpi (max resolution from ADF) up to 1200x1200 dpi (max resolution interpolated) - Kapasitas Kertas : 60 Lembar - Ukuran Kertas Minimum : 51 mm x 51 mm - Ukuran Kertas Maximum : 216 mm x 5,000 mm - Ketebalan Kertas : 40 - 200 gsm - Maximum Ketebalan Kertas : 1.32 mm - Power : Ready mode : 7W, Sleep mode : 1.5W - Teknologi Scanner : Dual CIS - Control Panel : Rubber Keys - Drivers : ICA, SANE, TWAIN, WIA - Koneksi : USB 3.0, Software Bawaan ABBYY FineReader Pro, ABBYY PDF Transformer Plus, BRAdmin Professional 4, Brother Control Center, Nuance PaperPort 14SE, Brother iPrint amp;Scan - Kompatibilitas : WIndows 11, Windows 10 (Home|Pro|Education|Enterprise) (32 or 64 bit editions), Windows 8.1(32 or 64 bit editions), Windows 7 SP1 (32 or 64 bit editions), Windows Server 2019, 2016, 2012R2, 2012, macOS 10.14.x or greater, Linux - Memory Standard : 512 MB - Fitur Unggulan Lainnya : Auto Scan Size, Auto Deskew, Skip Blank Page, Auto Colour Detection, Auto Image Rotation, Blurred Character Correction, Colour Dropout, Colour Tone Adjustment, Noise Reduction, Multifeed Detection, Punch Hole Removal, Quiet Mode, Plastic Card Mode Fitur Unggulan : Scan to email, Image, OCR, File, Scan to USB, Searchable PDF, USB Host - Dimensi (H x W x D) : 630 mm x 299 mm x 290 mm - Berat Unit : 2.6 kg Garasi Resmi 3 Tahun / 1.000.000 lembar', 'ADS-3100 memiliki desain yang ringkas sangat ideal untuk bisnis kecil atau ruang kerja kantor maupun rumahan. Tidak perlu menyediakan ruang untuk mesin yang besar. Spesifikasi amp; Features : - Kecepatan Scan : 40 ppm colour/mono - Resolusi : up to 600x600 dpi (max resolution from ADF) up to 1200x1200 dpi (max resolution interpolated) - Kapasitas Kertas : 60 Lembar - Ukuran Kertas Minimum : 51 mm x 51 mm - Ukuran Kertas Maximum : 216 mm x 5,000 mm - Ketebalan Kertas : 40 - 200 gsm - Maximum Ketebalan Kertas : 1.32 mm - Power : Ready mode : 7W, Sleep mode : 1.5W - Teknologi Scanner : Dual CIS - Control Panel : Rubber Keys - Drivers : ICA, SANE, TWAIN, WIA - Koneksi : USB 3.0, Software Bawaan ABBYY FineReader Pro, ABBYY PDF Transformer Plus, BRAdmin Professional 4, Brother Control Center, Nuance PaperPort 14SE, Brother iPrint amp;Scan - Kompatibilitas : WIndows 11, Windows 10 (Home|Pro|Education|Enterprise) (32 or 64 bit editions), Windows 8.1(32 or 64 bit editions), Windows 7 SP1 (32 or 64 bit editions), Windows Server 2019, 2016, 2012R2, 2012, macOS 10.14.x or greater, Linux - Memory Standard : 512 MB - Fitur Unggulan Lainnya : Auto Scan Size, Auto Deskew, Skip Blank Page, Auto Colour Detection, Auto Image Rotation, Blurred Character Correction, Colour Dropout, Colour Tone Adjustment, Noise Reduction, Multifeed Detection, Punch Hole Removal, Quiet Mode, Plastic Card Mode Fitur Unggulan : Scan to email, Image, OCR, File, Scan to USB, Searchable PDF, USB Host - Dimensi (H x W x D) : 630 mm x 299 mm x 290 mm - Berat Unit : 2.6 kg Garasi Resmi 3 Tahun / 1.000.000 lembar', 5495000.00, NULL, 'unit', 10, 10, '68c26c91d3da2.jpg', 'aktif', '2025-09-11 05:32:00', '2025-09-11 08:32:04', NULL, 1),
(34, 'SPC-PSU-45-897', 'SPC Power Supply 450W', NULL, 1, 'barang_it', '- AC Input 115 VAC or 230 VAC, 50-60 HZ\r\n- Dual +12V output\r\n- Nosiless 80 mm fan\r\n- 24 pin power cable\r\n- SATA power cable\r\n\r\n- 4P Molex power cable\r\n- SATA/ATA Ready\r\n- 80mm Silent Fan\r\n- 450 Watt\r\n- Pure 225 Watt\r\n- Recommended Power Supply for Intel & AMD', '- AC Input 115 VAC or 230 VAC, 50-60 HZ\r\n- Dual +12V output\r\n- Nosiless 80 mm fan\r\n- 24 pin power cable\r\n- SATA power cable\r\n\r\n- 4P Molex power cable\r\n- SATA/ATA Ready\r\n- 80mm Silent Fan\r\n- 450 Watt\r\n- Pure 225 Watt\r\n- Recommended Power Supply for Intel & AMD', 110000.00, NULL, 'unit', 10, 10, '68c26e794cf89.png', 'aktif', '2025-09-11 05:42:22', '2025-09-11 07:48:15', NULL, 1),
(35, 'P-634', 'Venomrx Power Core 500W PSU', NULL, 1, 'barang_it', '- 8Cm Fan\r\n- 115-240V Range Voltage\r\n- A+ Capasitor\r\n- A+ Component\r\n- 80% Efficiency\r\n- Rated Output Power 300W\r\n- AC Input 200-240V, 3.5A Max, 50-60Hz\r\n\r\nConnector:\r\n- ATX (20+4) PIN 450mm\r\n- ATX 4 PIN 450mm\r\n- MOLEX+MOLEX+SATA+SATA 900mm\r\n- Product Dimension: 150mmx110mmx86mm', '- 8Cm Fan\r\n- 115-240V Range Voltage\r\n- A+ Capasitor\r\n- A+ Component\r\n- 80% Efficiency\r\n- Rated Output Power 300W\r\n- AC Input 200-240V, 3.5A Max, 50-60Hz\r\n\r\nConnector:\r\n- ATX (20+4) PIN 450mm\r\n- ATX 4 PIN 450mm\r\n- MOLEX+MOLEX+SATA+SATA 900mm\r\n- Product Dimension: 150mmx110mmx86mm', 150000.00, NULL, 'unit', 10, 10, '68c26f48486a5.jpg', 'aktif', '2025-09-11 05:49:42', '2025-09-11 06:42:16', NULL, 0),
(36, 'TP-LINK-WR-028', 'TP-Link WR840N 300M Router Wireless Lan', 3, 1, 'barang_it', '- Tingkat transmisi nirkabel 300Mbps ideal untuk tugas sensitif bandwidth dan pekerjaan dasar\r\n- Mendukung mode Titik Akses untuk membuat titik akses Wi-Fi baru\r\n- Mendukung mode Range Extender untuk meningkatkan jangkauan nirkabel yang ada di kamar anda\r\n- Kontrol Orang Tua mengatur kapan dan bagaimana perangkat yang terhubung dapat mengakses internet\r\n- IPTV mendukung IGMP Proxy / Snooping, Bridge dan Tag VLAN untuk mengoptimalkan streaming - \r\n  IPTV\r\n- kompatibel dengan IPv6 (Protokol Internet versi 6)\r\n- Guest Network menyediakan akses terpisah untuk tamu sambil mengamankan jaringan rumah\r\n- Guest Network 2.4GHz', '- Interface : 4 10/100Mbps LAN PORTS, 1 10/100Mbps WAN PORT\r\n- Button : WPS/RESET Button\r\n- Antenna : 2 Antennas\r\n- External Power Supply : 9VDC / 0.6A\r\n- Wireless Standards : IEEE 802.11n, IEEE 802.11g, IEEE 802.11b\r\n- Dimensions ( W x D x H ) : 7.2 x 5.0 x 1.4in.(182 x 128 x 35 mm)\r\n- Wireless Functions : Enable/Disable Wireless Radio, WDS Bridge, WMM, Wireless Statistics\r\n- Wireless Security : 64/128-bit WEP, WPA / WPA2,WPA-PSK / WPA2-PSK\r\n- Quality of Service : WMM, Bandwidth Control\r\n- WAN Type : Dynamic IP/Static IP/PPPoE/ PPTP/L2TP\r\n- Management : Access Control, Local Management, Remote Management\r\n- DHCP : Server, Client, DHCP Client List, Address Reservation\r\n- Port Forwarding : Virtual Server,Port Triggering, UPnP, DMZ\r\n- Dynamic DNS : DynDns, Comexe, NO-IP\r\n- VPN Pass-Through : PPTP, L2TP, IPSec (ESP Head)\r\n- Access Control : Parental Control, Local Management Control, Host List, Access Schedule, Rule Management\r\n- Firewall Security : DoS, SPI Firewall, IP Address Filter/MAC Address Filter/Domain Filter, IP and MAC Address Binding\r\n- Protocols : Support IPv4 and IPv6\r\n- Guest Network : 2.4GHz Guest Network x1\r\n- Certification : CE, RoHS\r\n- Package Contents : TL-WR840N, Power supply unit, Resource CD, Ethernet Cable, Quick Installation Guide\r\n- System Requirements : Windows 2000/XP/Vista, Windows 7, Windows 8, Windows 8.1, Windows 10 or Mac OS or Linux-based operating system', 150000.00, NULL, 'unit', 10, 0, '68c26ef59ab3f.jpeg', 'aktif', '2025-09-11 05:57:50', '2025-09-13 08:10:19', NULL, 0),
(37, 'VENOMRX-11-277', 'Venomrx 1150 H81 MA2-V3 (+Slot NVME)', NULL, 1, 'barang_it', 'Support NVME M,2 Slot 1 buah Processor : Support Intel Core i7/i5/i3/Pentium/ Celeron processors in the LGA1150 package. Support Intel 4th generation multi-core processors Chipset : Intel H81 On-Board graphics : Integrated Graphics Processor Memory Support : Dual Channel DDR3 1333/1600MHz Memory, up to 16GB Dimm 2 x Memory Slots (Max. up to 8GB each) LAN : Realtek RTL8105E 100M / RTL8111E 1000 Mbps on board Audio Chip : integrated by Realtek ALC662 on board, Supports 6-channel audio-out Slot : 1 x PCI Express x16 Slot Storage : 4 x SATA II port Internal Slot : I/O Ports 1 x 24-pin ATX Power Connetor 1 x 4-pin ATX 12V Power Connetor 1 x USB 2.0 connector support additional 2 USB 2.0 ports 1 x Front panel audio connector 1 x System Panel connector Back I/O Ports : 1 x USB 2.0 keyboard port 1 x USB 2.0 mouse port 1 x VGA 1 x HDMI 2 x USB 2.0 2 x USB 3.0 1 x LAN jack 3 x Audio jack BIOS : AMI BIOS Form Factor : Micro ATX - 190*170 mm Accessories : 1 x Driver CD 1 x User Manual 1 x I/O Backboard 1 x SATA cable', 'Support NVME M,2 Slot 1 buah Processor : Support Intel Core i7/i5/i3/Pentium/ Celeron processors in the LGA1150 package. Support Intel 4th generation multi-core processors Chipset : Intel H81 On-Board graphics : Integrated Graphics Processor Memory Support : Dual Channel DDR3 1333/1600MHz Memory, up to 16GB Dimm 2 x Memory Slots (Max. up to 8GB each) LAN : Realtek RTL8105E 100M / RTL8111E 1000 Mbps on board Audio Chip : integrated by Realtek ALC662 on board, Supports 6-channel audio-out Slot : 1 x PCI Express x16 Slot Storage : 4 x SATA II port Internal Slot : I/O Ports 1 x 24-pin ATX Power Connetor 1 x 4-pin ATX 12V Power Connetor 1 x USB 2.0 connector support additional 2 USB 2.0 ports 1 x Front panel audio connector 1 x System Panel connector Back I/O Ports : 1 x USB 2.0 keyboard port 1 x USB 2.0 mouse port 1 x VGA 1 x HDMI 2 x USB 2.0 2 x USB 3.0 1 x LAN jack 3 x Audio jack BIOS : AMI BIOS Form Factor : Micro ATX - 190*170 mm Accessories : 1 x Driver CD 1 x User Manual 1 x I/O Backboard 1 x SATA cable', 345000.00, NULL, 'unit', 10, 10, '68c26f1920a6a.jpg', 'aktif', '2025-09-11 06:05:33', '2025-09-11 06:41:29', NULL, 0),
(38, 'M-548', 'MSI Modern 14 C12MO Laptop', 1, 1, 'barang_it', 'Processor : 12th Gen Intel?? Core??? i5-1235U processor, 10 cores (2 P-cores + 8 E-cores), Max Turbo Frequency 4.4 GHz\r\nDisplay : 14??? FHD (1920*1080), 60Hz, 45%NTSC IPS-Level\r\nMemory : 16GB DDR4-3200 Onboard\r\nStorage : 1TB NVMe PCIe Gen3x4 SSD\r\nGraphics : Intel?? Iris?? Xe graphics\r\nKeyboard : Backlight Keyboard (Single-Color, White)\r\nWireless : Intel?? Wi-Fi 6E AX211, Bluetooth v5.3\r\nConectivity : 1x Type-C USB3.2 Gen2 with PD charging; 1x Type-A USB3.2 Gen2; 2x Type-A USB2.0; 1x (4K @ 30Hz) HDMI; 1x Micro SD Card Reader\r\nWebcam : HD type (30fps@720p)\r\nAudio : 2x 2W Speaker\r\nBattery : 3 cell, 39.3Whr, 65W adapter\r\nOS : Windows 11 Home + OHS 2021', 'Processor : 12th Gen Intel?? Core??? i5-1235U processor, 10 cores (2 P-cores + 8 E-cores), Max Turbo Frequency 4.4 GHz\r\nDisplay : 14??? FHD (1920*1080), 60Hz, 45%NTSC IPS-Level\r\nMemory : 16GB DDR4-3200 Onboard\r\nStorage : 1TB NVMe PCIe Gen3x4 SSD\r\nGraphics : Intel?? Iris?? Xe graphics\r\nKeyboard : Backlight Keyboard (Single-Color, White)\r\nWireless : Intel?? Wi-Fi 6E AX211, Bluetooth v5.3\r\nConectivity : 1x Type-C USB3.2 Gen2 with PD charging; 1x Type-A USB3.2 Gen2; 2x Type-A USB2.0; 1x (4K @ 30Hz) HDMI; 1x Micro SD Card Reader\r\nWebcam : HD type (30fps@720p)\r\nAudio : 2x 2W Speaker\r\nBattery : 3 cell, 39.3Whr, 65W adapter\r\nOS : Windows 11 Home + OHS 2021', 7200000.00, NULL, 'unit', 10, 10, '68c26e185e3b1.jpg', 'aktif', '2025-09-11 06:09:56', '2025-09-11 06:37:12', NULL, 0),
(39, 'P-955', 'EPSON L4260 Printer', 2, 1, 'barang_it', 'Print - Scan - Copy\r\nwifi direct - epson connet - epson smart panel\r\nDuplex printing\r\nKecepatan cetak draf A4 (hitam/ warna) : 33/15ppm\r\nIso 24734 simpleks A4(hitam/warna) : 10.5/5.0 ipm\r\nIso 24734 dupleks A4 (hitam) : 6.0/4.0 ipm\r\nKecepatan cetak foto 4R,Default,Border : 69 detik\r\nKecepatan cetak foto 4R tanpa border : 92 detik\r\nHasil halaman (hitam/warna) : 7,500/6,000 halaman\r\nResolusi cetak maks : 5.760/1,440 dpi\r\nResolusi Optik Pemindai : 1,200/2,400 dpi\r\nModel Tinta : 001\r\nDimensi (W x D x H) : 375 x 347 x 187 mm\r\nBerat tanpa packaging : 5,4 kg\r\nMaximum Paper Size: 215.9 x 1200 mm\r\nOperating System Compatibility: Windows XP / XP Professional / Vista / 7 / 8 / 8.1 / 10\r\nWindows Server 2003 / 2008 / 2012 / 2016 / 2019\r\nOnly printing functions are supported for Windows Server OS\r\nMac OS X 10.6.8 or later', 'Print - Scan - Copy\r\nwifi direct - epson connet - epson smart panel\r\nDuplex printing\r\nKecepatan cetak draf A4 (hitam/ warna) : 33/15ppm\r\nIso 24734 simpleks A4(hitam/warna) : 10.5/5.0 ipm\r\nIso 24734 dupleks A4 (hitam) : 6.0/4.0 ipm\r\nKecepatan cetak foto 4R,Default,Border : 69 detik\r\nKecepatan cetak foto 4R tanpa border : 92 detik\r\nHasil halaman (hitam/warna) : 7,500/6,000 halaman\r\nResolusi cetak maks : 5.760/1,440 dpi\r\nResolusi Optik Pemindai : 1,200/2,400 dpi\r\nModel Tinta : 001\r\nDimensi (W x D x H) : 375 x 347 x 187 mm\r\nBerat tanpa packaging : 5,4 kg\r\nMaximum Paper Size: 215.9 x 1200 mm\r\nOperating System Compatibility: Windows XP / XP Professional / Vista / 7 / 8 / 8.1 / 10\r\nWindows Server 2003 / 2008 / 2012 / 2016 / 2019\r\nOnly printing functions are supported for Windows Server OS\r\nMac OS X 10.6.8 or later', 3400000.00, NULL, 'unit', 10, 10, '68c26cf313527.jpg', 'aktif', '2025-09-11 06:14:35', '2025-09-11 06:32:19', NULL, 0),
(40, 'P-619', 'EPSON L3250 Printer', 2, 1, 'barang_it', 'Print, scan, copy (Full A4)\r\nTinta Anti Tumpah\r\nWi-Fi & Wi-Fi Direct\r\nBorderless Hingga 4R\r\n\r\nSupport Tinta Tipe :\r\nEpson 003', 'Print, scan, copy (Full A4)\r\nTinta Anti Tumpah\r\nWi-Fi & Wi-Fi Direct\r\nBorderless Hingga 4R\r\n\r\nSupport Tinta Tipe :\r\nEpson 003', 2450000.00, NULL, 'unit', 10, 10, '68c26cc2336f6.jpg', 'aktif', '2025-09-11 06:17:44', '2025-09-11 07:58:31', NULL, 1),
(41, 'M-769', 'Logitech Mouse Wireless MK170', 1, 1, 'barang_it', 'Teknologi sensor\r\nPenelusuran optik yang mulus\r\nDPI (Min./Maks.): 1000??\r\nTombol\r\nJumlah tombol: 3 (Klik Kiri/Kanan, Klik Tengah)\r\nScrolling\r\nPengguliran baris demi baris\r\nScroll Wheel: Ya, 2D, optik\r\nBaterai\r\nBaterai: 12 bulan 6Daya tahan baterai mungkin bervariasi, berdasarkan pengguna dan komputasi.\r\nInformasi Baterai: 1 X AA (disertakan)\r\nKonektivitas\r\nJenis Koneksi: Koneksi wireless 2.4 GHz\r\nJangkauan wireless: 10 m 7Jangkauan wireless mungkin beragam, bergantung pada pengguna, kondisi lingkungan, dan komputasi.\r\nKoneksi / Daya: Ya, switch on/off', 'Teknologi sensor\r\nPenelusuran optik yang mulus\r\nDPI (Min./Maks.): 1000??\r\nTombol\r\nJumlah tombol: 3 (Klik Kiri/Kanan, Klik Tengah)\r\nScrolling\r\nPengguliran baris demi baris\r\nScroll Wheel: Ya, 2D, optik\r\nBaterai\r\nBaterai: 12 bulan 6Daya tahan baterai mungkin bervariasi, berdasarkan pengguna dan komputasi.\r\nInformasi Baterai: 1 X AA (disertakan)\r\nKonektivitas\r\nJenis Koneksi: Koneksi wireless 2.4 GHz\r\nJangkauan wireless: 10 m 7Jangkauan wireless mungkin beragam, bergantung pada pengguna, kondisi lingkungan, dan komputasi.\r\nKoneksi / Daya: Ya, switch on/off', 130000.00, NULL, 'unit', 10, 10, '68c26f96c6baa.jpeg', 'aktif', '2025-09-11 06:42:33', '2025-09-12 08:31:25', NULL, 0),
(42, 'D-273', 'DAHUA XVR1B16-I 16CH H265+', NULL, 1, 'barang_it', 'H.265+/H.265 dual-stream video compression\r\nSupports Full-channel AI-Coding\r\nSupports HDCVI/AHD/TVI/CVBS/IP video inputs\r\nMax 18 channels IP camera inputs, each channel up to 6MP; Max 64 Mbps Incoming Bandwidth\r\nLong transmission distance over coax cable\r\nUp to 8 channels video stream ( analog channel ) SMD Plus', 'H.265+/H.265 dual-stream video compression\r\nSupports Full-channel AI-Coding\r\nSupports HDCVI/AHD/TVI/CVBS/IP video inputs\r\nMax 18 channels IP camera inputs, each channel up to 6MP; Max 64 Mbps Incoming Bandwidth\r\nLong transmission distance over coax cable\r\nUp to 8 channels video stream ( analog channel ) SMD Plus', 1365000.00, NULL, 'unit', 10, 10, '68c27304d7884.jpeg', 'aktif', '2025-09-11 06:47:44', '2025-09-12 04:54:25', NULL, 0),
(43, 'DAHUA-HACT-553', 'DAHUA HAC-T1A21-U 2MP Camera Indoor', NULL, 1, 'barang_it', 'Max. 30 fps@1080p\r\nSmart IR Illumination\r\n25 m illumination distance\r\nQuick-to-install eyeball saves installation time.\r\n3.6 mm fixed lens (2.8 mm optional).\r\nCVI/CVBS/AHD/TVI switchable.', 'Max. 30 fps@1080p\r\nSmart IR Illumination\r\n25 m illumination distance\r\nQuick-to-install eyeball saves installation time.\r\n3.6 mm fixed lens (2.8 mm optional).\r\nCVI/CVBS/AHD/TVI switchable.', 160000.00, NULL, 'unit', 10, 10, '68c272e84a719.png', 'aktif', '2025-09-11 06:51:11', '2025-09-11 06:57:44', NULL, 0),
(44, 'S-316', 'SEAGATE SKYHWAK MFI 4.0 TB', NULL, 1, 'barang_it', 'Form Factor: 3.5 Inch\r\nKapasitas: 1TB / 2TB / 4TB / 6TB\r\nDimensi: 146.9 x 101.8 x 20.2 mm\r\n???Performance\r\nInterface: SATA III 6Gb/s\r\nTransfer Rate: hingga 180 MB/s\r\nRotation Speed: 5400 RPM\r\nCache: 256MB\r\n???Features\r\nMendukung hingga 8 drive bays\r\nMendukung hingga 64 kamera\r\nRecording Technology: SMR\r\n???Reliability\r\nPower On Hours: 8760 jam/tahun (24x7)\r\nWorkload Rate Limit: 180TB/tahun\r\nMTBF: 1.000.000 jam\r\nGaransi resmi: 3 tahun\r\nSeagate Rescue Data Recovery: 3 tahun\r\n???Power Management\r\nStartup Power: 1.8A\r\nIdle Average: 2.5W\r\nOperating Mode: 3.7W\r\nSleep Mode: 0.25W\r\n???Environmental\r\nOperating Temp: 0??C ??? 70??C\r\nNon Operating Temp: -40??C', 'Form Factor: 3.5 Inch\r\nKapasitas: 1TB / 2TB / 4TB / 6TB\r\nDimensi: 146.9 x 101.8 x 20.2 mm\r\n???Performance\r\nInterface: SATA III 6Gb/s\r\nTransfer Rate: hingga 180 MB/s\r\nRotation Speed: 5400 RPM\r\nCache: 256MB\r\n???Features\r\nMendukung hingga 8 drive bays\r\nMendukung hingga 64 kamera\r\nRecording Technology: SMR\r\n???Reliability\r\nPower On Hours: 8760 jam/tahun (24x7)\r\nWorkload Rate Limit: 180TB/tahun\r\nMTBF: 1.000.000 jam\r\nGaransi resmi: 3 tahun\r\nSeagate Rescue Data Recovery: 3 tahun\r\n???Power Management\r\nStartup Power: 1.8A\r\nIdle Average: 2.5W\r\nOperating Mode: 3.7W\r\nSleep Mode: 0.25W\r\n???Environmental\r\nOperating Temp: 0??C ??? 70??C\r\nNon Operating Temp: -40??C', 1700000.00, NULL, 'unit', 10, 10, '68c27d2bd393c.jpeg', 'aktif', '2025-09-11 06:58:31', '2025-09-11 07:41:31', NULL, 0),
(45, 'K-073', 'Konektor DC Male', NULL, 1, 'barang_it', '1. Kualitas Tinggi: Sangat tahan lama, cocok untuk penggunaan jangka panjang.\r\n2. Jenis Konektor: DC Male Connector 2.1 x 5.5mm.\r\n3. Material: Plastik dengan strain relief untuk menjaga kestabilan koneksi.\r\n4. Diameter Internal: 2.1mm.\r\n5. Diameter Eksternal: 5.5mm.\r\n6. Dimensi: 38mm (P) x 14mm (L) x 13mm (T).', '1. Kualitas Tinggi: Sangat tahan lama, cocok untuk penggunaan jangka panjang.\r\n2. Jenis Konektor: DC Male Connector 2.1 x 5.5mm.\r\n3. Material: Plastik dengan strain relief untuk menjaga kestabilan koneksi.\r\n4. Diameter Internal: 2.1mm.\r\n5. Diameter Eksternal: 5.5mm.\r\n6. Dimensi: 38mm (P) x 14mm (L) x 13mm (T).', 2500.00, NULL, 'unit', 10, 10, '68c27caf19b2c.jpeg', 'aktif', '2025-09-11 07:01:26', '2025-09-11 07:39:27', NULL, 0),
(46, 'KONEKTOR-B-424', 'KONEKTOR BNC DRAT TW', NULL, 1, 'barang_it', '- Support kabel \r\n- Mudah pemasangan\r\n- Good Quality', '- Support kabel \r\n- Mudah pemasangan\r\n- Good Quality', 3000.00, NULL, 'unit', 10, 10, '68c27c959634d.jpg', 'aktif', '2025-09-11 07:04:03', '2025-09-11 07:39:01', NULL, 0),
(47, 'A-777', 'ACER TRAVELMATE TMP214/0041 6 GB 512G', 1, 1, 'barang_it', '- Display : 14 Inch\r\n- Processor : i5-1335U (Cache 12 M, hingga 4,60 GHz)\r\n- Memory : 8GB DDR4\r\n- Storage : 512GB SSD\r\n- Graphics : Intel UHD Graphics\r\n- OS : Windows 11 Home\r\n- LAN, WiFi, Bluetooth', '??? Display : 14 Inch\r\n??? Processor : i5-1335U (Cache 12 M, hingga 4,60 GHz)\r\n??? Memory : 8GB DDR4\r\n??? Storage : 512GB SSD\r\n??? Graphics : Intel UHD Graphics\r\n??? OS : Windows 11 Home\r\n??? LAN, WiFi, Bluetooth', 12263000.00, NULL, 'unit', 10, 10, '68c27bcbc2c22.jpeg', 'aktif', '2025-09-11 07:11:16', '2025-09-12 08:08:21', NULL, 0),
(48, 'DAHUA-XVR5-069', 'DAHUA XVR5116HS-5M-I3 DVR 16CH H265+', NULL, 1, 'barang_it', '> 2-channel QuickPick 2.0 for analog channels which improves the efficiency of target retrieval. \r\n> 16-channel SMD Plus for analog channels or 24-channel SMD by camera which allows the device to trigger accurate alarms for humans and vehicles. \r\n> 4-channel perimeter protection for analog channels which supports tripwire and intrusion detection powered by AI. \r\n> 2-channel face recognition for analog channels which supports face comparison. \r\n> 16-channel AI Coding saves on storage and bandwidth while maintaining the details of targets in videos. \r\n> Supports HDCVI, AHD, TVI, CVBS and IP video input. \r\n> Supports up to 24-channel IP camera input and each channel can be up to 6 MP. The incoming bandwidth reaches 128 Mbps. \r\n> Smart H.265+ and H.265 dual-stream video compression. \r\n> IoT and POS capabilities', '> 2-channel QuickPick 2.0 for analog channels which improves the efficiency of target retrieval. \r\n> 16-channel SMD Plus for analog channels or 24-channel SMD by camera which allows the device to trigger accurate alarms for humans and vehicles. \r\n> 4-channel perimeter protection for analog channels which supports tripwire and intrusion detection powered by AI. \r\n> 2-channel face recognition for analog channels which supports face comparison. \r\n> 16-channel AI Coding saves on storage and bandwidth while maintaining the details of targets in videos. \r\n> Supports HDCVI, AHD, TVI, CVBS and IP video input. \r\n> Supports up to 24-channel IP camera input and each channel can be up to 6 MP. The incoming bandwidth reaches 128 Mbps. \r\n> Smart H.265+ and H.265 dual-stream video compression. \r\n> IoT and POS capabilities', 2550000.00, NULL, 'unit', 10, 10, '68c27c79edd27.jpeg', 'aktif', '2025-09-11 07:14:14', '2025-09-11 07:38:33', NULL, 0),
(49, 'DAHUA-HACB-580', 'DAHUA HAC-B1A21-U, 2.0MP Camera Outdoor', NULL, 1, 'barang_it', '> Max. 30 fps@1080p.\r\n> Smart IR Illumination\r\n> 30 m illumination distance.\r\n> 3.6 mm fixed lens (2.8 mm, 6 mm optional)\r\n> CVI/CVBS/AHD/TVI switchable.\r\n> IP67, 12 VDC.', '> Max. 30 fps@1080p.\r\n> Smart IR Illumination\r\n> 30 m illumination distance.\r\n> 3.6 mm fixed lens (2.8 mm, 6 mm optional)\r\n> CVI/CVBS/AHD/TVI switchable.\r\n> IP67, 12 VDC.', 170000.00, NULL, 'unit', 10, 10, '68c27c5d85b59.jpeg', 'aktif', '2025-09-11 07:16:41', '2025-09-12 04:54:57', NULL, 0),
(50, 'DAHUA-HACB-697', 'DAHUA HAC-B1A51P, 2.0MP Camera Analog Outdoor 5MP', NULL, 1, 'barang_it', '- Max. 25 fps@5MP (16:9 video output)\r\n- CVI/CVBS/AHD/TVI switchable\r\n- 3.6 mm fixed lens (2.8 mm optional)\r\n- Max. IR length 20 m, Smart IR\r\n- IP67, DC12V', '??? Max. 25 fps@5MP (16:9 video output)\r\n??? CVI/CVBS/AHD/TVI switchable\r\n??? 3.6 mm fixed lens (2.8 mm optional)\r\n??? Max. IR length 20 m, Smart IR\r\n??? IP67, DC12V', 385000.00, NULL, 'unit', 10, 10, '68c27c3776a77.jpg', 'aktif', '2025-09-11 07:18:30', '2025-09-12 08:07:44', NULL, 0),
(51, 'A-096', 'ASUS Vivobook 14 A1404Va', 1, 1, 'barang_it', '- Processor : Intel Core i5-1335U Processor 1.3 GHz (12MB Cache, up to 4.6 GHz, 10 cores, 12 Threads),\r\n- Memory : 8GB DDR4 on board, Upgradable (1x DDR4 SO-DIMM slot) Up to 16GB DDR4,\r\n- Storage : 512GB M.2 NVMe PCIe 4.0 SSD,\r\n- Graphics : Intel UHD Graphics (Intel Iris Xe Graphics eligible),\r\n??? Display : 14.0-inch, FHD (1920 x 1080) 16:9 aspect ratio,LED Backlit, IPS-level Panel, 60Hz refresh rate, 250nits HDR peak brightness, 45% NTSC color gamut, Anti-glare display,\r\n??? Keyboard : Backlit Chiclet Keyboard + Fingerprint (integrated with Touchpad),\r\n??? Wireless : Wi-Fi 6E(802.11ax) (Dual band) 1*1 + Bluetooth 5.3,\r\n??? Webcam : 720p HD camera With privacy shutter,\r\n??? Audio : SonicMaster Built-in speaker Built-in array microphone, with Cortana and Alexa voice-recognition support,\r\n??? Battery : 42WHrs, 3S1P, 3-cell Li-ion, 45W AC Adapter,\r\n??? OS : Windows 11 Home + OHS 2021.', '??? Processor : Intel Core i5-1335U Processor 1.3 GHz (12MB Cache, up to 4.6 GHz, 10 cores, 12 Threads),\r\n??? Memory : 8GB DDR4 on board, Upgradable (1x DDR4 SO-DIMM slot) Up to 16GB DDR4,\r\n??? Storage : 512GB M.2 NVMe PCIe 4.0 SSD,\r\n??? Graphics : Intel UHD Graphics (Intel Iris Xe Graphics eligible),\r\n??? Display : 14.0-inch, FHD (1920 x 1080) 16:9 aspect ratio,LED Backlit, IPS-level Panel, 60Hz refresh rate, 250nits HDR peak brightness, 45% NTSC color gamut, Anti-glare display,\r\n??? Keyboard : Backlit Chiclet Keyboard + Fingerprint (integrated with Touchpad),\r\n??? Wireless : Wi-Fi 6E(802.11ax) (Dual band) 1*1 + Bluetooth 5.3,\r\n??? Webcam : 720p HD camera With privacy shutter,\r\n??? Audio : SonicMaster Built-in speaker Built-in array microphone, with Cortana and Alexa voice-recognition support,\r\n??? Battery : 42WHrs, 3S1P, 3-cell Li-ion, 45W AC Adapter,\r\n??? OS : Windows 11 Home + OHS 2021.', 8450000.00, NULL, 'unit', 10, 10, '68c27bf42c657.png', 'aktif', '2025-09-11 07:22:30', '2025-09-13 03:30:33', NULL, 1),
(52, 'BT-ADAPTER-886', 'TP-LINK UB400 BLUETOOTH 4.0 USB BT ADAPTER', NULL, 1, 'barang_it', 'Bluetooth 4.0 terbaru dengan teknologi rendah energi (BLE) dan kompatibel dengan Bluetooth V3.0, 2.1, 2.0, 1.1. Plug and Play untuk Win 8, Win 8.1, dan Win 10. Ultra-kecil untuk portabilitas yang nyaman dengan kinerja tinggi yang andal. Mendukung Windows 10, 8.1, 8, 7, XP.TP-LINK BLUETOOTH 4.0 NANO USB ADAPTERFEATURES :- Bluetooth 4.0 Applies the latest Bluetooth 4.0 with low energy (BLE) technology and it is backward compatible with Bluetooth V3.0/2.1/2.0/1.1- Driver Free Plug and Play for Win 8, Win 8.1, and Win 10- NanoSized Ultrasmall for convenient portability with reliable high performance- Supported Operating System Supports Windows 10/8.1/8/7/XPTECHNICAL SPECIFICATIONS :- Standards and Protocols : Bluetooth 4.0- Interface : USB 2.0- Dimensions ( W x D x H ) : 0.58 0.27 0.74 in (14.8 6.8 18.9 mm)- Certification : FCC, CE, RoHS- Package Contents : Bluetooth 4.0 Nano USB Adapter UB400- System Requirements : Windows 10/8.1/8/7/XP- Operating Temperature: 0~40 (32 ~104)- Storage Temperature: 40~70 (40 ~158)- Operating Humidity: 10%~90% noncondensing- Storage Humidity: 5%~90% noncondensing100% Originalwith 1 year(s) warranty', 'Bluetooth 4.0 terbaru dengan teknologi rendah energi (BLE) dan kompatibel dengan Bluetooth V3.0, 2.1, 2.0, 1.1. Plug and Play untuk Win 8, Win 8.1, dan Win 10. Ultra-kecil untuk portabilitas yang nyaman dengan kinerja tinggi yang andal. Mendukung Windows 10, 8.1, 8, 7, XP.TP-LINK BLUETOOTH 4.0 NANO USB ADAPTERFEATURES :- Bluetooth 4.0 Applies the latest Bluetooth 4.0 with low energy (BLE) technology and it is backward compatible with Bluetooth V3.0/2.1/2.0/1.1- Driver Free Plug and Play for Win 8, Win 8.1, and Win 10- NanoSized Ultrasmall for convenient portability with reliable high performance- Supported Operating System Supports Windows 10/8.1/8/7/XPTECHNICAL SPECIFICATIONS :- Standards and Protocols : Bluetooth 4.0- Interface : USB 2.0- Dimensions ( W x D x H ) : 0.58 0.27 0.74 in (14.8 6.8 18.9 mm)- Certification : FCC, CE, RoHS- Package Contents : Bluetooth 4.0 Nano USB Adapter UB400- System Requirements : Windows 10/8.1/8/7/XP- Operating Temperature: 0~40 (32 ~104)- Storage Temperature: 40~70 (40 ~158)- Operating Humidity: 10%~90% noncondensing- Storage Humidity: 5%~90% noncondensing100% Originalwith 1 year(s) warranty', 90000.00, NULL, 'unit', 10, 10, '68c27c1b27c3f.jpg', 'aktif', '2025-09-11 07:25:38', '2025-09-11 07:36:59', NULL, 0),
(53, 'UTP-COMPLI-924', 'RJ45 AMP Commscope Connector RJ 45', NULL, 1, 'barang_it', '- UTP Compliant Standards : CAT type 3/4/5/5e\r\n- Cable Function : Connector\r\n- Package Qty : 50 pcs', '- UTP Compliant Standards : CAT type 3/4/5/5e\r\n- Cable Function : Connector\r\n- Package Qty : 50 pcs', 75000.00, NULL, 'unit', 10, 10, '68c27d0809668.jpeg', 'aktif', '2025-09-11 07:29:03', '2025-09-11 07:40:56', NULL, 0),
(54, 'P-685', 'EPSON L3210 Printer', 2, 1, 'barang_it', '- Teknologi cetak: Heat-FreeTM MicroPiezo?? 4-Color Inkjet (CMYK)\r\n- Resolusi cetak maksimum: Hingga 5.760 dpi x 1.440 dpi\r\n- Kecepatan cetak maksimum: Hitam 33 ppm dan warna 15 ppm (draft, A4/letter)\r\n- Kecepatan cetak ISO: Hitam 10 ppm dan warna 5 ppm (A4/letter) \r\n- Ukuran cetak maksimum: 215.9mm x 1200mm\r\n- Ukuran kertas yang didukung: Standar: A4, Letter, Legal (215,9 mm x 355,6 mm), Meksiko Legal (215,9 mm x 340,4 mm), Legal 9 (214,9 mm x 315 mm), folio (215,9 mm x330,2 mm), eksekutif, pernyataan, A6\r\n??? Foto: 10 cm x15 cm (4 inci x 6 inci), lebar 16: 9 (102 mm x 181 mm), 13 cm x 18 cm (5 inci x 7 inci)\r\n??? Jenis: Kertas biasa, kertas khusus Epson (kertas matte, glossy, semi glossy, kertas inkjet kualitas tinggi)\r\n??? Amplop: No. 10 (10,5 cm x 24,1 cm)\r\n??? Kapasitas muat: Umpan belakang: hingga 100 lembar (A4 / letter)', '???  Teknologi cetak: Heat-FreeTM MicroPiezo?? 4-Color Inkjet (CMYK)\r\n??? Resolusi cetak maksimum: Hingga 5.760 dpi x 1.440 dpi\r\n??? Kecepatan cetak maksimum: Hitam 33 ppm dan warna 15 ppm (draft, A4/letter)\r\n??? Kecepatan cetak ISO: Hitam 10 ppm dan warna 5 ppm (A4/letter) \r\n???  Ukuran cetak maksimum: 215.9mm x 1200mm\r\n??? Ukuran kertas yang didukung: Standar: A4, Letter, Legal (215,9 mm x 355,6 mm), Meksiko Legal (215,9 mm x 340,4 mm), Legal 9 (214,9 mm x 315 mm), folio (215,9 mm x330,2 mm), eksekutif, pernyataan, A6\r\n??? Foto: 10 cm x15 cm (4 inci x 6 inci), lebar 16: 9 (102 mm x 181 mm), 13 cm x 18 cm (5 inci x 7 inci)\r\n??? Jenis: Kertas biasa, kertas khusus Epson (kertas matte, glossy, semi glossy, kertas inkjet kualitas tinggi)\r\n??? Amplop: No. 10 (10,5 cm x 24,1 cm)\r\n??? Kapasitas muat: Umpan belakang: hingga 100 lembar (A4 / letter)', 2025000.00, NULL, 'unit', 10, 10, '68c27cdd95a51.jpg', 'aktif', '2025-09-11 07:39:08', '2025-09-12 08:10:03', NULL, 0),
(55, 'ASUS-VIVO-895', 'Asus Vivobook Go 14 E1404GA-FHD351M-Mixed Black', 1, 1, 'barang_it', '* Processor : Intel?? Core??? i3-N305 Processor 1.8 GHz (6MB Cache, up to 3.8 GHz, 8 cores, 8 Threads)\r\n* Display : 14.0-inch FHD (1920 x 1080) 16:9 aspect ratio LED Backlit 60Hz refresh rate 250nits 45% NTSC color gamut Anti-glare display 83 ???\r\n* Memory : 8GB DDR4 on board\r\n* Storage : 512GB M.2 NVMe??? PCIe?? 3.0 SSD\r\n* Graphics : Intel?? UHD Graphics\r\n* Keyboard & Touchpad : Backlit Chiclet Keyboard + FingerPrint (integrated with Touchpad)\r\n* Wireless : Wi-Fi 6E(802.11ax) (Dual band) 1*1 + Bluetooth?? 5.3 Wireless Card (*Bluetooth?? version may change with OS version different.)\r\n* Ports : 1x USB 2.0 Type-A; 1x USB 3.2 Gen 1 Type-A; 1x USB 3.2 Gen 1 Type-C; 1x HDMI 1.4; 1x 3.5mm Combo Audio Jack; 1x DC-in\r\n* Audio : SonicMaster, Built-in speaker, Built-in array microphone with Cortana voice-recognition suppor\r\n\r\n* Camera : 720p HD camera With privacy shutter\r\n* Battery : 42WHrs, 3S1P, 3-cell Li-ion, 45W AC Adapter\r\n* Free : Backpack\r\n* OS : Windows 11 Home + Office Home & Student 2021 + Microsoft 365 (1Y)', '* Processor : Intel?? Core??? i3-N305 Processor 1.8 GHz (6MB Cache, up to 3.8 GHz, 8 cores, 8 Threads)\r\n* Display : 14.0-inch FHD (1920 x 1080) 16:9 aspect ratio LED Backlit 60Hz refresh rate 250nits 45% NTSC color gamut Anti-glare display 83 ???\r\n* Memory : 8GB DDR4 on board\r\n* Storage : 512GB M.2 NVMe??? PCIe?? 3.0 SSD\r\n* Graphics : Intel?? UHD Graphics\r\n* Keyboard & Touchpad : Backlit Chiclet Keyboard + FingerPrint (integrated with Touchpad)\r\n* Wireless : Wi-Fi 6E(802.11ax) (Dual band) 1*1 + Bluetooth?? 5.3 Wireless Card (*Bluetooth?? version may change with OS version different.)\r\n* Ports : 1x USB 2.0 Type-A; 1x USB 3.2 Gen 1 Type-A; 1x USB 3.2 Gen 1 Type-C; 1x HDMI 1.4; 1x 3.5mm Combo Audio Jack; 1x DC-in\r\n* Audio : SonicMaster, Built-in speaker, Built-in array microphone with Cortana voice-recognition suppor\r\n\r\n* Camera : 720p HD camera With privacy shutter\r\n* Battery : 42WHrs, 3S1P, 3-cell Li-ion, 45W AC Adapter\r\n* Free : Backpack\r\n* OS : Windows 11 Home + Office Home & Student 2021 + Microsoft 365 (1Y)', 5800000.00, NULL, 'unit', 10, 10, '68c2820157611.jpg', 'aktif', '2025-09-11 07:44:17', '2025-09-11 08:02:09', NULL, 0),
(56, 'VGEN-AVATA-890', 'V-Gen Avatar 16 GB', NULL, 1, 'barang_it', 'Size :38 x 17 x 5 mm\r\nStatus :Regular\r\nVolume :+/- 5.0gr\r\nVoltage :2.7V 3.3V\r\nRead : +/- 29 MB/sec\r\nWrite : +/- 15 MB/sec', 'Size :38 x 17 x 5 mm\r\nStatus :Regular\r\nVolume :+/- 5.0gr\r\nVoltage :2.7V 3.3V\r\nRead : +/- 29 MB/sec\r\nWrite : +/- 15 MB/sec', 40000.00, NULL, 'unit', 10, 10, '68c28054f08c9.jpeg', 'aktif', '2025-09-11 07:48:55', '2025-09-11 07:55:00', NULL, 0),
(57, 'VGEN-MICRO-953', 'V-Gen Micro SD Turbo NA 32 GB', NULL, 1, 'barang_it', 'Dimensi Kartu: 15 mm (L) x 11 mm (W) x 1.0 mm (H)\r\nKelas Kecepatan: UHS 1 class10\r\nKecepatan Baca: Up to 100MB/detik\r\nKecepatan Tulis: Up to 48MB/detik\r\nTemperatur Operasional: -25??C to +85??C (Recommended)\r\nTemperatur Penyimpanan: -40??C to +85??C (Recommended)\r\nFitur: Full HD 1080 ( 1980??1080 pixels ), RoHS ( bebas bahan kimia berbahaya ) Waterproof, Shockproof, Weatherproof, X-Rayproof', 'Dimensi Kartu: 15 mm (L) x 11 mm (W) x 1.0 mm (H)\r\nKelas Kecepatan: UHS 1 class10\r\nKecepatan Baca: Up to 100MB/detik\r\nKecepatan Tulis: Up to 48MB/detik\r\nTemperatur Operasional: -25??C to +85??C (Recommended)\r\nTemperatur Penyimpanan: -40??C to +85??C (Recommended)\r\nFitur: Full HD 1080 ( 1980??1080 pixels ), RoHS ( bebas bahan kimia berbahaya ) Waterproof, Shockproof, Weatherproof, X-Rayproof', 47000.00, NULL, 'unit', 10, 10, '68c280dedc3d8.jpg', 'aktif', '2025-09-11 07:50:50', '2025-09-11 07:57:18', NULL, 0),
(58, 'VGEN-MICRO-382', 'V-Gen Micro SD Turbo NA 64 GB', NULL, 1, 'barang_it', 'Dimensi Kartu: 15 mm (L) x 11 mm (W) x 1.0 mm (H)\r\nKelas Kecepatan: UHS 1 class10\r\nKecepatan Baca: Up to 100MB/detik\r\nKecepatan Tulis: Up to 48MB/detik\r\nTemperatur Operasional: -25??C to +85??C (Recommended)\r\nTemperatur Penyimpanan: -40??C to +85??C (Recommended)\r\nFitur: Full HD 1080 ( 1980??1080 pixels ), RoHS ( bebas bahan kimia berbahaya ) Waterproof, Shockproof, Weatherproof, X-Rayproof', 'Dimensi Kartu: 15 mm (L) x 11 mm (W) x 1.0 mm (H)\r\nKelas Kecepatan: UHS 1 class10\r\nKecepatan Baca: Up to 100MB/detik\r\nKecepatan Tulis: Up to 48MB/detik\r\nTemperatur Operasional: -25??C to +85??C (Recommended)\r\nTemperatur Penyimpanan: -40??C to +85??C (Recommended)\r\nFitur: Full HD 1080 ( 1980??1080 pixels ), RoHS ( bebas bahan kimia berbahaya ) Waterproof, Shockproof, Weatherproof, X-Rayproof', 74000.00, NULL, 'unit', 10, 10, '68c280cf3c77a.jpg', 'aktif', '2025-09-11 07:52:06', '2025-09-11 07:57:03', NULL, 0),
(59, 'VGEN-RESCU-822', 'V-Gen Rescue SSD INT 2.5\" SATA3 128 GB', NULL, 1, 'barang_it', '- Supported : UDMA Mode 6\r\n- TRIM Support : Yes (Requires OS Support)\r\n- Garbage Collection : Yes\r\n- S.M.A.R.T : Yes\r\n- Write Cache : Yes\r\n- Host Protect Area : Yes\r\n- APM : Yes\r\n- NCQ : Yes\r\n- 48-Bit : Yes\r\n- Security : AES 256-Bit Full Disk Encryption (FDE)\r\n- TCG/Opal V2.0 , Encryption Drive (IEEE1667)\r\n- Volume : +/- 20 gr', '- Supported : UDMA Mode 6\r\n- TRIM Support : Yes (Requires OS Support)\r\n- Garbage Collection : Yes\r\n- S.M.A.R.T : Yes\r\n- Write Cache : Yes\r\n- Host Protect Area : Yes\r\n- APM : Yes\r\n- NCQ : Yes\r\n- 48-Bit : Yes\r\n- Security : AES 256-Bit Full Disk Encryption (FDE)\r\n- TCG/Opal V2.0 , Encryption Drive (IEEE1667)\r\n- Volume : +/- 20 gr', 158000.00, NULL, 'unit', 10, 10, '68c282997d74b.jpg', 'aktif', '2025-09-11 07:57:43', '2025-09-11 08:04:41', NULL, 0),
(60, 'EZVIZ-C6N--333', 'Ezviz C6N 2MP Smart Home', NULL, 1, 'barang_it', 'Model : CS-C6N-A0-1C2WFR\r\nImage Sensor : 1/4\" Progressive Scan CMOS\r\nShutter Speed : Self-adaptive shutter\r\nLens : 4mm@ F2.4, view angle: 85?? diagonal, 75?? horizontal, 45?? vertical\r\nLens Mount : M12\r\nDay & Night : IR-cut filter with auto-switching\r\nDNR : 3D DNR\r\nWDR : Digital WDR\r\nVideo Compression : H.264\r\nVideo Bit Rate : Adaptive bit rate.\r\nMax. Resolution : 1920 ?? 1080\r\nFrame Rate : Max. 15fps; Self-adaptive during network transmission\r\nSmart Alarm : Motion detection\r\nWi-Fi Pairing : AP Pairing\r\nProtocol : EZVIZ Cloud Proprietary Protocol\r\nInterface Protocol : EZVIZ Cloud Proprietary Protocol\r\nStorage : MicroSD card slot (Max. 256 GB)\r\nPower : Micro USB\r\n\r\nWired Network : RJ45 X 1 (10M/100M self-adaptive Ethernet port)\r\nStandard : IEEE802.11 b/g/n\r\nFrequency Range : 2.4 GHz ~ 2.4835 GHz\r\nChannel Bandwidth : Supports 20MHz\r\nSecurity : 64/128-bit WEP, WPA/WPA2, WPA-PSK/WPA2-PSK\r\nTransmission Rate : 11b: 11 Mbps, 11g: 54 Mbps, 11n: 72 Mbps\r\nPower Supply : DC 5V/1A\r\nPower Consumption : 5W max\r\nIR Range : MAX. 10m (32.81 fts)\r\nDimensions : 88 mm x 88.2 mm x 119 mm (3.46??? x 3.47??? x 4.69???)\r\nPackaging Dimensions : 103 mm x 103 mm x 168 mm (4.06??? x 4.06??? x 6.61???)\r\nPackaging Contents : C6N camera; Base; Screw Kit; Power Cable 3m (9.8 ft.); Drill Template; Power Adapter; Quick Start Guide\r\nMicro SD SANDISK ULTRA microSD UHS-I CARD FOR CAMERAS for 128GB / 256GB\r\nCapacity: 128GB / 256 GB\r\nRead Speed: up to 100MB/S1 for 128 GB / up to 120MB/S1 for 256 GB\r\nVideo Speed: C102\r\nForm Factor: microSDXC', 'Model : CS-C6N-A0-1C2WFR\r\nImage Sensor : 1/4\" Progressive Scan CMOS\r\nShutter Speed : Self-adaptive shutter\r\nLens : 4mm@ F2.4, view angle: 85?? diagonal, 75?? horizontal, 45?? vertical\r\nLens Mount : M12\r\nDay & Night : IR-cut filter with auto-switching\r\nDNR : 3D DNR\r\nWDR : Digital WDR\r\nVideo Compression : H.264\r\nVideo Bit Rate : Adaptive bit rate.\r\nMax. Resolution : 1920 ?? 1080\r\nFrame Rate : Max. 15fps; Self-adaptive during network transmission\r\nSmart Alarm : Motion detection\r\nWi-Fi Pairing : AP Pairing\r\nProtocol : EZVIZ Cloud Proprietary Protocol\r\nInterface Protocol : EZVIZ Cloud Proprietary Protocol\r\nStorage : MicroSD card slot (Max. 256 GB)\r\nPower : Micro USB\r\n\r\nWired Network : RJ45 X 1 (10M/100M self-adaptive Ethernet port)\r\nStandard : IEEE802.11 b/g/n\r\nFrequency Range : 2.4 GHz ~ 2.4835 GHz\r\nChannel Bandwidth : Supports 20MHz\r\nSecurity : 64/128-bit WEP, WPA/WPA2, WPA-PSK/WPA2-PSK\r\nTransmission Rate : 11b: 11 Mbps, 11g: 54 Mbps, 11n: 72 Mbps\r\nPower Supply : DC 5V/1A\r\nPower Consumption : 5W max\r\nIR Range : MAX. 10m (32.81 fts)\r\nDimensions : 88 mm x 88.2 mm x 119 mm (3.46??? x 3.47??? x 4.69???)\r\nPackaging Dimensions : 103 mm x 103 mm x 168 mm (4.06??? x 4.06??? x 6.61???)\r\nPackaging Contents : C6N camera; Base; Screw Kit; Power Cable 3m (9.8 ft.); Drill Template; Power Adapter; Quick Start Guide\r\nMicro SD SANDISK ULTRA microSD UHS-I CARD FOR CAMERAS for 128GB / 256GB\r\nCapacity: 128GB / 256 GB\r\nRead Speed: up to 100MB/S1 for 128 GB / up to 120MB/S1 for 256 GB\r\nVideo Speed: C102\r\nForm Factor: microSDXC', 290000.00, NULL, 'unit', 10, 10, '68c2822f1f7e3.jpeg', 'aktif', '2025-09-11 08:01:23', '2025-09-11 08:02:55', NULL, 0),
(61, 'EZVIZ-H8C--445', 'Ezviz H8C 2MP Smart Home Outdoor IP Camera WI-FI', NULL, 1, 'barang_it', 'IP Camera CCTV Wifi EZVIZ H8C 1080P Pan And Tilt (Support RJ45/LAN) Specifications Model Model CS-H8c (1080P) Camera Image Sensor 1/2.8 #34; Progressive Scan CMOS Shutter Speed Self-adaptive shutter Lens 4mm @ F2.0, viewing angle: 46?? (Vertical), 89?? (Horizontal), 104?? (Diagonal) 6mm @ F2.0, viewing angle: 28?? (Vertical), 52?? (Horizontal), 60?? (Diagonal) PT Angle Pan: 350??, Tilt: 80?? Minimum Illumination 0.5 Lux @ (F1.6, AGC ON), 0 Lux with IR (Data is obtained from EZVIZ laboratories) Lens Mount M12 Day amp; Night IR-Cut filter with auto-switching DNR 3D DNR WDR Digital WDR Night Vision Distance 30 m / 98 ft Video amp; Audio Max. Resolution 1920 ?? 1080 Frame Rate Max: 30fps; Self-Adaptive during network transmission Video Compression H.265 / H.264 H.265 Type Main Profile Video Bit Rate Full HD; Hi-Def; Standard. Adaptive bit rate. Audio Bit Rate Self-Adaptive Max. Bitrate 2 Mbps Network Wi-Fi Standard IEEE802.11b, 802.11g, 802.11n Frequency Range 2.4 GHz ~ 2.4835 GHz Channel Bandwidth Supports 20 MHz Transmission Rate 11b: 11 Mbps,11g: 54 Mbps,11n: 72 Mbps Wi-Fi Pairing AP pairing Protocol EZVIZ Cloud Proprietary Protocol Interface Protocol EZVIZ Cloud Proprietary Protocol Wired Network RJ45 ?? 1 (10M / 100M Adaptive Ethernet Port) Storage Local Storage MicroSD card slot (Up to 512GB) Cloud Storage EZVIZ Cloud Storage (Subscription Required) Function Smart Alarm AI-Powered Human Shape Detection Customized Alert Area Supports Two-way Talk Supports General Function Anti-Flicker, Dual-Stream, Heart Beat, Password Protection, Watermark General Operating Conditions -30 ??C to 50??C ( -22 ??F to 122 ??F ), humidity 95% or less (non-condensing) IP Grade Weatherproof Design Power Supply DC 12V / 1A Power Consumption MAX. 12W Dimensions 100.05 ?? 129.19 ?? 149.75 mm (3.94 ?? 5.09 ?? 5.9 inch) Packaging Dimensions 140 ?? 140 ?? 192 mm (5.51 ?? 5.51 ?? 7.56 inch) Weight (With package) 730 g (25.75 oz) Net Weight 420 g (14.8 oz) In the box - H8c Camera - Drill Template - Screw Kit - Waterproof Kit - Power Adapter - Regulatory Information - Quick Start Guide Certifications CE / FCC / UKCA / UL / WEEE / RoHS / REACH', 'IP Camera CCTV Wifi EZVIZ H8C 1080P Pan And Tilt (Support RJ45/LAN) Specifications Model Model CS-H8c (1080P) Camera Image Sensor 1/2.8 #34; Progressive Scan CMOS Shutter Speed Self-adaptive shutter Lens 4mm @ F2.0, viewing angle: 46?? (Vertical), 89?? (Horizontal), 104?? (Diagonal) 6mm @ F2.0, viewing angle: 28?? (Vertical), 52?? (Horizontal), 60?? (Diagonal) PT Angle Pan: 350??, Tilt: 80?? Minimum Illumination 0.5 Lux @ (F1.6, AGC ON), 0 Lux with IR (Data is obtained from EZVIZ laboratories) Lens Mount M12 Day amp; Night IR-Cut filter with auto-switching DNR 3D DNR WDR Digital WDR Night Vision Distance 30 m / 98 ft Video amp; Audio Max. Resolution 1920 ?? 1080 Frame Rate Max: 30fps; Self-Adaptive during network transmission Video Compression H.265 / H.264 H.265 Type Main Profile Video Bit Rate Full HD; Hi-Def; Standard. Adaptive bit rate. Audio Bit Rate Self-Adaptive Max. Bitrate 2 Mbps Network Wi-Fi Standard IEEE802.11b, 802.11g, 802.11n Frequency Range 2.4 GHz ~ 2.4835 GHz Channel Bandwidth Supports 20 MHz Transmission Rate 11b: 11 Mbps,11g: 54 Mbps,11n: 72 Mbps Wi-Fi Pairing AP pairing Protocol EZVIZ Cloud Proprietary Protocol Interface Protocol EZVIZ Cloud Proprietary Protocol Wired Network RJ45 ?? 1 (10M / 100M Adaptive Ethernet Port) Storage Local Storage MicroSD card slot (Up to 512GB) Cloud Storage EZVIZ Cloud Storage (Subscription Required) Function Smart Alarm AI-Powered Human Shape Detection Customized Alert Area Supports Two-way Talk Supports General Function Anti-Flicker, Dual-Stream, Heart Beat, Password Protection, Watermark General Operating Conditions -30 ??C to 50??C ( -22 ??F to 122 ??F ), humidity 95% or less (non-condensing) IP Grade Weatherproof Design Power Supply DC 12V / 1A Power Consumption MAX. 12W Dimensions 100.05 ?? 129.19 ?? 149.75 mm (3.94 ?? 5.09 ?? 5.9 inch) Packaging Dimensions 140 ?? 140 ?? 192 mm (5.51 ?? 5.51 ?? 7.56 inch) Weight (With package) 730 g (25.75 oz) Net Weight 420 g (14.8 oz) In the box - H8c Camera - Drill Template - Screw Kit - Waterproof Kit - Power Adapter - Regulatory Information - Quick Start Guide Certifications CE / FCC / UKCA / UL / WEEE / RoHS / REACH', 535000.00, NULL, 'unit', 10, 10, '68c28332ed957.jpg', 'aktif', '2025-09-11 08:04:13', '2025-09-11 08:07:14', NULL, 0),
(62, 'AX-166', 'AXIOO MYBOOK PRO K5 NOTEBOOK', 1, 1, 'barang_it', 'Screen Size : 14 Inch FHD IPS Display Screen\r\nCPU : Inter Core i5-1135G7\r\nRAM : 8 GB DDRStorage : 512GB \r\nSSD NVMEOS : Windows 10 Pro\r\nGaransi : 1 tahun\r\nIntegrated Intel GraphicsHD \r\nCamera\r\nUSB PortsRJ45 LAN\r\nWiFi and BluetoothHDMI Port', 'TKDN+BMP : 50,70%Screen Size : 14 Inch FHD IPS Display ScreenCPU : Inter Core i5-1135G7RAM : 8 GB DDRStorage : 512GB SSD NVMEOS : Windows 10 ProGaransi : 1 tahunIntegrated Intel GraphicsHD CameraUSB PortsRJ45 LANWiFi and BluetoothHDMI Port', 9850000.00, NULL, 'unit', 10, 10, '68c2869f83171.jpeg', 'aktif', '2025-09-11 08:06:07', '2025-09-13 03:31:23', NULL, 0);
INSERT INTO `produk` (`id`, `kode_produk`, `nama_produk`, `kategori_id`, `vendor_id`, `jenis`, `deskripsi`, `spesifikasi`, `harga_satuan`, `harga_grosir`, `satuan`, `stok_minimal`, `stok_tersedia`, `gambar`, `status`, `created_at`, `updated_at`, `harga_diskon`, `is_featured`) VALUES
(63, 'ACER-ASPIR-762', 'ACER Aspire Lite AL14 31P C6DD', 1, 1, 'barang_it', '- Processor : Intel N100 Processor (4 Cores, 3.4 GHz)\r\n- OS : Windows 11 Home\r\n- Memory : 8 GB DDR5 Memory (Upgradeable up to 16 GB)\r\n- Storage : 512 GB SSD NVMe\r\n- Inch, Res, Ratio, Panel : 14\" WUXGA 1920 x 1200, IPS, Acer ComfyView\r\n??? Graphics : Intel UHD Graphics\r\n??? Features : 180 Degree Open Design\r\n??? OS : Windows 11 Home + Office Home Student 2021', '??? Processor : Intel?? N100 Processor (4 Cores, 3.4 GHz)\r\n??? OS : Windows 11 Home\r\n??? Memory : 8 GB DDR5 Memory (Upgradeable up to 16 GB)\r\n??? Storage : 512 GB SSD NVMe\r\n??? Inch, Res, Ratio, Panel : 14\" WUXGA 1920 x 1200, IPS, Acer ComfyView\r\n??? Graphics : Intel UHD Graphics\r\n??? Features : 180 Degree Open Design\r\n??? OS : Windows 11 Home + Office Home Student 2021', 4900000.00, NULL, 'unit', 10, 10, '68c2865b39e94.png', 'aktif', '2025-09-11 08:09:40', '2025-09-13 03:23:10', NULL, 0),
(65, 'BROTHER-S-718', 'Brother ADS-1300 Scanner', NULL, 1, 'barang_it', 'Scanning Speed: 30ppm/300dpi, 7ppm/600dpi\r\nDocument Size - Multiple Sheets - Length: 70 mm to 355.6 mm\r\nDocument Size - Multiple Sheets - Width: 50.8 mm to 215.9 mm\r\nColour / Monochrome: Yes / Yes\r\n2-Sided (Duplex) Scan: Yes\r\nColour Depth - Input: 48-bit colour processing\r\nColour Depth - Output:24-bit colour processing\r\nResolution - Optical: Up to 600 dpi ?? 600 dpi\r\nResolution - Interpolated: Up to 1,200 dpi ?? 1,200 dpi\r\nDocument Scanning Width / Length: Up to 215.9 mm\r\nADF (Automatic Document Feeder): Up to 20 sheets\r\nMemory Capacity: SDRAM: 256MB', 'Scanning Speed: 30ppm/300dpi, 7ppm/600dpi\r\nDocument Size - Multiple Sheets - Length: 70 mm to 355.6 mm\r\nDocument Size - Multiple Sheets - Width: 50.8 mm to 215.9 mm\r\nColour / Monochrome: Yes / Yes\r\n2-Sided (Duplex) Scan: Yes\r\nColour Depth - Input: 48-bit colour processing\r\nColour Depth - Output:24-bit colour processing\r\nResolution - Optical: Up to 600 dpi ?? 600 dpi\r\nResolution - Interpolated: Up to 1,200 dpi ?? 1,200 dpi\r\nDocument Scanning Width / Length: Up to 215.9 mm\r\nADF (Automatic Document Feeder): Up to 20 sheets\r\nMemory Capacity: SDRAM: 256MB', 4225000.00, NULL, 'unit', 10, 10, '68c288e7f26a5.jpg', 'aktif', '2025-09-11 08:27:35', '2025-09-11 08:31:53', NULL, 0),
(66, 'VENOMRX-RH-920', 'VenomRX Rhombus Fixed RGB Black', NULL, 1, 'barang_it', 'Model Fixed RGB\r\nColor Black\r\nSize 120x120x25 mm\r\nVoltage DC 12V\r\nCurrent 0.15 Ampere\r\nSpeed 1200?? 10% RPM\r\nAirflow 41.8 CFM\r\nAir Pressure 1.56 mm H-20\r\nBearing Type Hydro Bearing\r\nNoise 26.4dB\r\nLifespan 30.000 Hours\r\nConnector 4-pin Molex', 'Model Fixed RGB\r\nColor Black\r\nSize 120x120x25 mm\r\nVoltage DC 12V\r\nCurrent 0.15 Ampere\r\nSpeed 1200?? 10% RPM\r\nAirflow 41.8 CFM\r\nAir Pressure 1.56 mm H-20\r\nBearing Type Hydro Bearing\r\nNoise 26.4dB\r\nLifespan 30.000 Hours\r\nConnector 4-pin Molex', 29000.00, NULL, 'unit', 10, 10, '68c28897e314e.jpg', 'aktif', '2025-09-11 08:29:33', '2025-09-11 08:30:15', NULL, 0),
(67, 'NETLINK-HT-048', 'Netlink HTB-3100 10/100 Media Converter', NULL, 1, 'barang_it', 'Operating standars : IEEE802.3u, 10/100Base-TX and 100Base-FX\r\nMAC address table : 1K\r\nConnector :\r\nUTP: RJ-45 10/100Mbps\r\nFiber : SC 100Mbps\r\nCable :\r\nUTP: Cat. 5 UTP/SFTP/FTP & Cat.6 UTP/SFTP/FTP (the max distance up to 100m)\r\nFiber (Singlemode) : 8.3/125, 8.7/125, 9/125, 10/125um (the max distance up to 90km)\r\nFlow Control\r\nFull Duplex: IEEE802.3x\r\nHalf Duplex: Backpressure\r\nLED: Power, FX 100, FX Link/Act, TX 100, TX FDX, TX Link/Act\r\nPower: AC 110V - 220V to DC 5V\r\nAmbient temperature : 0-50\r\nStorage temperature : -20 +70', 'Operating standars : IEEE802.3u, 10/100Base-TX and 100Base-FX\r\nMAC address table : 1K\r\nConnector :\r\nUTP: RJ-45 10/100Mbps\r\nFiber : SC 100Mbps\r\nCable :\r\nUTP: Cat. 5 UTP/SFTP/FTP & Cat.6 UTP/SFTP/FTP (the max distance up to 100m)\r\nFiber (Singlemode) : 8.3/125, 8.7/125, 9/125, 10/125um (the max distance up to 90km)\r\nFlow Control\r\nFull Duplex: IEEE802.3x\r\nHalf Duplex: Backpressure\r\nLED: Power, FX 100, FX Link/Act, TX 100, TX FDX, TX Link/Act\r\nPower: AC 110V - 220V to DC 5V\r\nAmbient temperature : 0-50\r\nStorage temperature : -20 +70', 79000.00, NULL, 'unit', 10, 10, '68c28b04e6e6f.jpg', 'aktif', '2025-09-11 08:32:59', '2025-09-11 08:40:36', NULL, 0),
(68, 'VENTUZ-KAB-512', 'Ventuz Kabel VGA To VGA (Gold Plate) 3M', NULL, 1, 'barang_it', '3+9 Double color\r\n28#BC copper OD7.8-8.0\r\ngold-plated+ 100%\r\nlength 1.5m-30m\r\ndouble true ring + weave 25m-50m\r\ndouble true ring 1080P', '3+9 Double color\r\n28#BC copper OD7.8-8.0\r\ngold-plated+ 100%\r\nlength 1.5m-30m\r\ndouble true ring + weave 25m-50m\r\ndouble true ring 1080P', 48000.00, NULL, 'unit', 10, 10, '68c28a2b11bf1.jpeg', 'aktif', '2025-09-11 08:34:18', '2025-09-11 08:36:59', NULL, 0),
(69, 'ORICO-HDD-740', 'ORICO HDD SSD Enclosure 2.5 Inch USB 3.0 - 2020U3-V1', NULL, 1, 'barang_it', '1. Product Model : ORICO-2020U3-V1\r\n2. Material : ABS\r\n3. Color : Black\r\n4. Dimension : 132.9mm(L)*80mm(W)*17mm(H)\r\n5. Transmission Interface : USB3.0 Micro-B\r\n6. Transmission Speed : 5Gbps\r\n7. Support Capacity : Up to 6TB\r\n8. Hard Disk Support Thickness : 7-9.5MM\r\n9. Support System : Windows / Mac OS / Linux\r\n10. Packaging accessories : Micro-B to USB-A 0.5M data cable*1, manual*1, shockproof sponge*1', '1. Product Model : ORICO-2020U3-V1\r\n2. Material : ABS\r\n3. Color : Black\r\n4. Dimension : 132.9mm(L)*80mm(W)*17mm(H)\r\n5. Transmission Interface : USB3.0 Micro-B\r\n6. Transmission Speed : 5Gbps\r\n7. Support Capacity : Up to 6TB\r\n8. Hard Disk Support Thickness : 7-9.5MM\r\n9. Support System : Windows / Mac OS / Linux\r\n10. Packaging accessories : Micro-B to USB-A 0.5M data cable*1, manual*1, shockproof sponge*1', 62000.00, NULL, 'unit', 10, 10, '68c28c495bef6.jpg', 'aktif', '2025-09-11 08:44:03', '2025-09-11 08:46:01', NULL, 0),
(70, 'DJI-MINI-4-807', 'DJI Mini 4 Pro (DJI RC 2)', NULL, 1, 'barang_it', 'Sensor 1/1.3-inch CMOS\r\n4K/60fps HDR True Vertical Shooting\r\nOmnidirectional Obstacle Sensing untuk pelacakan yang stabil\r\n20km FHD Video Transmission\r\nFitur ActiveTrack 360??\r\nDilengkapi Advanced Pilot Assistance Systems (APAS) untuk keamanan tambahan', 'Sensor 1/1.3-inch CMOS\r\n4K/60fps HDR True Vertical Shooting\r\nOmnidirectional Obstacle Sensing untuk pelacakan yang stabil\r\n20km FHD Video Transmission\r\nFitur ActiveTrack 360??\r\nDilengkapi Advanced Pilot Assistance Systems (APAS) untuk keamanan tambahan', 11544000.00, NULL, 'unit', 10, 10, '68c28cececc97.jpeg', 'aktif', '2025-09-11 08:47:17', '2025-09-11 08:48:44', NULL, 0),
(71, 'PROCESSOR--613', 'Processor Intel Core i5-12400F (BOX)', NULL, 1, 'barang_it', 'Max Turbo Frequency : 4.40 GHz\r\nPerformance-core Max Turbo Frequency : 4.40 GHz\r\nPerformance-core Base Frequency : 2.50 GHz\r\nCache : 18 MB Intel???? Smart Cache\r\nTotal L2 Cache : 7.5 MB\r\nProcessor Base Power : 65 W\r\nMaximum Turbo Power : 117 W\r\nMax Memory Size (dependent on memory type) : 128 GB\r\nSockets Supported: FCLGA1700', 'Max Turbo Frequency : 4.40 GHz\r\nPerformance-core Max Turbo Frequency : 4.40 GHz\r\nPerformance-core Base Frequency : 2.50 GHz\r\nCache : 18 MB Intel???? Smart Cache\r\nTotal L2 Cache : 7.5 MB\r\nProcessor Base Power : 65 W\r\nMaximum Turbo Power : 117 W\r\nMax Memory Size (dependent on memory type) : 128 GB\r\nSockets Supported: FCLGA1700', 1800000.00, NULL, 'unit', 10, 10, '68c28df7a0b6c.jpg', 'aktif', '2025-09-11 08:50:41', '2025-09-11 08:53:11', NULL, 0),
(72, 'ASUS-PRIME-649', 'ASUS PRIME H610M-K D4', NULL, 1, 'barang_it', 'Intel H610 (LGA 1700) mic-ATX motherboard with DDR4, PCIe 4.0, M.2 slot, Realtek 1 Gb Ethernet, HDMI, D-Sub, USB 3.2 Gen 1 ports, SATA 6 Gbps, COM header, RGB header\r\n??? Comprehensive cooling: PCH heatsink and Fan Xpert\r\n??? Ultrafast connectivity: 32Gbps M.2 slot, Realtek 1 Gb Ethernet and USB 3.2 Gen 1 support\r\n??? 5X Protection III: Multiple hardware safeguards for all-round protection\r\nCPU\r\nIntel Socket LGA1700 for 12th Gen Intel Core, Pentium Gold and Celeron Processors*\r\nSupports Intel Turbo Boost Technology 2.0 and Intel Turbo Boost Max Technology 3.0**\r\n** Intel Turbo Boost Max Technology 3.0 support depends on the CPU types.\r\nMemory\r\n2 x DIMM, Max. 64GB, DDR4 3200/3000/2933/2800/2666/2400/2133 Non-ECC, Un-buffered Memory*\r\nDual Channel Memory Architecture\r\nSupports Intel Extreme Memory Profile (XMP)\r\n* Actual memory data rate depends on the CPU types and DRAM modules, for more information\r\nGraphics\r\n1 x D-Sub port\r\n1 x HDMI port**\r\n* Graphics specifications may vary between CPU types.\r\n** Supports 4K@60Hz as specified in HDMI 2.1.\r\nForm Factor\r\nmATX Form Factor', 'Intel H610 (LGA 1700) mic-ATX motherboard with DDR4, PCIe 4.0, M.2 slot, Realtek 1 Gb Ethernet, HDMI, D-Sub, USB 3.2 Gen 1 ports, SATA 6 Gbps, COM header, RGB header\r\n??? Comprehensive cooling: PCH heatsink and Fan Xpert\r\n??? Ultrafast connectivity: 32Gbps M.2 slot, Realtek 1 Gb Ethernet and USB 3.2 Gen 1 support\r\n??? 5X Protection III: Multiple hardware safeguards for all-round protection\r\nCPU\r\nIntel Socket LGA1700 for 12th Gen Intel Core, Pentium Gold and Celeron Processors*\r\nSupports Intel Turbo Boost Technology 2.0 and Intel Turbo Boost Max Technology 3.0**\r\n** Intel Turbo Boost Max Technology 3.0 support depends on the CPU types.\r\nMemory\r\n2 x DIMM, Max. 64GB, DDR4 3200/3000/2933/2800/2666/2400/2133 Non-ECC, Un-buffered Memory*\r\nDual Channel Memory Architecture\r\nSupports Intel Extreme Memory Profile (XMP)\r\n* Actual memory data rate depends on the CPU types and DRAM modules, for more information\r\nGraphics\r\n1 x D-Sub port\r\n1 x HDMI port**\r\n* Graphics specifications may vary between CPU types.\r\n** Supports 4K@60Hz as specified in HDMI 2.1.\r\nForm Factor\r\nmATX Form Factor', 1200000.00, NULL, 'unit', 10, 10, '68c28ff90fc08.jpg', 'aktif', '2025-09-11 08:59:58', '2025-09-11 09:02:11', NULL, 0),
(73, 'MSI-VGA-CA-608', 'MSI VGA Card GeForce GTX 1650 D6 VENTUS XS OCV1 4GB GDDR6', NULL, 1, 'barang_it', 'NVIDIA GeForce GTX 1650 Prosesor 896 unit kore dan frekuensi kore\r\nBoost 1620 MHz. Memori GDDR6 4GB beroperasi pada kecepatan 12 Gbps. Interface\r\nPCI Express x16 3.0 memastikan koneksi yang stabil. Dengan output DL-DVI-D,\r\nHDMI 2.0b, dan DisplayPort v1.4, kartu ini mendukung berbagai layar.', 'Model GeForce GTX 1650 D6 VENTUS XS OCV1 menawarkan grafis NVIDIA GeForce GTX\r\n1650. Prosesor grafis ini dilengkapi dengan 896 unit kore dan frekuensi kore\r\nBoost 1620 MHz. Memori GDDR6 4GB beroperasi pada kecepatan 12 Gbps. Interface\r\nPCI Express x16 3.0 memastikan koneksi yang stabil. Dengan output DL-DVI-D,\r\nHDMI 2.0b, dan DisplayPort v1.4, kartu ini mendukung berbagai layar.', 2450000.00, NULL, 'unit', 10, 0, '68c29159a2685.png', 'aktif', '2025-09-11 09:05:29', '2025-09-11 09:09:34', NULL, 0),
(74, 'VGEN-DDR4--460', 'V-Gen DDR4 PLATINUM 8GB PC19200/2400Mhz', NULL, 1, 'barang_it', 'V-GeN PLATINUM 8GB PC19200/2400Mhz Long Dimm (Memory PC VGEN) Kapasitas tersedia : 4GB, 8GB, dan 16GB Dimensi : 130mm x 30mm x 2mm Kecepatan : 2400Mhz Chipset : Major Brand (Samsung/SKHynix/Micron) Slot : DIMM 288 Pin Type : Unbuffered Voltage : 1.2V ECC', 'RAM DDR4 V-GeN PLATINUM 8GB PC19200/2400Mhz Long Dimm (Memory PC VGEN) Kapasitas tersedia : 4GB, 8GB, dan 16GB Dimensi : 130mm x 30mm x 2mm Kecepatan : 2400Mhz Chipset : Major Brand (Samsung/SKHynix/Micron) Slot : DIMM 288 Pin Type : Unbuffered Voltage : 1.2V ECC', 286500.00, NULL, 'unit', 10, 10, '68c2931bb3bb2.jpeg', 'aktif', '2025-09-11 09:08:31', '2025-09-12 05:05:37', NULL, 0),
(75, 'VGEN-SSD-M-399', 'V-Gen SSD M2 NVME 512 GB Hyper', NULL, 1, 'barang_it', 'Capacity : 512GB Dimensi : 80.15 x 22.15 x 2.38 (mm) Read up to 3500 MB/s Write up to 2500 MB/s Interface : NVM express 1.3 , PCIe Gen 3.0 x4 Form Factor : M.2 (2280) Operating Temperature : 0??C ??? 70??C Volume : +/- 20 gr', 'Capacity : 512GB Dimensi : 80.15 x 22.15 x 2.38 (mm) Read up to 3500 MB/s Write up to 2500 MB/s Interface : NVM express 1.3 , PCIe Gen 3.0 x4 Form Factor : M.2 (2280) Operating Temperature : 0??C ??? 70??C Volume : +/- 20 gr', 585000.00, NULL, 'unit', 10, 10, '68c292ba31918.jpg', 'aktif', '2025-09-11 09:10:10', '2025-09-11 09:13:30', NULL, 0),
(76, 'WDC-BLUE-I-487', 'WD Blue INT SATA 3.5\" 1 TB', NULL, 1, 'barang_it', '- Capacity: 1TB, 2TB\r\n- RPM Class: 7200\r\n- Interface: SATA 6 Gb/s\r\n- Form Factor: 3.5 Inch', '- Capacity: 1TB, 2TB\r\n- RPM Class: 7200\r\n- Interface: SATA 6 Gb/s\r\n- Form Factor: 3.5 Inch', 835000.00, NULL, 'unit', 10, 10, '68c293554d03e.png', 'aktif', '2025-09-11 09:12:21', '2025-09-12 07:37:50', NULL, 0),
(77, 'DEEPCOOL-P-423', 'Deepcool Power Supply PF500 (Flat Cable) 500W 80PLUS', NULL, 1, 'barang_it', 'Type ATX12V V2.4\r\nProduct Dimensions 150??140??86mm (W x L x H)\r\n80PLUS Certifications 230V EU White\r\nFan Size 120mm\r\nFan Bearing Hypro Bearing\r\nTopology Active PFC+Double tube forward\r\nCapacitors Taiwan bulk capacitor\r\nPower Good Signal 100-500ms\r\nHold Up Time ???16ms(75% Load)\r\nEfficiency ???85% Under Typical Load(50% Loading)\r\nProtection OPP/OVP/SCP\r\nOperation Temperature 0-40??C\r\nRegulatory CB/CCC/CE/UKCA/EAC/RCM/BIS\r\nErp Regulation ErP 2010\r\nMTBF 100,000 Hours', 'Type ATX12V V2.4\r\nProduct Dimensions 150??140??86mm (W x L x H)\r\n80PLUS Certifications 230V EU White\r\nFan Size 120mm\r\nFan Bearing Hypro Bearing\r\nTopology Active PFC+Double tube forward\r\nCapacitors Taiwan bulk capacitor\r\nPower Good Signal 100-500ms\r\nHold Up Time ???16ms(75% Load)\r\nEfficiency ???85% Under Typical Load(50% Loading)\r\nProtection OPP/OVP/SCP\r\nOperation Temperature 0-40??C\r\nRegulatory CB/CCC/CE/UKCA/EAC/RCM/BIS\r\nErp Regulation ErP 2010\r\nMTBF 100,000 Hours', 515000.00, NULL, 'unit', 10, 10, '68c29433d8797.jpg', 'aktif', '2025-09-11 09:14:36', '2025-09-11 09:19:47', NULL, 0),
(78, 'LG-LED-22--106', 'LG Led 22\" 22MR410-B HDMI+VGA', NULL, 1, 'barang_it', '- 21.45??? Full HD display\r\n- 100Hz Refresh Rate\r\n- Reader Mode\r\n- OnScreen Control\r\n- AMD FreeSync / Black Stabiliser', '- 21.45??? Full HD display\r\n- 100Hz Refresh Rate\r\n- Reader Mode\r\n- OnScreen Control\r\n- AMD FreeSync / Black Stabiliser', 1070000.00, NULL, 'unit', 10, 10, '68c29468e5397.jpg', 'aktif', '2025-09-11 09:17:02', '2025-09-12 07:39:41', NULL, 0),
(79, 'DAHUA-COO-104', 'DAHUA DH-XVR1B04-I 4CH 2MP H265+', NULL, 1, 'barang_it', 'Merk: Dahua\r\nModel: DH-XVR1B04-I\r\nCamera Input: 4 Channel\r\nResolution: 2MP 1920x1080p\r\nVideo Compression: H.265 Pro+/H.265 Pro/H.265/H.264+/H.264\r\n5 signals input adaptively (HDTVI/AHD/CVI/CVBS/IP)\r\nHDD Capacity: 1x HDD up to 6TB (Belum termasuk HDD)\r\nAplikasi HP: Digital Mobile Surveillance System (DMSS)\r\nHDMI Output: Yes 1x\r\nVGA Output: Yes 1x\r\nRCA Output: N/A\r\nLAN RJ-45 Input: Yes 1x\r\nUSB Input: Yes 2x\r\nAudio Input & Output: Yes 1x /1x\r\nPower Supply: DC 12V 1.5A\r\nBerat (tanpa HDD): ??? 1.1 kg\r\nDimensi (P x L x T): 197 ?? 192 ?? 41 mm', 'Merk: Dahua\r\nModel: DH-XVR1B04-I\r\nCamera Input: 4 Channel\r\nResolution: 2MP 1920x1080p\r\nVideo Compression: H.265 Pro+/H.265 Pro/H.265/H.264+/H.264\r\n5 signals input adaptively (HDTVI/AHD/CVI/CVBS/IP)\r\nHDD Capacity: 1x HDD up to 6TB (Belum termasuk HDD)\r\nAplikasi HP: Digital Mobile Surveillance System (DMSS)\r\nHDMI Output: Yes 1x\r\nVGA Output: Yes 1x\r\nRCA Output: N/A\r\nLAN RJ-45 Input: Yes 1x\r\nUSB Input: Yes 2x\r\nAudio Input & Output: Yes 1x /1x\r\nPower Supply: DC 12V 1.5A\r\nBerat (tanpa HDD): ??? 1.1 kg\r\nDimensi (P x L x T): 197 ?? 192 ?? 41 mm', 510000.00, NULL, 'unit', 10, 10, '68c296c66bdf0.jpeg', 'aktif', '2025-09-11 09:21:40', '2025-09-11 09:30:46', NULL, 0),
(80, 'ADAPTOR-12-794', 'Adaptor 12V/10A Jaring', NULL, 1, 'barang_it', '- Cocok di pakai untuk aplikasi CCTV, Radio, Charger, LED Strip, Computer project.\r\n- Ada cooling fan.\r\n- High Efficiency, Low Temperature.\r\n- Safety design with shortage protection, overload protection and auto voltage switching.\r\n- DC Voltage: 12V.\r\n- Current Range: 10A 20A 30A\r\n- Rated Power: 120W 240W 360W', '- Cocok di pakai untuk aplikasi CCTV, Radio, Charger, LED Strip, Computer project.\r\n- Ada cooling fan.\r\n- High Efficiency, Low Temperature.\r\n- Safety design with shortage protection, overload protection and auto voltage switching.\r\n- DC Voltage: 12V.\r\n- Current Range: 10A 20A 30A\r\n- Rated Power: 120W 240W 360W', 85000.00, NULL, 'unit', 10, 10, '68c296e532c67.jpg', 'aktif', '2025-09-11 09:24:28', '2025-09-11 09:31:17', NULL, 0),
(81, 'L-046', 'Logitech Mouse MK120', NULL, 1, 'barang_it', '-Full size Mouse\r\n-High Definition Optical tracking\r\n-Plug and Play\r\n-Usb Conection', '-Full size Mouse\r\n-High Definition Optical tracking\r\n-Plug and Play\r\n-Usb Conection', 158000.00, NULL, 'unit', 0, 0, '68c3c26a6a0f8.jpg', 'aktif', '2025-09-12 04:27:01', '2025-09-12 08:32:05', NULL, 0),
(83, 'EZVIZ-WALL-729', 'Ezviz Wallmount Bracket', NULL, 4, 'barang_it', '-Bisa digunakan untuk Kamera EZVIZ C6N ,C6CN ,C6TC ,C4W, C6WI , C6C\r\n-Mampu menahan beban camera hingga 5Kg\r\n-Cocok diletakan Diluar maupun di dalam ruangan.\r\n-Mudah dipasang,Simpel dan Praktis.\r\nModel : CS-CMT-Bracket-Wall Mount\r\nDimensions : 153,3 × 113,4 × 55 mm (6,04 × 4,46 × 2,17 inci)\r\nPackaging Dimensions : 156 × 115 × 58 mm (6,14 × 4,53 × 2,28 inci)', '-Bisa digunakan untuk Kamera EZVIZ C6N ,C6CN ,C6TC ,C4W, C6WI , C6C\r\n-Mampu menahan beban camera hingga 5Kg\r\n-Cocok diletakan Diluar maupun di dalam ruangan.\r\n-Mudah dipasang,Simpel dan Praktis.\r\nModel : CS-CMT-Bracket-Wall Mount\r\nDimensions : 153,3 × 113,4 × 55 mm (6,04 × 4,46 × 2,17 inci)\r\nPackaging Dimensions : 156 × 115 × 58 mm (6,14 × 4,53 × 2,28 inci)', 50000.00, NULL, 'unit', 0, 0, '68c3d4b1b75a8.jpg', 'aktif', '2025-09-12 05:31:43', '2025-09-12 08:07:13', NULL, 0),
(84, 'W-761', 'WD BLUE INT SATA 3.5\" 500GB', NULL, 3, 'barang_it', 'HARDISK 500GB WD BLUE HDD INTERNAL FOR PC COMPUTER KAPASITAS : 500GB PERFORMANCE 100% SENTINEL 100% POWER ON TIME :0 DAYS SPEED RPM 7200 MEREK WD BLUE UKURAN 3,5 INCHI SUPORT FOR PC CPU COMPUTER', 'HARDISK 500GB WD BLUE HDD INTERNAL FOR PC COMPUTER KAPASITAS : 500GB PERFORMANCE 100% SENTINEL 100% POWER ON TIME :0 DAYS SPEED RPM 7200 MEREK WD BLUE UKURAN 3,5 INCHI SUPORT FOR PC CPU COMPUTER', 225000.00, NULL, 'unit', 0, 0, '68c3c30355c62.jpg', 'aktif', '2025-09-12 06:10:58', '2025-09-13 03:18:21', NULL, 0),
(85, 'CAPACITY-2-092', 'HIKVISION DS20HKVS-VX1 2TB', NULL, 3, 'barang_it', '', 'Capacity 2TB \r\nInterface SATA \r\nForm Factor 3.5 \r\nRPM 5400rpm\r\nCache 128MB', 891000.00, NULL, 'unit', 0, 0, '68c3d48de793a.jpeg', 'aktif', '2025-09-12 06:43:05', '2025-09-12 08:06:37', NULL, 0),
(86, 'SAMSUNG-19-148', 'SAMSUNG 19\" HDMI LS19A330N', NULL, 5, 'barang_it', 'Screen Size (Class) 19\r\nFlat / Curved Flat\r\nActive Display Size (HxV) (mm) 409.8mm x 230.4mm\r\nScreen Curvature N/A\r\nAspect Ratio 16:9\r\nPanel Type TN\r\nBrightness (Typical) 250cd/\r\nPeak Brightness (Typical) N/A\r\nBrightness (Min) 200cd/\r\nContrast Ratio Static 600:1(Typical)\r\nDynamic Contrast Ratio Mega\r\nHDR(High Dynamic Range) N/A\r\nHDR10+ N/A\r\nResolution 1,366 x 768\r\nResponse Time 5 (GTG\r\nViewing Angle (H/V) 90/65\r\nColor Support Max 16.7M\r\nColor Gamut (NTSC 1976) 72%\r\nColor Gamut (DCI Coverage) N/A\r\nsRGB Coverage N/A\r\nAdobe RGB Coverage N/A\r\nRefresh Rate Max 60Hz', 'Screen Size (Class) 19\r\nFlat / Curved Flat\r\nActive Display Size (HxV) (mm) 409.8mm x 230.4mm\r\nScreen Curvature N/A\r\nAspect Ratio 16:9\r\nPanel Type TN\r\nBrightness (Typical) 250cd/\r\nPeak Brightness (Typical) N/A\r\nBrightness (Min) 200cd/\r\nContrast Ratio Static 600:1(Typical)\r\nDynamic Contrast Ratio Mega\r\nHDR(High Dynamic Range) N/A\r\nHDR10+ N/A\r\nResolution 1,366 x 768\r\nResponse Time 5 (GTG\r\nViewing Angle (H/V) 90/65\r\nColor Support Max 16.7M\r\nColor Gamut (NTSC 1976) 72%\r\nColor Gamut (DCI Coverage) N/A\r\nsRGB Coverage N/A\r\nAdobe RGB Coverage N/A\r\nRefresh Rate Max 60Hz', 1080000.00, NULL, 'unit', 0, 0, '68c3c31e253b9.jpg', 'aktif', '2025-09-12 06:51:21', '2025-09-12 08:13:57', NULL, 0),
(87, 'W-415', 'WD PURPLE INT SATA 3.5\" 1TB', NULL, 3, 'barang_it', 'Harddisk CCTV WD Purple 1TB.\r\nBisa untuk komputer, DVR, dan NVR.\r\nBisa untuk home storage (ezviz).', 'Harddisk CCTV WD Purple 1TB.\r\nBisa untuk komputer, DVR, dan NVR.\r\nBisa untuk home storage (ezviz).', 525000.00, NULL, 'unit', 0, 0, '68c3d73c0b8d9.jpg', 'aktif', '2025-09-12 06:55:45', '2025-09-12 08:18:04', NULL, 0),
(88, 'ADAPTOR-12-224', 'Adaptor 12v 5a Switching', 3, 3, 'barang_it', '• Size : 5.5mmx 2.1mm ( Diameter Lubang )\r\n• Input/Voltage : 100-240\r\n• Output : 12V~5A\r\n• Barang Original,,biasa digunakan untuk Produk Fingerprint/Akses Solution/ZKteco Dll', '• Size : 5.5mmx 2.1mm ( Diameter Lubang )\r\n• Input/Voltage : 100-240\r\n• Output : 12V~5A\r\n• Barang Original,,biasa digunakan untuk Produk Fingerprint/Akses Solution/ZKteco Dll', 70000.00, NULL, 'unit', 0, 0, '68c3d0509210a.jpg', 'aktif', '2025-09-12 07:01:07', '2025-09-12 07:48:32', NULL, 0),
(89, 'SSD-KINGST-503', 'KINGSTON SSD 240GB A400', NULL, 5, 'barang_it', 'Form Factor: 2.5Inch Interface: SATA III 6Gb/s \r\nKapasitas: 240GB \r\nKecepatan Baca: Up to 500MB/s Kecepatan \r\nTulis: Up to 350MB/s \r\nController: 2Ch \r\nNAND: TLC Ketebalan: 7mm Start-up', 'Form Factor: 2.5Inch Interface: SATA III 6Gb/s \r\nKapasitas: 240GB \r\nKecepatan Baca: Up to 500MB/s Kecepatan \r\nTulis: Up to 350MB/s \r\nController: 2Ch \r\nNAND: TLC Ketebalan: 7mm Start-up', 470000.00, NULL, 'unit', 0, 0, '68c3d6c4bc713.jpg', 'aktif', '2025-09-12 07:03:25', '2025-09-12 08:16:04', NULL, 0),
(91, 'P-236', 'HP Deskjet 2337 PRINTER', NULL, 5, 'barang_it', 'Mencetak\r\nPrint speed, black <=10\r\nWarna atau Hitam Warna\r\nPrint speed, color <=10\r\nKategori\r\nTipe Produk Inkjet printers\r\nMerek Famili Deskjet\r\nUsage\r\nSempurna Untuk Pribadi\r\nPemakaian Student, Primary, Pribadi dan Kantor Pribadi\r\nfitur\r\nfitur Scan to PDF\r\nFungsi Cetak pindai dan salin\r\nPenanganan Media Cetak\r\nFinished output handling Sheetfed\r\nEnvelope input capacity Up to 5 envelopes\r\nOutput capacity Up to 25 sheets\r\nMaximum output capacity (sheets) Up to 25 sheets\r\nPaper handling input, standard 60-sheet input tray\r\nInput capacity Up to 60 sheets\r\nPaper handling output, standard 25-sheet output tray\r\nStandard output capacity (envelopes) Up to 5 envelopes', 'Mencetak\r\nPrint speed, black <=10\r\nWarna atau Hitam Warna\r\nPrint speed, color <=10\r\nKategori\r\nTipe Produk Inkjet printers\r\nMerek Famili Deskjet\r\nUsage\r\nSempurna Untuk Pribadi\r\nPemakaian Student, Primary, Pribadi dan Kantor Pribadi\r\nfitur\r\nfitur Scan to PDF\r\nFungsi Cetak pindai dan salin\r\nPenanganan Media Cetak\r\nFinished output handling Sheetfed\r\nEnvelope input capacity Up to 5 envelopes\r\nOutput capacity Up to 25 sheets\r\nMaximum output capacity (sheets) Up to 25 sheets\r\nPaper handling input, standard 60-sheet input tray\r\nInput capacity Up to 60 sheets\r\nPaper handling output, standard 25-sheet output tray\r\nStandard output capacity (envelopes) Up to 5 envelopes', 740000.00, NULL, 'unit', 0, 0, '68c3d47052a89.jpg', 'aktif', '2025-09-12 07:14:01', '2025-09-12 08:10:45', NULL, 0),
(92, 'EPSON-TINT-747', 'Epson Tinta T003', NULL, 5, 'barang_it', 'Produk : Tinta Original 100%\r\nBrand : Epson\r\nTipe : 003\r\nWarna : Cyan, Magenta, Yellow dan Black\r\nVolume : 65ml\r\nGaransi : Resmi / Service center Epson terdekat dikota anda\r\nSupoort Printer : Epson L3110, L3150', 'Produk : Tinta Original 100%\r\nBrand : Epson\r\nTipe : 003\r\nWarna : Cyan, Magenta, Yellow dan Black\r\nVolume : 65ml\r\nGaransi : Resmi / Service center Epson terdekat dikota anda\r\nSupoort Printer : Epson L3110, L3150', 100000.00, NULL, '', 0, 0, '68c3d3708a107.jpeg', 'aktif', '2025-09-12 07:17:02', '2025-09-13 03:31:47', NULL, 0),
(93, 'KABEL-FO-1-019', 'Kabel FO 1C 3S Prescon 100M', NULL, 4, 'barang_it', '• Kategori: Drop Cable\r\n• Panjang: 100 Meter\r\n• Connector: SC UPC Single Core\r\n• Isi Dalam Paket: 1 Roll', '• Kategori: Drop Cable\r\n• Panjang: 100 Meter\r\n• Connector: SC UPC Single Core\r\n• Isi Dalam Paket: 1 Roll', 98000.00, NULL, '', 0, 0, '68c3d533cb529.jpg', 'aktif', '2025-09-12 07:19:38', '2025-09-12 08:09:23', NULL, 0),
(95, 'KABEL-FO-1-046', 'Kabel FO 1C 3S Prescon 200M', NULL, 4, 'barang_it', '• Kategori: Drop Cable\r\n• Panjang: 200 Meter\r\n• Connector: SC UPC Single Core\r\n• Isi Dalam Paket: 1 Roll', '• Kategori: Drop Cable\r\n• Panjang: 200 Meter\r\n• Connector: SC UPC Single Core\r\n• Isi Dalam Paket: 1 Roll', 160000.00, NULL, '', 0, 0, '68c3d5cf1fd54.jpg', 'aktif', '2025-09-12 07:21:59', '2025-09-12 08:11:59', NULL, 0),
(96, 'VGEN-PLATI-479', 'V-Gen Platinum SSD 2.5\" SATA3 256 GB', NULL, 4, 'barang_it', 'Dimensi : 100 x 70 x 6 mm Speed : Read up to 510 MB/s Write up to 410 MB/s Interface : SATA 3 - 6 GB/s Form Factor : 2.5 inch Warranty : 3 years one to one replacement Type : Internal Storage Supported : UDMA Mode 6 TRIM Support : Yes (Requires OS Support) Garbage Collection : Yes S.M.A.R.T : Yes Write Cache : Yes Host Protect Area : Yes APM : Yes NCQ : Yes 48-Bit : Yes Security : AES 256-Bit Full Disk Encryption (FDE) TCG/Opal V2.0 , Encryption Drive (IEEE1667) Volume : +/- 20 gr', '', 298000.00, NULL, '', 0, 0, '68c4f95bcfad2.jpg', 'aktif', '2025-09-12 07:24:10', '2025-09-13 04:55:55', NULL, 0),
(97, 'TPLINK-TLW-440', 'TP-LINK TL-WA801N 300Mbps Wireless N Access Point', NULL, 4, 'barang_it', 'TP-LINK TL-WA801N 300Mbps Wireless N Access Point\r\nTL-WA801N 300Mbps Wireless N Access Point\r\n300 Mbps wireless speed ideal for smooth HD video, voice streaming, and online gaming\r\nSupports multiple operation modes: Access Point, Multi-SSID, Client, and Range Extender modes\r\nProtects your home network with WPA2 encryption and makes quick connection with the push of a button\r\nUp to 30 meters (100 feet) of flexible deployment with included Passive Power over Ethernet Injector\r\nInterface 1 10/100 M Ethernet Port(RJ45)\r\nSupport Passive PoE\r\nButton Power On/O Button, WPS Button, Reset Button\r\nExternal Power Supply 9VDC / 0.6A\r\nWireless Standards IEEE 802.11n, IEEE 802.11g, IEEE 802.11b\r\nDimensions ( W x D x H ) 7.1 5.1 1.4 in (181.6 129.5 36.2 mm)\r\nAntenna Type 2 Fixed Omni-Directional Antennas', 'TP-LINK TL-WA801N 300Mbps Wireless N Access Point\r\nTL-WA801N 300Mbps Wireless N Access Point\r\n300 Mbps wireless speed ideal for smooth HD video, voice streaming, and online gaming\r\nSupports multiple operation modes: Access Point, Multi-SSID, Client, and Range Extender modes\r\nProtects your home network with WPA2 encryption and makes quick connection with the push of a button\r\nUp to 30 meters (100 feet) of flexible deployment with included Passive Power over Ethernet Injector\r\nInterface 1 10/100 M Ethernet Port(RJ45)\r\nSupport Passive PoE\r\nButton Power On/O Button, WPS Button, Reset Button\r\nExternal Power Supply 9VDC / 0.6A\r\nWireless Standards IEEE 802.11n, IEEE 802.11g, IEEE 802.11b\r\nDimensions ( W x D x H ) 7.1 5.1 1.4 in (181.6 129.5 36.2 mm)\r\nAntenna Type 2 Fixed Omni-Directional Antennas', 308000.00, NULL, 'unit', 0, 0, '68c4e1b995b59.jpg', 'aktif', '2025-09-12 07:29:23', '2025-09-13 03:15:05', NULL, 0),
(98, 'NETLINK-HT-695', 'Netlink HTB GS03 HTB-GS03 Gigabit', NULL, 4, 'barang_it', 'Ethernet Interface\r\n1 RJ-45 port - Gigabit\r\nFiber Interface\r\n1 SC port\r\nSC distance\r\nup to 3km\r\nEnclosure Material\r\nMetal Case\r\nPower Supply\r\nDC 5V/1A\r\nWorking Temperature\r\n-10~55C, H: 5%-90%', 'Ethernet Interface\r\n1 RJ-45 port - Gigabit\r\nFiber Interface\r\n1 SC port\r\nSC distance\r\nup to 3km\r\nEnclosure Material\r\nMetal Case\r\nPower Supply\r\nDC 5V/1A\r\nWorking Temperature\r\n-10~55C, H: 5%-90%', 132000.00, NULL, 'unit', 0, 0, '68c3d62621c38.jpg', 'aktif', '2025-09-12 07:32:17', '2025-09-12 08:13:26', NULL, 0),
(99, 'SSD-M2-VGE-726', 'V-Gen SSD M2 256GB NVMe Hyper', NULL, 4, 'barang_it', 'Dimensi : 80 x 22 x 2 mm\r\nKapasitas : 256GB\r\nMerek : V-GeN\r\nModel : M.2 NVMe Hyper\r\nKualitas : Original 100%\r\nKecepatan : Read up to 3500 MB/s, Write up to 2500 MB/s\r\nInterface : PCIe Gen 3.0 x4, NVMe 1.3\r\nForm Factor : M.2 (2280)\r\nController: Asolid / Silicon Motion\r\nGaransi : Resmi 3 Tahun (Rusak langsung tukar, tidak di service)\r\nTipe : Internal Storage (Bisa untuk eksternal)\r\nOperasi Sistem : All Operating Systems', 'Dimensi : 80 x 22 x 2 mm\r\nKapasitas : 256GB\r\nMerek : V-GeN\r\nModel : M.2 NVMe Hyper\r\nKualitas : Original 100%\r\nKecepatan : Read up to 3500 MB/s, Write up to 2500 MB/s\r\nInterface : PCIe Gen 3.0 x4, NVMe 1.3\r\nForm Factor : M.2 (2280)\r\nController: Asolid / Silicon Motion\r\nGaransi : Resmi 3 Tahun (Rusak langsung tukar, tidak di service)\r\nTipe : Internal Storage (Bisa untuk eksternal)\r\nOperasi Sistem : All Operating Systems', 348000.00, NULL, '', 0, 0, '68c3d7b91dfa3.jpeg', 'aktif', '2025-09-12 07:36:32', '2025-09-12 08:20:09', NULL, 0),
(100, 'TANG-HT315-062', 'Tang HT-315', NULL, 4, 'barang_it', 'Technical Specifications of Tang Crimper / Crimping Tools - VZ-NT3104\r\nDimension 19.9(L) x 6.5(W) x 1.8(H) cm\r\nOthers Material : Iron and plastic', 'Technical Specifications of Tang Crimper / Crimping Tools - VZ-NT3104\r\nDimension 19.9(L) x 6.5(W) x 1.8(H) cm\r\nOthers Material : Iron and plastic', 36000.00, NULL, '', 0, 0, '68c4dd1427d6f.jpeg', 'aktif', '2025-09-12 07:42:25', '2025-09-13 02:55:16', NULL, 0),
(101, 'TCM-36MM-4-889', 'TECHMA 3.6mm, 4.0MP IP Camera Outdoor Fullcolor Audio', NULL, 3, 'barang_it', 'Image Sensor : 1/3\"CMOS\r\nMin. Illumination : Color:0.02Lux@(F1.6,AGC ON),B/W:0Lux with IR\r\nShutter Time : 1s-1/100000s\r\nDay&Night : IR-cut filter with auto switch (ICR)\r\nWDR : DWDR\r\nS/N : >40dB\r\nAngle Adjustment : 0-360°;0-80°;0-360°\r\nLens Type : Fixed\r\nFocal Length : 2.8mm\r\nLens Mount : M12\r\nAperture Range : F1.6,Fixed\r\nField of View : 77.6°(H);44.1°(V);89.3°(D)\r\nDORI Distance :\r\nDetect:80.0m\r\nObserve:31.8m\r\nRecognize:16.0m\r\nIdentify:8.0m\r\nIR Distance : 30m\r\nWavelength : 850nm\r\nAlarm Trigger : Motion Detection;IP Address Conflict\r\nCommunication Interface : 1 RJ45 10M/ 100M self adaptive Ethernet port\r\nPower Supply : POE 802.3af,MAX 6W\r\nProtection : Surge protection 2000V;Lightning proof 6000V;IP67', 'Image Sensor : 1/3\"CMOS\r\nMin. Illumination : Color:0.02Lux@(F1.6,AGC ON),B/W:0Lux with IR\r\nShutter Time : 1s-1/100000s\r\nDay&Night : IR-cut filter with auto switch (ICR)\r\nWDR : DWDR\r\nS/N : >40dB\r\nAngle Adjustment : 0-360°;0-80°;0-360°\r\nLens Type : Fixed\r\nFocal Length : 2.8mm\r\nLens Mount : M12\r\nAperture Range : F1.6,Fixed\r\nField of View : 77.6°(H);44.1°(V);89.3°(D)\r\nDORI Distance :\r\nDetect:80.0m\r\nObserve:31.8m\r\nRecognize:16.0m\r\nIdentify:8.0m\r\nIR Distance : 30m\r\nWavelength : 850nm\r\nAlarm Trigger : Motion Detection;IP Address Conflict\r\nCommunication Interface : 1 RJ45 10M/ 100M self adaptive Ethernet port\r\nPower Supply : POE 802.3af,MAX 6W\r\nProtection : Surge protection 2000V;Lightning proof 6000V;IP67', 290000.00, NULL, '', 0, 0, '68c4df551c2c8.png', 'aktif', '2025-09-12 07:50:45', '2025-09-13 03:04:53', NULL, 0),
(102, 'TCM-28MM-4-500', 'TECHMA 2.8mm, 4.0MP IP Camera Indoor Fullcolor Audio', NULL, 3, 'barang_it', 'Image Sensor : 1/3\"CMOS\r\nMin. Illumination : Color:0.02Lux@(F1.6,AGC ON),B/W:0Lux with IR\r\nShutter Time : 1s-1/100000s\r\nDay&Night : IR-cut filter with auto switch (ICR)\r\nWDR : DWDR\r\nS/N : >40dB\r\nAngle Adjustment : 0-360°;0-80°;0-360°\r\nLens Type : Fixed\r\nFocal Length : 2.8mm\r\nLens Mount : M12\r\nAperture Range : F1.6,Fixed\r\nField of View : 77.6°(H);44.1°(V);89.3°(D)\r\nDORI Distance :\r\nDetect:80.0m\r\nObserve:31.8m\r\nRecognize:16.0m\r\nIdentify:8.0m\r\nIR Distance : 30m\r\nWavelength : 850nm\r\nAlarm Trigger : Motion Detection;IP Address Conflict\r\nCommunication Interface : 1 RJ45 10M/ 100M self adaptive Ethernet port\r\nPower Supply : POE 802.3af,MAX 6W\r\nProtection : Surge protection 2000V;Lightning proof 6000V;IP67', 'Image Sensor : 1/3\"CMOS\r\nMin. Illumination : Color:0.02Lux@(F1.6,AGC ON),B/W:0Lux with IR\r\nShutter Time : 1s-1/100000s\r\nDay&Night : IR-cut filter with auto switch (ICR)\r\nWDR : DWDR\r\nS/N : >40dB\r\nAngle Adjustment : 0-360°;0-80°;0-360°\r\nLens Type : Fixed\r\nFocal Length : 2.8mm\r\nLens Mount : M12\r\nAperture Range : F1.6,Fixed\r\nField of View : 77.6°(H);44.1°(V);89.3°(D)\r\nDORI Distance :\r\nDetect:80.0m\r\nObserve:31.8m\r\nRecognize:16.0m\r\nIdentify:8.0m\r\nIR Distance : 30m\r\nWavelength : 850nm\r\nAlarm Trigger : Motion Detection;IP Address Conflict\r\nCommunication Interface : 1 RJ45 10M/ 100M self adaptive Ethernet port\r\nPower Supply : POE 802.3af,MAX 6W\r\nProtection : Surge protection 2000V;Lightning proof 6000V;IP67', 280000.00, NULL, '', 0, 0, '68c4de11cfe48.png', 'aktif', '2025-09-12 07:51:34', '2025-09-13 02:59:29', NULL, 0),
(103, 'TCMN9610A--198', 'TECHMA-N9610A 10Ch Network Video Recorder', NULL, 3, 'barang_it', '10 channel Camera input up to 8MP\r\nH.265 Video Compretion\r\n1 slot HDD Support upto 4TB\r\nPlayback 4channel @1080P\r\nMotion detect Alarm, Human Counting\r\nVMS Software & P6SPro/P6Slite Smartphone APP\r\nPower 12VDC', '10 channel Camera input up to 8MP\r\nH.265 Video Compretion\r\n1 slot HDD Support upto 4TB\r\nPlayback 4channel @1080P\r\nMotion detect Alarm, Human Counting\r\nVMS Software & P6SPro/P6Slite Smartphone APP\r\nPower 12VDC', 575000.00, NULL, 'unit', 0, 0, '68c4e0473da8a.png', 'aktif', '2025-09-12 07:56:57', '2025-09-13 03:08:55', NULL, 0),
(104, 'ADAPTOR-12-244', 'Adaptor 12V/2A', NULL, 3, 'barang_it', 'Power Input : AC 100 - 240 Volt 50/60Hz\r\nPower Output : DC 12V 2A\r\nAda lampu indikator\r\nPanjang Kabel Power : 90cm\r\nJack DC : 5.5mm * 2.5mm\r\nCompatibel 5.5mm * 2.1mm', 'Power Input : AC 100 - 240 Volt 50/60Hz\r\nPower Output : DC 12V 2A\r\nAda lampu indikator\r\nPanjang Kabel Power : 90cm\r\nJack DC : 5.5mm * 2.5mm\r\nCompatibel 5.5mm * 2.1mm', 22000.00, NULL, '', 0, 0, '68c3d344c24b4.jpg', 'aktif', '2025-09-12 07:58:48', '2025-09-13 03:29:57', NULL, 0),
(105, 'LENOVO-IDE-369', 'Lenovo IdeaPad Flex 5 14ABR8', NULL, 5, 'barang_it', 'Processor : AMD Ryzen 5-5625U\r\nDisplay : 14″ WUXGA IPS Touch\r\nMemory : 16GB Soldered LPDDR4x-4266\r\nStorage : 512GB SSD\r\nGraphics : AMD Radeon Graphics\r\nKeyboard : Backlit, English\r\nOS : Windows 11 Home + Microsoft 365 Basic + Office Home 2024', 'Processor : AMD Ryzen 5-5625U\r\nDisplay : 14″ WUXGA IPS Touch\r\nMemory : 16GB Soldered LPDDR4x-4266\r\nStorage : 512GB SSD\r\nGraphics : AMD Radeon Graphics\r\nKeyboard : Backlit, English\r\nOS : Windows 11 Home + Microsoft 365 Basic + Office Home 2024', 10450000.00, NULL, 'unit', 0, 0, '68c3d5f534e85.png', 'aktif', '2025-09-12 08:03:38', '2025-09-12 08:12:37', NULL, 0),
(106, '-SSD-VISIP-434', 'VISIPRO SSD  256GB', NULL, 5, 'barang_it', '- Interface : M.2 2280\r\n- Capacity : 256GB\r\n​- DRAM : -\r\n- Max Seq Read / Write (MB/s) : 550 MB/s / 520 MB/s\r\n- Max Random Read / Write (10Ps) : 50k / 70k\r\n- Nano Flash : 3D TLC', '- Interface : M.2 2280\r\n- Capacity : 256GB\r\n​- DRAM : -\r\n- Max Seq Read / Write (MB/s) : 550 MB/s / 520 MB/s\r\n- Max Random Read / Write (10Ps) : 50k / 70k\r\n- Nano Flash : 3D TLC', 300000.00, NULL, '', 0, 0, '68c4dcd1ec6f9.jpg', 'aktif', '2025-09-12 08:07:42', '2025-09-13 02:54:09', NULL, 0),
(107, 'SPECTRA-KA-381', 'Spectra Kabel UTP CAT5E 305M', NULL, 4, 'barang_it', '- Merk : Spectra\r\n- Category : CAT5E\r\n- Diameter : 24AWG\r\n- Panjang : 305 Meter\r\n- Warna : Gray (abu-abu)', '- Merk : Spectra\r\n- Category : CAT5E\r\n- Diameter : 24AWG\r\n- Panjang : 305 Meter\r\n- Warna : Gray (abu-abu)', 330000.00, NULL, '', 0, 0, '68c3d68111716.jpg', 'aktif', '2025-09-12 08:10:37', '2025-09-12 08:14:57', NULL, 0),
(108, 'BELDEN-CON-088', 'Belden Connector UTP RJ45 CAT5E', NULL, 4, 'barang_it', '• Konektor RJ45 UTP - CAT5E\r\n\r\n• Dapat dipasangkan pada kabel BELDEN tipe 1583A\r\n\r\n• Pakai Crimping tools apa saja bisa di pakai', '• Konektor RJ45 UTP - CAT5E\r\n\r\n• Dapat dipasangkan pada kabel BELDEN tipe 1583A\r\n\r\n• Pakai Crimping tools apa saja bisa di pakai', 86000.00, NULL, '', 0, 0, '68c4dbbf06f00.jpg', 'aktif', '2025-09-12 08:12:24', '2025-09-13 02:49:35', NULL, 0),
(109, 'TP-LINK-SW-223', 'TP-Link Switch Hub 8 Port Gigabit SG1008D', NULL, 4, 'barang_it', 'HARDWARE FEATURES\r\nStandards and Protocols IEEE 802.3i/802.3u/ 802.3ab/802.3x\r\nInterface 8 10/100/1000Mbps RJ45 Ports\r\nAUTO Negotiation/AUTO MDI/MDIX\r\nFan Quantity Fanless\r\nPower Consumption Maximum: 4.63W (220V/50Hz)\r\nExternal Power Supply External Power Adapter (Output: 5VDC / 0.6A)\r\nJumbo Frame 15 KB\r\nSwitching Capacity 16 Gbps\r\nDimensions ( W x D x H ) 7.1 * 3.5 * 1.0 in. (180 * 90 * 25.5 mm)\r\nSOFTWARE FEATURES\r\nTransfer Method Store and Forward\r\nMAC Address Table 4K\r\nAdvanced Functions Green Technology, saving power up to 80%\r\n802.3X Flow Control, Back Pressure\r\nOTHERS\r\nCertification FCC, CE, RoHs\r\nPackage Contents 8-Port Gigabit Desktop Switch TL-SG1008D\r\nPower Adapter\r\nInstallation Guide\r\nEnvironment\r\nOperating Temperature: 0~40 (32~104); Storage Temperature: -40~70\r\n(-40~158); Operating Humidity: 10%~90% non-condensing; Storage Humidity:\r\n5%~90% non-condensing', 'HARDWARE FEATURES\r\nStandards and Protocols IEEE 802.3i/802.3u/ 802.3ab/802.3x\r\nInterface 8 10/100/1000Mbps RJ45 Ports\r\nAUTO Negotiation/AUTO MDI/MDIX\r\nFan Quantity Fanless\r\nPower Consumption Maximum: 4.63W (220V/50Hz)\r\nExternal Power Supply External Power Adapter (Output: 5VDC / 0.6A)\r\nJumbo Frame 15 KB\r\nSwitching Capacity 16 Gbps\r\nDimensions ( W x D x H ) 7.1 * 3.5 * 1.0 in. (180 * 90 * 25.5 mm)\r\nSOFTWARE FEATURES\r\nTransfer Method Store and Forward\r\nMAC Address Table 4K\r\nAdvanced Functions Green Technology, saving power up to 80%\r\n802.3X Flow Control, Back Pressure\r\nOTHERS\r\nCertification FCC, CE, RoHs\r\nPackage Contents 8-Port Gigabit Desktop Switch TL-SG1008D\r\nPower Adapter\r\nInstallation Guide\r\nEnvironment\r\nOperating Temperature: 0~40 (32~104); Storage Temperature: -40~70\r\n(-40~158); Operating Humidity: 10%~90% non-condensing; Storage Humidity:\r\n5%~90% non-condensing', 260000.00, NULL, 'unit', 0, 0, '68c4e0b5b8bc4.jpeg', 'aktif', '2025-09-12 08:14:59', '2025-09-13 08:10:57', NULL, 0),
(110, '12000-866', 'Ventuz Kabel HDMI 1,5M', NULL, 4, 'barang_it', '1, Center conductor: high quality digital signal delivered by oxygen-free copper\r\n\r\n2, Insulation: Using foamed PE as the insulation, ensure image quality and transmit image signal with stability.\r\n\r\n3, Inner shield:100% Aluminum/Mylar foil shield, prevent high frequency noises and interference\r\n\r\n4, Outer shield:95% copper braid shield, prevents most low frequency noises and interference.\r\n\r\n5, function: suitable for HDTV, home theater, DVD player,projector,PS3,XBOX 360 and other HDMI devices.\r\n\r\n6, High density triple shielding for maximum rejection of EMI and RFI\r\n\r\n7, Transfer speed: 10.5Gbps\r\n\r\n8, connectors: 24K gold-plated to ensure superior signal transfer and lifetime of maximum performance\r\n\r\n9, Color optional: Pearl golden, Matter black, Silver, Golden, Shiny black, white porcelain, black porcelain, chr', '1, Center conductor: high quality digital signal delivered by oxygen-free copper\r\n\r\n2, Insulation: Using foamed PE as the insulation, ensure image quality and transmit image signal with stability.\r\n\r\n3, Inner shield:100% Aluminum/Mylar foil shield, prevent high frequency noises and interference\r\n\r\n4, Outer shield:95% copper braid shield, prevents most low frequency noises and interference.\r\n\r\n5, function: suitable for HDTV, home theater, DVD player,projector,PS3,XBOX 360 and other HDMI devices.\r\n\r\n6, High density triple shielding for maximum rejection of EMI and RFI\r\n\r\n7, Transfer speed: 10.5Gbps\r\n\r\n8, connectors: 24K gold-plated to ensure superior signal transfer and lifetime of maximum performance\r\n\r\n9, Color optional: Pearl golden, Matter black, Silver, Golden, Shiny black, white porcelain, black porcelain, chr', 12000.00, NULL, '', 0, 0, '68c4e25b741ff.jpg', 'aktif', '2025-09-12 08:18:14', '2025-09-13 03:17:47', NULL, 0),
(111, 'VENTUZ-KAB-040', 'Ventuz Kabel HDMI 3M', NULL, 4, 'barang_it', '1, Center conductor: high quality digital signal delivered by oxygen-free copper\r\n\r\n2, Insulation: Using foamed PE as the insulation, ensure image quality and transmit image signal with stability.\r\n\r\n3, Inner shield:100% Aluminum/Mylar foil shield, prevent high frequency noises and interference\r\n\r\n4, Outer shield:95% copper braid shield, prevents most low frequency noises and interference.\r\n\r\n5, function: suitable for HDTV, home theater, DVD player,projector,PS3,XBOX 360 and other HDMI devices.\r\n\r\n6, High density triple shielding for maximum rejection of EMI and RFI\r\n\r\n7, Transfer speed: 10.5Gbps\r\n\r\n8, connectors: 24K gold-plated to ensure superior signal transfer and lifetime of maximum performance\r\n\r\n9, Color optional: Pearl golden, Matter black, Silver, Golden, Shiny black, white porcelain, black porcelain, chr', '1, Center conductor: high quality digital signal delivered by oxygen-free copper\r\n\r\n2, Insulation: Using foamed PE as the insulation, ensure image quality and transmit image signal with stability.\r\n\r\n3, Inner shield:100% Aluminum/Mylar foil shield, prevent high frequency noises and interference\r\n\r\n4, Outer shield:95% copper braid shield, prevents most low frequency noises and interference.\r\n\r\n5, function: suitable for HDTV, home theater, DVD player,projector,PS3,XBOX 360 and other HDMI devices.\r\n\r\n6, High density triple shielding for maximum rejection of EMI and RFI\r\n\r\n7, Transfer speed: 10.5Gbps\r\n\r\n8, connectors: 24K gold-plated to ensure superior signal transfer and lifetime of maximum performance\r\n\r\n9, Color optional: Pearl golden, Matter black, Silver, Golden, Shiny black, white porcelain, black porcelain, chr', 19000.00, NULL, '', 0, 0, '68c4abb85e42b.jpg', 'aktif', '2025-09-12 08:18:43', '2025-09-12 23:24:40', NULL, 0),
(112, 'VENTUZ-KAB-051', 'Ventuz Kabel HDMI 5M', NULL, 4, 'barang_it', '', '1, Center conductor: high quality digital signal delivered by oxygen-free copper\r\n\r\n2, Insulation: Using foamed PE as the insulation, ensure image quality and transmit image signal with stability.\r\n\r\n3, Inner shield:100% Aluminum/Mylar foil shield, prevent high frequency noises and interference\r\n\r\n4, Outer shield:95% copper braid shield, prevents most low frequency noises and interference.\r\n\r\n5, function: suitable for HDTV, home theater, DVD player,projector,PS3,XBOX 360 and other HDMI devices.\r\n\r\n6, High density triple shielding for maximum rejection of EMI and RFI\r\n\r\n7, Transfer speed: 10.5Gbps\r\n\r\n8, connectors: 24K gold-plated to ensure superior signal transfer and lifetime of maximum performance\r\n\r\n9, Color optional: Pearl golden, Matter black, Silver, Golden, Shiny black, white porcelain, black porcelain, chr', 26000.00, NULL, '', 0, 0, '68c4ab935cb94.jpg', 'aktif', '2025-09-12 08:21:00', '2025-09-12 23:24:03', NULL, 0),
(113, 'MSI-THIN-1-975', 'MSI THIN 15 B12UCX', NULL, 5, 'barang_it', '', '☑ Processor : 12th Gen Intel® Core™ i7-12650H processor 10 cores (6 P-cores + 4 E-cores), Max Turbo Frequency 4.7 GHz\r\n\r\n☑ Graphics : NVIDIA® GeForce RTX™2050 Laptop GPU 4GB GDDR6\r\n\r\n☑ Display : 15.6″ FHD(1920×1080), 144Hz Refresh Rate, IPS-Level\r\n\r\n☑ Memory : 8GB / 16GB DDR4-3200, 2 Slots, Max 64GB\r\n\r\n☑ Storage : 512GB NVMe SSD PCIe Gen4, 1x 2.5″ SATA HDD\r\n\r\n☑ Keyboard : Single Backlit Keyboard (Blue)\r\n\r\n☑ Wireless : 802.11 ax Wi-Fi 6E + Bluetooth v5.3\r\n\r\n☑ Webcam : HD type (30fps@720p)\r\n\r\n☑ Audio : 2x 2W Speaker\r\n\r\n☑ Battery : 3-Cell 52.4 Battery (Whr), 120W adapter\r\n\r\n☑ Color : Cosmos Gray\r\n\r\n☑ OS : Windows 11 Home\r\n\r\n☑ Ports : 1x Type-C (USB3.2 Gen1 / DP); 3x Type-A USB3.2 Gen1; 1x HDMI™ (4K @ 30Hz); 1x RJ45; 1x Mic-in, 1x Headphone-out', 8669000.00, NULL, 'unit', 0, 0, '68c4ab364309e.jpeg', 'aktif', '2025-09-12 08:23:40', '2025-09-12 23:22:30', NULL, 0),
(114, 'L-718', 'Logitech Mouse MK220', NULL, 5, 'barang_it', 'Much smaller design, same keys\r\nThe compact keyboard is about 36% smaller than standard keyboards but still has all the standard keysso doing the things you love is as easy as ever.\r\nAdvanced 2.4 GHz wireless\r\n128-bit AES encryption\r\nFewer battery hassles\r\n\r\nPackage Contents:\r\nKeyboard (K220)\r\nMouse (M150)\r\nUSB receiver\r\n2 AAA (keyboard) and 2 AA (mouse) batteries\r\nUser documentation\r\n\r\nSystem Requirements\r\nWindows Vista, Windows XP, Windows 7, Windows 8, Windows 10\r\nUSB port', 'Much smaller design, same keys\r\nThe compact keyboard is about 36% smaller than standard keyboards but still has all the standard keysso doing the things you love is as easy as ever.\r\nAdvanced 2.4 GHz wireless\r\n128-bit AES encryption\r\nFewer battery hassles\r\n\r\nPackage Contents:\r\nKeyboard (K220)\r\nMouse (M150)\r\nUSB receiver\r\n2 AAA (keyboard) and 2 AA (mouse) batteries\r\nUser documentation\r\n\r\nSystem Requirements\r\nWindows Vista, Windows XP, Windows 7, Windows 8, Windows 10\r\nUSB port', 280000.00, NULL, '', 0, 0, '68c4aad1d0c6e.jpg', 'aktif', '2025-09-12 08:30:02', '2025-09-13 03:32:24', NULL, 0),
(115, 'SPC-LED-19-833', 'SPC LED 19\" SM-19HD+HDMI', NULL, 4, 'barang_it', '', 'Office 19 inch / black\r\nRespon time 3ms/ HDMI / VGA\r\nFULLHD\r\nLED display\r\n60HZ high refresh / 1.98kg', 620000.00, NULL, 'unit', 0, 0, '68c4aa858241b.jpeg', 'aktif', '2025-09-12 08:38:30', '2025-09-12 23:19:33', NULL, 0),
(116, 'VURRION-GT-056', 'Vurrion GT 610 LP 2GB DDR3 64 BIT', NULL, 4, 'barang_it', '', 'Brand : Vurrion\r\n\r\nType : GT 610 LP\r\n\r\nGPU : GF116\r\n\r\nArchitecture : Fermi\r\n\r\nStream Processor : 48\r\n\r\nGPU Clock : 810MHz\r\n\r\nMemory Clock : 500MHz\r\n\r\nMemory Size : 2048 MB\r\n\r\nMemory Type : GDDR3\r\n\r\nMemory Width : 64-Bit\r\n\r\nOutput : 1x DVI, 1x HDMI, 1x VGA\r\n\r\nCooling System : SINGLE FAN', 280000.00, NULL, '', 0, 0, '68c4aa3f41024.jpg', 'aktif', '2025-09-12 08:43:10', '2025-09-12 23:18:23', NULL, 0),
(117, 'SEAGATE-SK-106', 'Seagate SkyHawk 1TB', NULL, 3, 'barang_it', '', '✅ Form Factor: 3.5 Inch\r\n\r\n✅ Kapasitas: 1TB \r\n\r\n✅ Dimensi: 146.9 x 101.8 x 20.2 mm\r\n\r\n⚡Performance\r\n\r\n✅ Interface: SATA III 6Gb/s\r\n\r\n✅ Transfer Rate: hingga 180 MB/s\r\n\r\n✅ Rotation Speed: 5400 RPM\r\n\r\n✅ Cache: 256MB\r\n\r\n⚡Features\r\n\r\n✅ Mendukung hingga 8 drive bays\r\n\r\n✅ Mendukung hingga 64 kamera\r\n\r\n✅ Recording Technology: SMR\r\n\r\n⚡Reliability\r\n\r\n✅ Power On Hours: 8760 jam/tahun (24x7)\r\n\r\n✅ Workload Rate Limit: 180TB/tahun\r\n\r\n✅ MTBF: 1.000.000 jam\r\n\r\n✅ Garansi resmi: 3 tahun\r\n\r\n✅ Seagate Rescue Data Recovery: 3 tahun\r\n\r\n⚡Power Management\r\n\r\n✅ Startup Power: 1.8A\r\n\r\n✅ Idle Average: 2.5W\r\n\r\n✅ Operating Mode: 3.7W\r\n\r\n✅ Sleep Mode: 0.25W\r\n\r\n⚡Environmental\r\n\r\n✅ Operating Temp: 0°C – 70°C\r\n\r\n✅ Non Operating Temp: -40°C', 525000.00, NULL, '', 0, 0, '68c4a9e2ad151.png', 'aktif', '2025-09-12 09:22:36', '2025-09-12 23:16:50', NULL, 0),
(118, 'T-470', 'TECHMA TCM-T1A20 2MP', 3, 3, 'barang_it', NULL, '1/3 CMOS High Definition Sensor\r\nResolution 2.0MP, Effective Pixels 1920×1080\r\nHD Lens 3.6mm\r\n4 in 1 Protocol CVI, TVI, AHD, Analog\r\nPower 12VDC', 200000.00, NULL, 'unit', 0, 0, '68c4ea424f0f4.png', 'aktif', '2025-09-13 03:51:30', '2025-09-13 03:51:30', NULL, 0),
(119, 'T-081', 'TAPO C200 Pan/Tilt Home Security Wi-Fi Camera', 3, 3, 'barang_it', NULL, 'High-Definition Video: The Tapo C200 features 1080p high-definition video, providing users with clear and detailed footage.\r\nPan and Tilt: The device offers 360° horizontal and 114° vertical range, enabling complete coverage of the area.\r\nNight Vision: With advanced night vision up to 40 feet, the Tapo C200 allows users to monitor their homes around the clock.\r\nMotion Detection and Alerts: The device uses smart motion detection technology to send instant notifications to your phone whenever movement is detected.\r\nTwo-Way Audio: The Tapo C200 comes equipped with a built-in microphone and speaker, allowing users to communicate with family, pets, or warn off intruders.\r\nLocal Storage: The device supports microSD cards up to 512GB for local storage, providing a secure and cost-effective way to store footage.\r\nPrivacy Mode: Users can enable Privacy Mode to stop recording and control when the camera is monitoring and when it\'s not.\r\nEasy Setup and Management: With the Tapo app, users can easily set up and manage their Tapo C200, and access live streaming and other controls.\r\nVoice Control: The Tapo C200 is compatible with Google Assistant and Amazon Alexa, offering hands-free control for users.\r\nSecure Encryption: The device uses advanced encryption and wireless protocols to ensure data privacy and secure communication between your phone and the device.', 450000.00, NULL, 'unit', 0, 0, '68c4eb7eab519.jpg', 'aktif', '2025-09-13 03:56:46', '2025-09-13 03:56:46', NULL, 0);
INSERT INTO `produk` (`id`, `kode_produk`, `nama_produk`, `kategori_id`, `vendor_id`, `jenis`, `deskripsi`, `spesifikasi`, `harga_satuan`, `harga_grosir`, `satuan`, `stok_minimal`, `stok_tersedia`, `gambar`, `status`, `created_at`, `updated_at`, `harga_diskon`, `is_featured`) VALUES
(120, 'DAHUA-HERO-470', 'DAHUA Hero A1 CCTV Wifi 360 2MP Smart Indoor Camera Two Way Talk', 3, 3, 'barang_it', NULL, 'Spesifikasi Produk:\r\n• Model: DH-H2A\r\n• Video: 2 MP (1920 × 1080)\r\n• Pan-Tilt: 3Pan: 0° to 355°;Tilt: -5° to +80°\r\n• Lens Type: 3.6mm Fixed Lens\r\n• IR Length: 10m\r\n• Wireless: IEEE 802.11b/g/n 2.4–2.4835 GHz, 2.4 G\r\n• Interface: Micro SD Card Slot (up to 256GB), Port Lan, Built-in Mic & Speaker, Reset Button\r\n• Power: 5V DC (Max 1.7W)\r\n• Net Dimension: φ77.8 mm × 108.1 mm\r\n• Net Weight: 370g', 450000.00, NULL, 'unit', 0, 0, '68c4ec410a619.jpg', 'aktif', '2025-09-13 04:00:01', '2025-09-13 04:00:01', NULL, 0),
(121, 'V380-PRO-K-579', 'V380 Pro Kamera CCTV Bohlam Dual Lens WiFi IP Camera E27 1080P E9', 3, 3, 'barang_it', NULL, 'Kamera Dual Lens\r\nV380 membekali produknya dengan dual lens kamera sehingga dapat memantau kondisi lingkungan dengan maksimal. Kamera pada bagian atas berfungsi untuk menangkap gambar ruangan secara keseluruhan. Sedangkan kamera bawah dapat digunakan untuk memantau sudut tertentu. Semakin aman dengan adanya fitur 360° panorama.>\r\nResolusi Video Full HD 1080 Piksel\r\nMeski ukurannya mini, kamera CCTV dari V380 ini dapat merekam video dengan kualitas tinggi. Anda bisa melihat hasil rekaman dengan resolusi video Full HD 1080 pikselyang jelas dan jernih. Sangat sesuai untuk memantau berbagai aktivitas yang terjadi di lingkungan Anda.\r\nModel Lampu Bohlam\r\nMenawarkan model seperti lampu bohlam Anda bisa memasang CCTV ini di mana saja. Mulai dari rumah, garasi, mobil, pertokoan, hingga pabrik, letakkan kamera CCTV pada sudut yang tak terduga untuk memastikan lingkungan Anda aman dari berbagai tindak kejahatan. Kini Anda bisa memantau kondisi dari berbagai angle tanpa dibatasi ukuran ruangan. Pastikan Anda menggunakan soket lampu E27 agar dapat terpasang dengan sempurna.\r\nTeknologi Human Shape Detector 2.0', 350000.00, NULL, 'unit', 0, 0, '68c4ed68722c6.png', 'aktif', '2025-09-13 04:04:56', '2025-09-13 04:04:56', NULL, 0),
(122, 'AVARO-CCTV-310', 'AVARO CT03 CCTV WIFI SMART OUTDOOR IP CAM 4MP', 3, 3, 'barang_it', NULL, '• Resolution 4 MP\r\n• Image Sensor 1/3” Color CMOS\r\n• Image Compression H.265\r\n• IR Distance 10M\r\n• Audio Build-in Mic & Speaker\r\n• Micro SD Card Up to 128GB\r\n• Connection WiFi 2.4 GHz\r\n• RJ45 / LAN Onvif\r\n• Working Temperature 0 °C ~ 45 ° C Working Humidity 10% - 95', 350000.00, NULL, 'unit', 0, 0, '68c4edf76dc95.jpg', 'aktif', '2025-09-13 04:07:19', '2025-09-13 04:07:19', NULL, 0),
(123, 'SAMSUNG-75-054', 'SAMSUNG 75DU7000 CRYSTAL UHD 4K 75 inch SMART TV', 12, 1, 'barang_it', NULL, 'Product Type\r\nLED\r\nSeries\r\n7\r\nDisplay\r\nScreen Size75″\r\nRefresh Rate60Hz\r\nResolution3,840 x 2,160\r\nVideo\r\nPicture EngineCrystal Processor 4K\r\nOne Billion ColorYes\r\nHDR (High Dynamic Range)HDR\r\nHDR 10+Support\r\nHLG (Hybrid Log Gamma)Yes\r\nContrastMega Contrast\r\nColorPur Color\r\nBrightness/Color DetectionBrigtness Detection\r\nMicro DimmingUHD Dimming\r\nContrast EnhancerYes\r\nFilm ModeYes\r\nMotion TechnologyMotion Xcelerator\r\nPicture ClarityYes\r\nFilmmaker Mode (FMM)Yes\r\nAudio\r\nAdaptive SoundAdaptive Sound\r\nObject Tracking SoundOTS Lite\r\nQ-SymphonyYes\r\nSound Output (RMS)20W\r\nSpeaker Type2CH\r\nMultiroom LinkYes\r\nBlutooth AudioYes\r\nDual Audio Support (Bluetooth)Yes\r\nBuds Auto SwitchYes\r\nSmart Service\r\nOperating SystemTizen™ Smart TV\r\nWeb BrowserYes\r\nSmartThingsYes\r\nMedia HomeYes\r\nSmart Feature\r\nMulti Device ExperienceMobile to TV, TV Sound to Mobile, Sound Mirroring, Wireless TV On\r\nTap ViewYes\r\nVideo CommunicationGoogle Meet\r\nMobile Camera SupportYes\r\nEasy SetupYes\r\nApp CastingYes\r\nWireless DexYes\r\nWeb ServiceMicrosoft 365\r\nNFTNifty Gateway\r\nDifferentiation\r\nAnalog Clean ViewYes\r\nTriple ProtectionYes\r\nGame Feature\r\nAuto Game Mode (ALLM)Yes\r\nHGiGYes\r\nTuner/Broadcasting\r\nDigital BroadcastingDVB-T2CS2\r\nAnalog TunerYes\r\nTV Key SupportYes\r\nConnectivity\r\nHDMI3\r\nUSB1\r\nEthernet (LAN)1\r\nDigital Audio Out (Optical)1\r\nRF In (Terrestrial / Cable input)1/1(Common Use for Terrestrial)/1\r\nHDMI Audio Return ChanneleARC/ARC\r\nWireless LAN Built-inYes (WiFi5)\r\nBluetoothYes (BT5.2)\r\nAnynet+ (HDMI-CEC)Yes\r\nDesign\r\nDesignSlim Look\r\nBezel Type3 Bezel-less\r\nSlim TypeSlim look\r\nFront ColorBLACK\r\nStand TypeSLIM FEET\r\nStand ColorBLACK\r\nAdditional Feature\r\nCaption (Subtitle)Yes\r\nConnectShare™Yes\r\nEPGYes\r\nOSD LanguageLocal Languages\r\nTeletext (TTX)Yes\r\nMBR SupportYes\r\nAccessibility\r\nVoice GuideUK English, France French,Hindi , Russian , Korean\r\nLearn TV Remote / Learn Menu ScreenUAE: UK English, French,Arabic ,Persian / AFR: UK English, French, Portuguese / Egypt,Libya: UK English, French, Spanish\r\nLow Vision SupportAudio Description, Zoom Menu and Text, High Contrast, SeeColors, Color Inversion, Grayscale, Picture Off\r\nHearing Impaired SupportMulti-output Audio, Sign Language Zoom\r\nMotor Impaired SupportSlow Button Repeat\r\nPower & Eco Solution\r\nPower SupplyAC100-240V~ 50/60Hz\r\nPower Consumption (Max)260 W\r\nEco SensorYes\r\nAuto Power OffYes\r\nAuto Power SavingYes\r\nDimension\r\nPackage Size (WxHxD)1840 x 1118 x 198 mm\r\nSet Size with Stand (WxHxD)1673.2 x 1047.9 x 341.1 mm\r\nSet Size without Stand (WxHxD)1673.2 x 958.2 x 59.9 mm\r\nStand (Basic) (WxD)1426.5 x 341.1 mm\r\nVESA Spec400 x 400 mm\r\nWeight\r\nPackage Weight39.10 kg\r\nSet Weight with Stand30.80 kg\r\nSet Weight without Stand30.4 kg\r\nAccessory\r\nRemote Controller ModelTM2240A\r\nBatteries (for Remote Control)Yes\r\nVesa Wall Mount SupportYes\r\nFull Motion Slim Wall Mount (Y22)Yes\r\nWebcam SupportYes\r\nZigbee / Thread ModuleDongle Support\r\nUser ManualYes\r\nE-ManualYes\r\nPower CableYes', 11500000.00, NULL, 'unit', 0, 0, '68c4ef54789cb.png', 'aktif', '2025-09-13 04:13:08', '2025-09-13 04:13:08', NULL, 0),
(124, 'D-376', 'DAHUA DH-XVR1B08-I 8 Channel Penta-brid 1080N/720p Cooper 1U', 3, 3, 'barang_it', NULL, '> H.265+/H.265 dual-stream video compression\r\n> Supports Full channel AI-Coding\r\n> Supports HDCVI/AHD/TVI/CVBS/IP video inputs\r\n> Max 10 channels IP camera inputs, each channel up to 6MP; Max 40 Mbps incoming bandwidth\r\n> Up to 4 channels video stream ( analog channel ) SMD Plus', 875000.00, NULL, 'unit', 0, 0, '68c4f0aa3b019.png', 'aktif', '2025-09-13 04:18:50', '2025-09-13 04:18:50', NULL, 0),
(125, 'UGREEN-NET-905', 'UGREEN Network Kabel Lan Tester RJ11 RJ45 10950 - 10950', 3, 6, 'barang_it', NULL, 'Deskripsi :\r\n- Pabrikan: UGREEN\r\n- Kode Produk: ( 10951B )\r\n- Warna: Hitam, hijau\r\n- Fungsi: Periksa kabel jaringan, kabel telepon\r\n- Masukan: RJ12, RJ11, RJ45\r\n- Keluaran: RJ45j\r\n- Baterai yang digunakan: baterai 9V (termasuk)\r\n- Menambahkan fungsi mendeteksi kabel jaringan di wire harness dan menghasilkan sinyal membantu pengguna dengan mudah mendeteksi kabel jaringan yang perlu mereka temukan di bundel kabel.\r\n- Mendukung pengujian POE - Free Pouch Bag', 350000.00, NULL, 'unit', 0, 0, '68c4f19ab6da9.jpg', 'aktif', '2025-09-13 04:22:50', '2025-09-13 04:22:50', NULL, 0),
(126, 'T-660', 'TP-LINK LS1005 5-Port 10/100Mbps Desktop Network Switch', 3, 3, 'barang_it', '', '-5× 10/100Mbos Auto-Negotiation RJ45 ports, supporting Auto-MDI/MDIX\r\n-Green Ethernet technology saves power consumption\r\n-IEEE 802.3X flow control provides reliable data transfer\r\n-Plastic casing and desktop design\r\n-Plug and play, no configuration required\r\n-Fanless design ensures quiet operation', 95000.00, NULL, 'unit', 0, 0, '68c4f3bd1e472.jpg', 'aktif', '2025-09-13 04:31:57', '2025-09-13 08:10:40', NULL, 0),
(127, 'V-232', 'Video BALUN 4CH', 3, 1, 'barang_it', NULL, 'Balun Type: Pasif\r\nChannel: 4 Channel\r\nVideo mengirimkan jarak: 300 m dengan pasif video transceiver, 900 m dengan aktif video transceiver\r\nVideo Format: NTSC, PAL, SECAM\r\nkawat Type: CAT 5/5E/6 kabel twisted pair\r\nbahan: Logam\r\nItem Berat: 178g/6.28 oz\r\nItem Ukuran: 11.7*7.8*2.4 cm/4.33*3.07 * 0.945in\r\npaket Berat: 202g/7.13 oz\r\npaket Ukuran: 13.1*8.2*2.9 cm/5.16*3.23 * 1.14i', 100000.00, NULL, 'unit', 0, 0, '68c4f4a1b609b.jpg', 'aktif', '2025-09-13 04:35:45', '2025-09-13 04:35:45', NULL, 0),
(128, 'HDMI-EXTEN-396', 'HDMI Extender Up To 60 Meter Over Lan RJ45 Cat6/Cat5', 3, 6, 'barang_it', NULL, 'Jarak ekstensi: Hingga 60 meter.\r\nKompatibel dengan kabel LAN Cat6/Cat5.\r\nPaket lengkap: Dapatkan 2 unit extender.\r\nAdaptor termasuk dalam paket.\r\nKeunggulan\r\n✅ Dapat 2 unit. \r\n✅ Include adaptor.\r\n✅ Original produk dengan garansi tukar baru.', 400000.00, NULL, 'unit', 0, 0, '68c4f560882cc.jpeg', 'aktif', '2025-09-13 04:38:56', '2025-09-13 04:38:56', NULL, 0),
(129, 'PASSIVE-VI-570', 'Passive Video Balun HD, AHD, HDCVI And TVI 720p 1080P 300m', 3, 6, 'barang_it', NULL, 'Compatible with HD/AHD/HDCVI/TVI\r\ntransmission signal: 720P/1080P\r\ncable Cat 5/5e/6\r\ntransmision distance to 300m for HD/HDCVI/AHD, 200M for TVI\r\nNo power required\r\nBuilt-in surge & transient protection\r\nSuper interference rejection', 40000.00, NULL, 'unit', 0, 0, '68c4f5d67dc63.jpg', 'aktif', '2025-09-13 04:40:54', '2025-09-13 04:40:54', NULL, 0),
(130, 'VIDEO-BALU-725', 'Video balun 8mp passive video balun HD CVI TVI AHD', 3, 6, 'barang_it', NULL, 'cctv via twisted pairs\r\nsupport 720 960 1080p 3mp 4mp 5mp 8mp\r\n1set/2pcs\r\nfitur\r\n* kompatibel dengan semua digital HD-TVI, CVI, dan kamera analog AHD\r\n* tidak ada daya yang diperlukan\r\n* warna video hingga 1000FT untuk HD-CVI kamera 720 P digital\r\n* warna video hingga 650FT untuk HD-CVI kamera 1080 P digital\r\n* warna video hingga 650FT untuk HD-TVI kamera 720 P & 1080 P digital\r\n* warna video hingga 1000FT untuk 720 & 960 & 1080 p analog AHD kamera\r\n* Push pin terminal koneksi untuk UTP kabel\r\n* built-in TVS (transient voltage suppressor) untuk perlindungan surge\r\n* desain anti-statis gelombang filter\r\n* desain proteksi petir (KELAS III)\r\n* 60dB crosstalk dan kebisingan kekebalan; exceptional penolakan gangguan', 35000.00, NULL, 'unit', 0, 0, '68c4f637cd8af.jpg', 'aktif', '2025-09-13 04:42:31', '2025-09-13 04:42:31', NULL, 0),
(131, 'V-454', 'V-GEN Titans Flashdisk 32GB', 1, 4, 'barang_it', NULL, 'Kecepatan : Read up to 125 Mbps,\r\nWrite up to 108 Mbps\r\nDimensi : 54x20x8 mm\r\nVolume : 10gr\r\nGaransi : Lifetime Warranty One to One Replacement', 95000.00, NULL, 'unit', 0, 0, '68c4f7a15187b.jpg', 'aktif', '2025-09-13 04:48:33', '2025-09-13 04:48:33', NULL, 0),
(132, 'VGEN-SSD-1-382', 'V-GEN SSD 128GB PLATINUM SATA3', 1, 4, 'barang_it', NULL, 'Dimensi : 100 x 70 x 6 mm\r\nSpeed : Read up to 510Mbps\r\nWrite up to 410Mbps\r\nInterface : SATA 3 - 6GB/s\r\nForm Factor : 2.5 inch\r\nWarranty : 5 years one to one replacement\r\nType : Internal Storage\r\nSupported : UDMA Mode 6\r\nTRIM Support : Yes (Requires OS Support)\r\nGarbage Collection : Yes\r\nS.M.A.R.T : Yes\r\nWrite Cache : Yes\r\nHost Protect Area : Yes\r\nAPM : Yes\r\nNCQ : Yes\r\n48-Bit : Yes\r\nSecurity : AES 256-Bit Full Disk Encryption (FDE)\r\nTCG/Opal V2.0 , Encryption Drive (IEEE1667)', 250000.00, NULL, 'unit', 0, 0, '68c4f8a5d4ee7.jpg', 'aktif', '2025-09-13 04:52:53', '2025-09-13 04:52:53', NULL, 0),
(133, 'VGEN-256GB-802', 'V-GeN SSD 256GB RESCUE SATA III', 1, 4, 'barang_it', NULL, 'Dimensi : 100 x 70 x 6 mm\r\nSpeed : Read up to 500 MB/s\r\nWrite up to 400 MB/s\r\nInterface : SATA 3 - 6 GB/s\r\nForm Factor : 2.5 inch\r\nWarranty : 3 years one to one replacement\r\nType : Internal Storage\r\nSupported : UDMA Mode 6\r\nTRIM Support : Yes (Requires OS Support)\r\nGarbage Collection : Yes\r\nS.M.A.R.T : Yes\r\nWrite Cache : Yes\r\nHost Protect Area : Yes\r\nAPM : Yes\r\nNCQ : Yes\r\n48-Bit : Yes\r\nSecurity : AES 256-Bit Full Disk Encryption (FDE)\r\nTCG/Opal V2.0 , Encryption Drive (IEEE1667)\r\nVolume : +/- 20 gr', 325000.00, NULL, 'unit', 0, 0, '68c4f930cb13d.jpg', 'aktif', '2025-09-13 04:55:12', '2025-09-13 04:55:12', NULL, 0),
(134, 'SK-HYNIX-8-555', 'SK Hynix 8GB DDR4 3200 MHz PC-25600 Sodimm', 1, 6, 'barang_it', NULL, 'Ram Laptop SK Hynix 8GB DDR4 3200 MHz PC-25600 Sodimm SO-Dimm\r\nSK HYNIX DDR4 8 GB ~ 3200 SODIMM PC-25600 = 3200 MHZ', 275000.00, NULL, 'unit', 0, 0, '68c4fa8557674.jpeg', 'aktif', '2025-09-13 05:00:53', '2025-09-13 05:00:53', NULL, 0),
(135, 'USB-LAN-AD-732', 'USB LAN ADAPTER / USB TO ETHERNET RJ45 Komputer Laptop', 3, 6, 'barang_it', NULL, 'USB to LAN merupakan sebuah perangkat yang dapat digunakan untuk mengganti fungsi slot USB menjadi fungsi slot untuk LAN. Sangat cocok untuk komputer atau laptop yang tidak memiliki slot LAN. Alat ini sangat mudah untuk digunakan, Anda hanya tinggal mencolokkannya ke slot USB dan saat itu juga Anda dapat menggunakannya.', 30000.00, NULL, 'unit', 0, 0, '68c4fadb1596f.jpg', 'aktif', '2025-09-13 05:02:19', '2025-09-13 05:02:19', NULL, 0),
(136, 'HUB-USB-20-623', 'M-Tech Hub USB 2.0 4 Port 4 Switch - Hitam', 3, 6, 'barang_it', NULL, 'Spesifikasi Produk\r\nProduk : USB Hub Versi 2.0 4 Port 4 Switch\r\nJenis : USB HUB\r\nBrand : M-Tech\r\nUSB : USB Versi 2.0\r\nPort USB : 4 Port\r\nada Switch On Off\r\nSupports up to 480 Mbps data transfer rate\r\nPanjang kabel 50cm', 35000.00, NULL, 'unit', 0, 0, '68c4fb432bb01.jpg', 'aktif', '2025-09-13 05:04:03', '2025-09-13 05:04:03', NULL, 0),
(137, 'PANASONIC--134', 'Panasonic Baterai koin CR2032', 1, 6, 'barang_it', NULL, 'BATERAI PANASONIC LITHIUM COIN TYPE CR-2032/5BE.\r\n1) Baterai Lithium Coin menghadirkan daya tahan lama pada berbagai perangkat.\r\n2) Voltage : 3 Volt.\r\n3) 1 Pack isi 5 Pcs\"', 40000.00, NULL, 'unit', 0, 0, '68c4fb8235a54.jpg', 'aktif', '2025-09-13 05:05:06', '2025-09-13 05:05:06', NULL, 0),
(138, 'BATRAI-MAX-776', 'MAXELL Cell Lithium CR 2032 STANDART', 3, 6, 'barang_it', NULL, 'MerekMaxell\r\nSKU7913126780_ID-14329468861\r\nJenis Inti Baterai Li-Ion (Litium-Ion)\r\nFitur BateraiSetiap hari\r\nKapasitas Bateraicr2032', 20000.00, NULL, 'unit', 0, 0, '68c4fc2a05462.jpeg', 'aktif', '2025-09-13 05:07:54', '2025-09-13 05:07:54', NULL, 0),
(139, 'BATERAI-AB-154', 'ABC BIRU BATERAI  AAA 1.5 VOLT 2 PCS', 12, 6, 'barang_it', NULL, 'BATERAI ABC BIRU AAA 1.5 VOLT 2 PCS\r\nHarga per set\r\n1set isi 2 piece', 6000.00, NULL, 'unit', 0, 0, '68c4fd0ed75a1.jpg', 'aktif', '2025-09-13 05:11:42', '2025-09-13 05:11:42', NULL, 0),
(140, 'DOMSEM-BAT-672', 'DOMSEM BATTERY  CR1220 LITHIUM', 12, 6, 'barang_it', NULL, 'DOMSEM BATTERY  CR1220 LITHIUM', 5000.00, NULL, 'unit', 0, 0, '68c4fdddb8fb3.jpg', 'aktif', '2025-09-13 05:15:09', '2025-09-13 05:15:09', NULL, 0),
(141, 'KLIP-KABEL-214', 'klip kabel organizer clip cable isi 20pcs untuk merapihkan kabel', 12, 6, 'barang_it', NULL, 'FITUR :\r\nBAHAN PP KUALITAS WARNA BENING\r\nTANPA PAKU DAN BOR . KABEL SUSUNAN LEBIH RAPI\r\nSUDAH BERIKUT DOUBLE TAPE TEMPEL 20PCS\r\nSERBAGUNA , BISA DIGUNAKAN UNTUK KABEL CHARGER DLL\r\nGAMPANG DIAPLIKASIKAN , HANYA TEMPEL DAN PASANG KABEL.', 6000.00, NULL, 'unit', 0, 0, '68c4fe82b8b01.jpg', 'aktif', '2025-09-13 05:17:54', '2025-09-13 05:17:54', NULL, 0),
(142, '-NYK-USB-3-646', 'NYK USB 3.0 TO VGA CONVERTER', 3, 6, 'barang_it', NULL, 'USB 3.0 To VGA Adapter digunakan untuk menampilkan video (VGA) keluaran dari USB komputer ke layar monitor.\r\nAlat ini memiliki 3 fungsi yang dapat dipilih, yaitu :\r\n1. Menjadikan monitor kedua menampilkan tampilan yang sama dengan monitor utama. (primary)\r\n2. Menjadikan monitor kedua menampilkan tampilan yang merupakan pencerminan dari monitor utama. (mirror)\r\n3. Menjadikan monitor kedua sebagai perluasan dari monitor utama. Lebar Resolusi layar akan menjadi dua kali lipat.(extended)Dengan menggunakan alat ini kita tidak perlu memasangkan 2 buah VGA card ke dalam komputer, yang sering kali bermasalah dengan kompatibilitas hardware-nya. Dengan menggunakan media USB alat ini juga memungkinkan digunakan pada Notebook.', 100000.00, NULL, 'unit', 0, 0, '68c4fee6e87fc.jpg', 'aktif', '2025-09-13 05:19:34', '2025-09-13 05:19:34', NULL, 0),
(143, 'SEAGATE-ON-932', 'Seagate One Touch SSD 1TB USB-C SSD', 1, 6, 'barang_it', NULL, 'Interface: USB Type-C (Kabel C to A include)\r\nTransfer Speed: Up to 1.030MB/s*\r\nKapasitas: 1TB\r\nShock Resistant\r\nInclude Android Backup App\r\nGaransi Resmi 3 Tahun\r\nGaransi Seagate Rescue 3 Tahun', 2500000.00, NULL, 'unit', 0, 0, '68c4ff88bdf61.jpeg', 'aktif', '2025-09-13 05:22:16', '2025-09-13 05:22:16', NULL, 0),
(144, 'HARDISK-CA-049', 'Hardisk caddy for laptop 9mm slim 9 mm tipis SSD HDD Cady Slot', 1, 6, 'barang_it', NULL, 'Slim Ukuran 9mm untuk ukuran 9.5mm dan 12.7mm bisa lihat di sini https://www.tokopedia.com/wepart/ssd-hdd-caddy-slim-9-5mm-12-7mm-sata-dvd-slot-hardisk Mohon diukur tinggi DVD asli anda, lihat foto produk ke 2 cara mengukur tingginya. Cara membedakan lihat foto produk Features - HDD Caddy SATA 9 mm (make sure your DVD is 9.5mm size) - HDD Caddy SATA 12,7mm (make sure your DVD is 9.5mm size) - Universal for ALL Laptop with 9mm DVD Slot - SATA', 30000.00, NULL, 'unit', 0, 0, '68c4ffefd4d17.jpg', 'aktif', '2025-09-13 05:23:59', '2025-09-13 05:23:59', NULL, 0),
(145, 'DESOLDERIN-317', 'Desoldering Pump solder sucker pump besi desoldering machine half aluminium', 3, 6, 'barang_it', NULL, 'desoldering pump solder sucker pump besi desoldering machine half aluminium\r\ndesoldering pump solder sucker pump besi desoldering machine half aluminium\r\ndesoldering pump solder sucker pump besi desoldering machine half aluminium', 15000.00, NULL, 'unit', 0, 0, '68c50245afb47.jpg', 'aktif', '2025-09-13 05:33:57', '2025-09-13 05:33:57', NULL, 0),
(146, 'VIDEO-CAPT-602', 'Video Capture Card HDMI 1080p usb 2.0 video capture 1080p Usb2.0', 1, 6, 'barang_it', NULL, 'Kartu Penangkap Video HDMI 4K 60fps, Penangkap Video HD 1080P USB3.0 Audio untuk Game PS4 Kamera Streaming Langsung rekaman Resolusi Input (HDMI) 3840 × 2160 @ 30Hz, resolusi output (USB) 1920 × 1080 @ 30Hz Dengan mudah menghubungkan DSLR, camcorder, atau kamera aksi Anda ke PC atau Mac Anda Umpan balik waktu nyata, menembak dan menghasilkan dalam alat favorit Anda. Plug and play-tanpa driver, latensi rendah untuk perekaman game, rekaman rapat, streaming langsung. Kompatibel dengan Windows,Mac OS X Cocok untuk akuisisi definisi tinggi, rekaman pengajaran, pencitraan medis, dll. Merekam video langsung ke hard disk tanpa penundaan waktu. Spesifikasi: Resolusi HDMI: input maksimum bisa 3840 × 2160 @ 30Hz Resolusi output Video: output maksimal bisa 1920 × 1080 @ 30Hz Format output Video: YUV/JPEG Mendukung format video: 8/10/12bit warna pekat Mendukung format audio: L-PCM Mendukung AWG26 kabel standar HDMI: Masukan hingga 15 meter Mendukung sebagian besar perangkat lunak akuisisi, seperti VLC / OBS / Amcap, dll Mendukung Windows / Android / MacOS Sesuai dengan Video USB dan standar UVC Sesuai dengan standar UAC Audio USB Arus kerja maksimal: 0, 4A/5V DC Dimensi (P x L x T): 2.5x1.1x0.5 inci Tanpa sumber daya listrik eksternal, ringkas dan portabel. Perangkat Keras Komputer persyaratan konfigurasi: CPU: PC i5-3400 atau lebih tinggi; NB i7-3537U 2.0GHZ atau lebih Kartu grafis: PC NVIDIA GT630 atau lebih tinggi; NVIDIA NC GT735M atau lebih tinggi Jalankan memori: 4G RAM. Paket: 1x HDMI Capture USB 2.0 1x Panduan Pengguna', 70000.00, NULL, 'unit', 0, 0, '68c502ca3829c.jpg', 'aktif', '2025-09-13 05:36:10', '2025-09-13 05:36:10', NULL, 0),
(147, 'DONGLE-HDM-048', 'DONGLE HDMI ANYCAST TV DISPLAY 4K 1080 WIRELESS WIFI', 12, 6, 'barang_it', NULL, 'Brand-New Cool AnyCast Wifi Display TV Dongle, Dual Core 4K HD Display No Need Mode Switching Support Airplay Miracast DLNA\r\nUpgraded Wireless Display: New Powerful 8272 Chipset, no need mode switching between iOS and Android device. Mirror your phone, tablet or PC screen to your TV, projector, any HDMI display wirelessly.\r\nSupport YouTube -- Conforming to the Wi-Fi miracast agreement, airplay mirroring agreement and the DLNA agreement, this dongle can transmit what your device display to the monitor. No need any software driver or APP; No need any cable or adapter from Phone to the dongle. Support 3G/ LTE while using airplay/ miracast over Wi-Fi.\r\nSupport 4K output: 4K HD display, show every details to your large screen, let you enjoy larger and clearer view.\r\nParameters:\r\n1. Chipset: AM8272\r\n2\'\'Support Wifi: Wifi 802.11ac 2.4G\r\n3. Resolution: support 4K output, but can not decode 4K video\r\n4. No need to switch mode from IOS to Android\r\n5.Dual Core, H.265 Decored Ability', 75000.00, NULL, 'unit', 0, 0, '68c5032e5e19c.jpg', 'aktif', '2025-09-13 05:37:50', '2025-09-13 05:37:50', NULL, 0),
(148, 'NYK-KABEL--210', 'NYK Kabel USB Male to Male 50cm - USB2.0 AM/AM - A-A 50cm', 3, 6, 'barang_it', NULL, 'SPESIFIKASI PRODUK\r\n. Merk : NYK\r\n. Tipe : Kabel USB A/M-A/M\r\n. Plug 1 : USB2.0 A/M\r\n. Plug 2 : USB2.0 A/M\r\n. Warna : Biru Transfarant\r\n\r\n. Varian Panjang : 50cm dan 1.5 meter', 20000.00, NULL, 'unit', 0, 0, '68c5226856a1e.jpg', 'aktif', '2025-09-13 07:51:04', '2025-09-13 07:51:04', NULL, 0),
(149, 'KABEL-USB--216', 'Kabel USB Extension 1,5M Hitam Male To Female', 1, 6, 'barang_it', NULL, 'Kabel USB Extension 1,5M Hitam Male To Female', 20000.00, NULL, 'unit', 0, 0, '68c523ca55192.jpg', 'aktif', '2025-09-13 07:56:58', '2025-09-13 07:56:58', NULL, 0),
(150, 'USB-SOUND--751', 'USB SOUND 7.1 CHANNEL / USB SOUND CARD VERSI 7.1 Laptop Audio', 1, 6, 'barang_it', NULL, 'Plug n play, cukup colok USB 2.0 Full-Speed (12Mbps). Kompatibel USB Bus\r\npowered, tidak memerlukan daya eksternal. Konektor: USB Type-A, jack stereo\r\noutput, jack input mikrofon.', 25000.00, NULL, 'unit', 0, 0, '68c5245ac5c36.jpg', 'aktif', '2025-09-13 07:59:22', '2025-09-13 07:59:22', NULL, 0),
(151, '-NYK-CONVE-531', 'NYK Converter kabel Hdmi -Vga', 1, 6, 'barang_it', NULL, 'merk   N Y K  ,  \r\nIni konverter buat dari Laptop/PC komputer/Playstation/Dvd player/Dvr cctv dll yang port nya HDMI   ,\r\nke Layar/monitor/proyektor/TV nya port VGA', 50000.00, NULL, 'unit', 0, 0, '68c524a66ca12.jpg', 'aktif', '2025-09-13 08:00:38', '2025-09-13 08:00:38', NULL, 0),
(152, 'NYK-CONVER-082', 'NYK Converter Kabel Mini HDMI to VGA', 1, 6, 'barang_it', NULL, 'Converter mini hdmi to vga NYK\r\n. Input : mini HDMI male\r\n. Output : VGA female\r\n. Cable length : 20cm\r\n. Colour : White\r\nTidak membutuhkan power dan tanpa Audio Out', 80000.00, NULL, 'unit', 0, 0, '68c52506b9d6c.jpg', 'aktif', '2025-09-13 08:02:14', '2025-09-13 08:02:14', NULL, 0),
(153, 'NYK-CONVER-851', 'NYK  Converter Kabel HDMI display to VGA AUDIO', 1, 6, 'barang_it', NULL, 'Spesifikasi Produk :\r\n- Desain yang fleksibel dan menyadari garis melalui fungsi\r\n- Tidak ada catu daya eksternal\r\n- Plug and Play\r\n- Resolusi Produk bisa sampai 1080p\r\n- Output Product sumber audio untuk 3.5 atau 3.5 soket audio ke R + L\r\n- Input: HDMI\r\n- Output: VGA + Audio', 55000.00, NULL, 'unit', 0, 0, '68c525baf12f6.jpeg', 'aktif', '2025-09-13 08:05:14', '2025-09-13 08:05:14', NULL, 0),
(154, 'NYK-CONVER-101', 'NYK CONVERTER DP TO HDMI', 1, 6, 'barang_it', NULL, 'NYK CONVERTER DP TO HDMI\r\nNYK CONVERTER DP TO HDMI\r\nNYK CONVERTER DP TO HDMI', 50000.00, NULL, 'unit', 0, 0, '68c5264329e2c.jpeg', 'aktif', '2025-09-13 08:07:31', '2025-09-13 08:07:31', NULL, 0),
(155, 'TPLINK-ARC-127', 'TP-Link Archer T2U Nano AC600 Nano Wireless USB Adapter Router - HITAM', 1, 6, 'barang_it', NULL, 'High Speed WiFi– Up to 600Mbps speeds with 200Mbps on 2.4GHz and 433\r\nMbps on 5GHz, upgrades your devices to higher AC WiFi speeds.\r\n\r\nDual Band Wireless – 2.4GHz and 5GHz band for flexible connectivity,\r\nupgrades your devices to work with the latest dual-band WiFi router for\r\nfaster speed and extended range.\r\nNano design – Small, unobtrusive design allows you to plug it in and forget it is even there\r\nOperating System – Supports Windows 10/8.1/8/7/XP, Mac OS X\r\nAdvanced Security –Supports 64/128-bit WEP, WPA/WPA2, and WPA-PSK/WPA2-PSK encryption standards\r\n\r\nUSB 2.0\r\nOmni Directional\r\n5GHz\r\n2.4GHz\r\nSupport 64/128 bit WEP, WPA-PSK/WPA2-PSK, 802.1x', 150000.00, NULL, 'unit', 0, 0, '68c5278adc3eb.jpeg', 'aktif', '2025-09-13 08:12:58', '2025-09-13 08:12:58', NULL, 0),
(156, 'TPLINK-TLW-152', 'TP-Link TL-WN725N - Nano USB Wireless Network Adapter 150Mbps', 1, 6, 'barang_it', NULL, 'FITUR HARDWARE\r\nInterface: USB 2.0\r\nDimensions (W X D X H): 0.73x0.59x0.28in.(18.6x15x7.1mm)\r\nAntenna: Internal antenna\r\nLED: Status\r\nWeight: 0.07 ounces / 2.1 grams (Without packaging)\r\n\r\nFITUR WIRELESS\r\nWireless Standards: IEEE 802.11b, IEEE 802.11g, IEEE 802.11n\r\nFrequency: 2.400-2.4835GHz\r\nTransmit Power: 20dBm\r\nWireless Modes: Ad-Hoc / Infrastructure mode\r\nWireless Security: Supports 64/128 WEP, WPA/WPA2, WPA-PSK/WPA2-PSK (TKIP/AES), supports IEEE 802.1X\r\nModulation Technology: DBPSK, DQPSK, CCK, OFDM, 16-QAM, 64-QAM\r\n\r\nSYSTEM REQUIREMENTS\r\nWindows 8.1/8(32/64bits), Windows 7(32/64bits), Windows Vista(32/64bits),\r\nWindows XP(32/64bits), Mac OS X 10.7~10.10, Linux', 110000.00, NULL, 'unit', 0, 0, '68c527e7b31da.jpg', 'aktif', '2025-09-13 08:14:31', '2025-09-13 08:14:31', NULL, 0),
(157, 'RAPOO-MOUS-392', 'RAPOO MOUSE WIRED N100 BLACK -USB', 1, 6, 'barang_it', NULL, 'Wired Ambidextrous Mouse\r\n\r\nN100\r\nErgonomic design\r\nAmbidextrous design\r\nHigh resolution\r\n1600 DPI sensor\r\n3 buttons including 2D non-slip scroll wheel\r\nNo driver or setup needed', 50000.00, NULL, 'unit', 0, 0, '68c52834b02ec.jpg', 'aktif', '2025-09-13 08:15:48', '2025-09-13 08:15:48', NULL, 0),
(158, 'RJ45-DAN-R-126', 'RJ45 dan RJ11 Network Cable Tester kabel LAN', 1, 6, 'barang_it', NULL, 'RJ45 dan RJ11\r\nNetwork Cable Tester\r\nNSHL468\r\nCPJ20 12T 0318\r\nCepat dan mudah untuk memeriksa kabel jaringan\r\nMemeriksa kabel putus dan short / kongslet, tipe kabel straight through atau crossover.\r\nIdeal untuk penggemar I.T yang ingin membuat kabel jaringan sendiri\r\n3 mode tombol : OFF , ON / quick , Slow\r\nMenggunakan baterai kotak 9V / PP3 tunggal', 50000.00, NULL, 'unit', 0, 0, '68c528fe256e9.jpg', 'aktif', '2025-09-13 08:19:10', '2025-09-13 08:19:10', NULL, 0),
(159, 'RFID-READE-635', 'RFID Reader Dual frequency | Reader card Mifare dan Proximity 2in1 - 8 Digit Dec', 1, 6, 'barang_it', NULL, 'RFID Reader card 2 jenis kartu Dapat membaca jenis kartu Mifare (13.56MHz ) dan Proximity (125KHz).\r\nBisa baca semua kartu (termasuk e-KTP, emoney, flazz, brizzi dsb), tanpa mengurangi saldo.\r\nPenggunaan sangat gampang, tinggal plug and play menggunakan USB ke komputer, udah langsung kedetect.\r\n\r\nMaterial : ABS+Epoxy Size : 96 x 61 x 12 mm\r\nItem : RFID reader\r\nUSB Voltage : 12VDC\r\nStatic Current : less than 50mA\r\nReader speed : less than 0.2sec\r\nSupport : Support Dual Frequency Proximity 125KHz and Mifare 13.56MHz\r\nFrequency:125khz / 13.56mhzJenis pembacaan kartu125khz (EM4100 / EM4101 dll.)\r\nCard Type : EM-ID card,Middle distance ID card,General ID card,Dual frequency card dsb\r\n\r\nDIGIT OUTPUT: 5 digit dec, 13 digit dec, 10 digit dec, 8 digit dec, 10 digit hex, 8 digit hex, 18 digit dec', 250000.00, NULL, 'unit', 0, 0, '68c52999d6045.jpg', 'aktif', '2025-09-13 08:21:45', '2025-09-13 08:21:45', NULL, 0),
(160, 'RFID-READE-112', 'RFID Reader 1 frequency | Reader card Mifare dan Proximity 2in1 - 8 Digit Dec', 3, 6, 'barang_it', NULL, 'Material : ABS+Epoxy Size : 96 x 61 x 12 mm\r\nItem : RFID reader\r\nUSB Voltage : 12VDC\r\nStatic Current : less than 50mA\r\nReader speed : less than 0.2sec\r\nSupport : Support Dual Frequency Proximity 125KHz and Mifare 13.56MHz\r\nFrequency:125khz / 13.56mhzJenis pembacaan kartu125khz (EM4100 / EM4101 dll.)\r\nCard Type : EM-ID card,Middle distance ID card,General ID card,Dual frequency card dsb\r\n\r\nDIGIT OUTPUT: 5 digit dec, 13 digit dec, 10 digit dec, 8 digit dec, 10 digit hex, 8 digit hex, 18 digit dec\r\n\r\n\r\nNanti pas order ada 3 pilihan yaitu:\r\n\r\n+ 8 Digit Dec (Default) = Outputnya adalah 8 Digit Desimal (Jika dari dulu beli di kami, maka standard kami ini)\r\n\r\n+ 10 Digit Dec = Outputnya adalah 10 Digit Desimal. Output digit yg pada umumnya dijual oleh penjual lain (tidak semua)', 250000.00, NULL, 'unit', 0, 0, '68c529e73f3b9.jpg', 'aktif', '2025-09-13 08:23:03', '2025-09-13 08:23:03', NULL, 0),
(161, 'BARRIER-GA-629', 'Barrier Gate Radar Sensor, Palang Parkir Kendaraan / Vehicle Detector', 3, 6, 'barang_it', NULL, 'MAX RANGE : 6 m\r\nPOWER INPUT : 12 VDC\r\nRADIO FREQUENCY: 24 GHz\r\nBarrier Gate Radar Sensor digunakan untuk menggantikan loop detektor.\r\nSensor ini akan memberikan input ke barrier gate controller ketika mendeteksi obyek yang berada didepannya hingga jarak tertentu yang telah di-setting.\r\nInput balik ini bermanfaat untuk mencegah palang parkir menabrak kendaraan yang ada di bawahnya (anti collision radar).\r\nRadar Detector ini dapat ditempatkan pada outdoor dan indoor di bawah palang parkir.', 545000.00, NULL, 'unit', 0, 0, '68c52a9d24f96.jpg', 'aktif', '2025-09-13 08:26:05', '2025-09-13 08:26:05', NULL, 0),
(162, 'POWER-SUPP-945', 'Adaptor | Power Supply 24V 2.5A Switching PSU SMPS LED CCTV CNC Stepper', 3, 6, 'barang_it', 'Power supply switching 24V 2.5A Body Jaring Input : 110-220v AC 50/60Hz Output : 24V DC 2.5Ampere Dimensi : 11 x 7.7x 3.7 cm +Body Metal +Led indikator +VR voltage adjustable cocok untuk -project arduino 24v -cnc controller/motor stepper 24v -project charger battery/Aki -Audio Amplifier -project elektronik lain', 'Power supply switching 24V 2.5A Body Jaring Input : 110-220v AC 50/60Hz Output : 24V DC 2.5Ampere Dimensi : 11 x 7.7x 3.7 cm +Body Metal +Led indikator +VR voltage adjustable cocok untuk -project arduino 24v -cnc controller/motor stepper 24v -project charger battery/Aki -Audio Amplifier -project elektronik lain', 85000.00, NULL, 'unit', 0, 0, '68c52b585847b.jpg', 'aktif', '2025-09-13 08:29:12', '2025-09-13 08:35:11', NULL, 0),
(163, 'SPEAKER-KO-895', 'Speaker Komputer Laptop USB Model:101Z', 1, 6, 'barang_it', NULL, 'Digital Speaker Mini Komputer PC / Laptop USB - USB Plug : 2.0 Channel - Power Output : RMS 3Wx2 - Drive Unit : 2 #34; X 2 - Rated Impedance : 4ohm - Powered Input : 5V USB 2.0 - Plug and play to USB terminal - Panjang Kabel 50-70cm - Colokan Audio Jack 3,5mm - Ada Pengatur Volume - Kualitas Suara Baik - Packing/kemasan bisa berubah sesuai distributor - Kondisi Baru - Dipacking aman pakai bubble. Rental Raafinet KM.12 Palembang Bisa COD Tokopedia', 45000.00, NULL, 'unit', 0, 0, '68c52c0362c5c.jpg', 'aktif', '2025-09-13 08:32:03', '2025-09-13 08:32:03', NULL, 0),
(164, 'KEYBOARD-F-761', 'Keyboard Flexible USB K-One Anti Air', 1, 6, 'barang_it', NULL, 'Spesifikasi Produk :\r\nBrand : K-One\r\nTipe : KB-8006m\r\nJenis : Keyboard Flexible\r\nKoneksi : USB\r\nWarna : -\r\nUkuran : 35 x 13,5 cm\r\nBisa Di gulung , Dicuci dan Mudah Di bawa kemana-mana Cocok untuk PC Komputer atau Laptop', 65000.00, NULL, 'unit', 0, 0, '68c52c8302975.jpeg', 'aktif', '2025-09-13 08:34:11', '2025-09-13 08:34:11', NULL, 0),
(165, 'MTECH-WEBC-985', 'M-Tech Webcam WB350 FullHD 1080P', 1, 6, 'barang_it', NULL, 'Features :\r\n* Resolution FHD 1080P / 30FPS (1920x1080px)\r\n* High resolution COMS color\r\n* Fixed focus camera\r\n* Built-in microphone\r\n* Automatic brightness adjustment\r\n* Support universal tripod - ready clip fits laptops and LCD monitors\r\n* Suitable for video calling, video recording, streaming, conference, etc\r\n* Plug and play with USB connection\r\n\r\n\r\nSpecification :\r\n* Resolution : 1080p/30fps\r\n* Focus Type : Fixed focus\r\n* Lens : Full HD\r\n* Noise Range : 48dB\r\n* Dynamic Range : 72dB\r\n* Interfaced : USB 2.0', 120000.00, NULL, 'unit', 0, 0, '68c52d57db39d.jpg', 'aktif', '2025-09-13 08:37:43', '2025-09-13 08:37:43', NULL, 0),
(166, 'LOGITECH-M-480', 'Logitech Mouse B100 USB Kabel (B M SUPER)', 1, 6, 'barang_it', NULL, 'Spesifikasi:\r\n- Model: Logitech Optical USB Mouse B100\r\n- Interface: Kabel USB\r\n- Buttons: 3\r\n- Technology: Optical Tracking\r\n- Resolution: 800 DPI\r\n- Warna: Hitam\r\n- Cocok untuk: Bisnis & Rumah.\r\n- Kompatibilitas: Windows, MAC, Linus & Chrome OS', 75000.00, NULL, 'unit', 0, 0, '68c52dc2ecb40.jpg', 'aktif', '2025-09-13 08:39:30', '2025-09-13 08:39:30', NULL, 0),
(167, 'MICROPACK--472', 'MicroPack Mouse Wireless MP-716W BLUE', 1, 6, 'barang_it', NULL, '- Model : MP-707B-BK (6970517493654)\r\n- Model : MP-707B-WH (6970517493661)\r\n- Wireless technology : Bluetooth 5.0 amp; Bluetooth 3.0\r\n- Wireless range : Approx 10 meters\r\n- Sensor : Optical sensor\r\n- Resolution : 1200 CPI\r\n- Number of buttons : 4 buttons (Left key, Right key, Scroll, DPI switch)\r\n- Battery type : 1 AAA battery\r\n- Rating voltage / current : 1.5V 15mA\r\n- Dimension : 96.5 x 60 x 30 mm * TIDAK MENGGUNAKAN / MENDAPATKAN RECEIVER', 95000.00, NULL, 'unit', 0, 0, '68c52e9698cfa.jpg', 'aktif', '2025-09-13 08:43:02', '2025-09-13 08:43:02', NULL, 0),
(168, 'RAYDEN-CAT-964', 'Rayden CATV Signal Amplifier ( Booster ) 3 Channel 20dB', 1, 6, 'barang_it', NULL, 'CATV Signal Amplifier ( Booster ) 3 Channel 20dB Rayden\r\nBandwidth : 45-860 & 1000 MHz\r\nGain : 20dB', 70000.00, NULL, 'unit', 0, 0, '68c52f9c141a5.jpg', 'aktif', '2025-09-13 08:47:24', '2025-09-13 08:47:24', NULL, 0),
(169, 'ZKTECO-LX5-760', 'ZKTeco LX50 Mesin Absensi Sidik Jari Biometrik', 12, 6, 'barang_it', NULL, 'Spesifikasi Teknis :\r\n• Kapasitas Sidik Jari : 500 template (sudah di upgrade jd 1000)\r\n• Kapasitas Transaksi : 50.000 transaksi\r\n• Kapasitas Kartu RFID : 500 kartu\r\n• Sensor : ZK Sensor Optik\r\n• Versi Algoritma : ZK Finger v10.0\r\n• Built-in Card Reader : pembaca jarak dekat 125 kHz\r\n• Komunikasi : USB-host\r\n• Power Supply : 5V DC 1A\r\n• Suhu Operasional : 0 ° C – 45 ° C\r\n• Kelembaban Pengoperasian : 20% – 80%\r\n• Dimensi : 180 X 132 X 32mm', 700000.00, NULL, 'unit', 0, 0, '68c530bb646eb.jpeg', 'aktif', '2025-09-13 08:52:11', '2025-09-13 08:52:11', NULL, 0),
(170, 'MOUSEPAD-P-588', 'MOUSEPAD POLOS ANTI SLIP MOUSE PAD', 1, 6, 'barang_it', NULL, 'MOUSEPAD POLOS ANTI SLIP MOUSE PAD', 10000.00, NULL, 'unit', 0, 0, '68c5315183578.jpeg', 'aktif', '2025-09-13 08:54:41', '2025-09-13 08:54:41', NULL, 0),
(171, 'MOUSEPAD-G-517', 'MousePad Gaming XL Polos Hitam Mouse Pad Desk Mat Alas Mouse Besar', 1, 6, 'barang_it', NULL, 'MousePad Gaming XL Polos Hitam Mouse Pad Desk Mat Alas Mouse Besar - 300x600mm\r\nMousePad Gaming XL Polos Hitam Mouse Pad Desk Mat Alas Mouse Besar - 300x600mm\r\nMousePad Gaming XL Polos Hitam Mouse Pad Desk Mat Alas Mouse Besar - 300x600mm', 30000.00, NULL, 'unit', 0, 0, '68c53198580ac.jpg', 'aktif', '2025-09-13 08:55:52', '2025-09-13 08:55:52', NULL, 0),
(172, 'YOSHIMITSU-319', 'YOSHIMITSU TITANIUM SERIES SPINDEL DVD-R 16X  ISI 50 KEPING', 1, 6, 'barang_it', NULL, 'TIPE CAKRAM : DVD-R\r\nMEREK : YOSHIMITSU\r\nSPEED : 1 - 16X\r\nKAPASITAS : 4.7GB / 120MIN\r\nISI : 50 KEPING\r\nMADE IN TAIWAN', 3000.00, NULL, 'unit', 0, 0, '68c532356fafe.jpeg', 'aktif', '2025-09-13 08:58:29', '2025-09-13 08:58:29', NULL, 0),
(173, 'USB-18-MET-865', 'Wireless Remote Cursor Control PC Laptop Computer USB 18 Meter', 1, 6, 'barang_it', NULL, '- USB interface\r\n- Silver Buttons: Power, E-mail, Internet, Move Windows, Close Windows, Switch Windows, Hotkey A/B/C/D, Mouse, Mouse Left/Right Key, Previous, Next, Play/Pause, Backward, Forward, Stop, Vol+, Vol-, Page Up/Down, Mute, My PC, Backspace, Tab Key, Up/Down/Left/Right arrow, Enter, Open, Start, Esc, Numlock, Desktop\r\n- Power: 2 x baterai AAA\r\n- panjang kabel receiver: 120cm\r\n- ukuran Remote: 2.8cm x 18.5cm x 5.1cm', 175000.00, NULL, 'unit', 0, 0, '68c533f1c3876.jpg', 'aktif', '2025-09-13 09:05:53', '2025-09-13 09:05:53', NULL, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `programmer_replies`
--

CREATE TABLE `programmer_replies` (
  `id` int(11) NOT NULL,
  `admin_message_id` int(11) NOT NULL,
  `programmer_id` int(11) NOT NULL,
  `reply` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `programmer_replies`
--

INSERT INTO `programmer_replies` (`id`, `admin_message_id`, `programmer_id`, `reply`, `created_at`, `updated_at`) VALUES
(1, 1, 11, 'test balasan', '2025-09-11 19:43:26', '2025-09-11 19:43:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `saldo_bank`
--

CREATE TABLE `saldo_bank` (
  `id` int(11) NOT NULL,
  `bank_id` int(11) NOT NULL,
  `saldo_awal` decimal(15,2) DEFAULT 0.00 COMMENT 'Saldo awal periode',
  `saldo_masuk` decimal(15,2) DEFAULT 0.00 COMMENT 'Total pemasukan',
  `saldo_keluar` decimal(15,2) DEFAULT 0.00 COMMENT 'Total pengeluaran',
  `saldo_akhir` decimal(15,2) DEFAULT 0.00 COMMENT 'Saldo akhir (awal + masuk - keluar)',
  `periode_bulan` int(11) NOT NULL COMMENT 'Bulan periode (1-12)',
  `periode_tahun` int(11) NOT NULL COMMENT 'Tahun periode',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `saldo_bank`
--

INSERT INTO `saldo_bank` (`id`, `bank_id`, `saldo_awal`, `saldo_masuk`, `saldo_keluar`, `saldo_akhir`, `periode_bulan`, `periode_tahun`, `created_at`, `updated_at`) VALUES
(1, 1, 50000000.00, 0.00, 13095000.00, 36905000.00, 9, 2025, '2025-09-09 06:11:27', '2025-09-11 02:37:35'),
(2, 2, 50000000.00, 0.00, 30846245.00, 19153755.00, 9, 2025, '2025-09-09 06:11:27', '2025-09-10 07:24:04'),
(3, 3, 100000000.00, 0.00, 12422000.00, 87578000.00, 9, 2025, '2025-09-09 06:11:27', '2025-09-10 06:06:13'),
(4, 4, 10000000.00, 0.00, 25376000.00, -15376000.00, 9, 2025, '2025-09-09 06:11:27', '2025-09-10 07:25:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_movement`
--

CREATE TABLE `stock_movement` (
  `id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL COMMENT 'in=masuk, out=keluar, adjustment=penyesuaian',
  `quantity` int(11) NOT NULL COMMENT 'Jumlah pergerakan (positif untuk in, negatif untuk out)',
  `reference_type` varchar(50) NOT NULL COMMENT 'Jenis referensi: pembelian, penjualan, penerimaan, transaksi, opname, manual',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID referensi dari tabel terkait',
  `stok_sebelum` int(11) NOT NULL DEFAULT 0 COMMENT 'Stok sebelum pergerakan',
  `stok_sesudah` int(11) NOT NULL DEFAULT 0 COMMENT 'Stok setelah pergerakan',
  `harga_satuan` decimal(15,2) DEFAULT NULL COMMENT 'Harga satuan saat pergerakan',
  `total_nilai` decimal(15,2) DEFAULT NULL COMMENT 'Total nilai pergerakan',
  `keterangan` text DEFAULT NULL COMMENT 'Keterangan tambahan',
  `user_id` int(11) NOT NULL COMMENT 'User yang melakukan pergerakan',
  `tanggal_movement` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk tracking pergerakan stok produk';

--
-- Dumping data untuk tabel `stock_movement`
--

INSERT INTO `stock_movement` (`id`, `produk_id`, `type`, `quantity`, `reference_type`, `reference_id`, `stok_sebelum`, `stok_sesudah`, `harga_satuan`, `total_nilai`, `keterangan`, `user_id`, `tanggal_movement`, `created_at`, `updated_at`) VALUES
(1, 1, 'in', 100, 'manual', NULL, 0, 100, 50000.00, 5000000.00, 'Stok awal sistem', 1, '2025-09-10 09:09:39', '2025-09-10 02:09:39', '2025-09-10 02:09:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_opname`
--

CREATE TABLE `stock_opname` (
  `id` int(11) NOT NULL COMMENT 'ID unik stock opname',
  `produk_id` int(11) NOT NULL COMMENT 'ID produk yang di-opname',
  `user_id` int(11) NOT NULL COMMENT 'ID user yang melakukan opname',
  `tanggal_opname` datetime NOT NULL COMMENT 'Tanggal dan waktu stock opname',
  `stok_sistem` int(11) NOT NULL DEFAULT 0 COMMENT 'Stok menurut sistem saat opname',
  `stok_fisik` int(11) NOT NULL DEFAULT 0 COMMENT 'Stok fisik yang ditemukan',
  `selisih` int(11) NOT NULL DEFAULT 0 COMMENT 'Selisih antara stok fisik dan sistem (fisik - sistem)',
  `keterangan` text DEFAULT NULL COMMENT 'Keterangan tambahan untuk stock opname',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu record dibuat',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu record terakhir diupdate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk mencatat riwayat stock opname produk';

--
-- Dumping data untuk tabel `stock_opname`
--

INSERT INTO `stock_opname` (`id`, `produk_id`, `user_id`, `tanggal_opname`, `stok_sistem`, `stok_fisik`, `selisih`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 10, 1, '2025-09-09 16:06:38', 20, 25, 5, '', '2025-09-09 09:06:38', '2025-09-09 09:06:38'),
(2, 10, 1, '2025-09-09 16:16:07', 25, 30, 5, '', '2025-09-09 09:16:07', '2025-09-09 09:16:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tiket_support`
--

CREATE TABLE `tiket_support` (
  `id` int(11) NOT NULL,
  `nomor_tiket` varchar(20) NOT NULL,
  `desa_id` int(11) NOT NULL,
  `teknisi_id` int(11) DEFAULT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text NOT NULL,
  `prioritas` enum('rendah','sedang','tinggi') DEFAULT 'sedang',
  `status` enum('baru','diproses','selesai','ditutup') DEFAULT 'baru',
  `tanggal_selesai` date DEFAULT NULL,
  `catatan_teknisi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `nomor_invoice` varchar(20) NOT NULL,
  `desa_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `jenis_transaksi` enum('barang','layanan','campuran') NOT NULL,
  `metode_pembayaran` enum('tunai','dp_pelunasan','tempo') NOT NULL,
  `bank_id` int(11) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `dp_amount` decimal(15,2) DEFAULT 0.00,
  `sisa_amount` decimal(15,2) DEFAULT 0.00,
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `status_transaksi` enum('draft','diproses','dikirim','selesai') DEFAULT 'draft',
  `status_pembayaran` enum('belum_bayar','dp','lunas') DEFAULT 'belum_bayar',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `nomor_invoice`, `desa_id`, `user_id`, `tanggal_transaksi`, `jenis_transaksi`, `metode_pembayaran`, `bank_id`, `total_amount`, `dp_amount`, `sisa_amount`, `tanggal_jatuh_tempo`, `status_transaksi`, `status_pembayaran`, `catatan`, `created_at`, `updated_at`) VALUES
(12, ' \'INV/2025/08/0270', 196, 1, '2025-08-25', '', '', 1, 55000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'edit', '2025-08-25 07:10:06', '2025-09-11 22:15:28'),
(13, ' \'INV/2025/08/8429', 6, 1, '2025-08-26', '', 'tunai', 1, 15000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' ', '2025-08-26 01:18:43', '2025-09-11 22:15:28'),
(15, ' \'INV-20250827-001', 4, 1, '2025-08-27', '', '', 1, 2610000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'kirim', '2025-08-27 16:18:12', '2025-09-11 22:15:28'),
(17, ' \'INV/2025/08/6004', 188, 1, '2025-08-29', '', 'tunai', 1, 8500000.00, 0.00, 0.00, '0000-00-00', 'draft', '', ' ', '2025-08-29 02:57:00', '2025-09-11 22:14:44'),
(18, ' \'INV-20250908-001', 146, 1, '2025-09-08', '', '', 1, 10904000.00, 0.00, 0.00, '0000-00-00', 'draft', '', ' ', '2025-09-08 05:14:58', '2025-09-11 22:15:28'),
(19, ' \'INV-20250908-002', 146, 1, '2025-09-08', '', 'tunai', 1, 3920000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 05:30:58', '2025-09-11 22:13:44'),
(20, ' \'INV-20250908-003', 24, 1, '2025-09-08', '', '', 1, 655000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 05:40:39', '2025-09-11 22:15:29'),
(21, ' \'INV-20250908-004', 24, 1, '2025-09-08', '', '', 1, 81506000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 05:46:57', '2025-09-11 22:15:28'),
(22, ' \'INV-20250908-005', 106, 1, '2025-09-08', '', 'tunai', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 05:53:27', '2025-09-11 22:12:34'),
(23, ' \'INV-20250908-006', 107, 1, '2025-09-08', '', 'tunai', 1, 9819000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 05:55:46', '2025-09-11 22:12:34'),
(24, ' \'INV-20250908-007', 24, 1, '2025-09-08', '', '', 1, 2000000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 05:58:40', '2025-09-11 22:15:28'),
(25, ' \'INV-20250908-008', 19, 1, '2025-09-08', '', '', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:01:03', '2025-09-11 22:15:29'),
(27, ' \'INV-20250908-009', 44, 1, '2025-09-08', '', '', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:09:01', '2025-09-11 22:15:28'),
(28, ' \'INV-20250908-010', 91, 1, '2025-09-08', '', '', 1, 10270000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:12:15', '2025-09-11 22:15:29'),
(29, ' \'INV-20250908-011', 109, 1, '2025-09-08', '', 'tunai', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:16:13', '2025-09-11 22:14:44'),
(30, ' \'INV-20250908-012', 116, 1, '2025-09-08', '', '', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:18:45', '2025-09-11 22:15:28'),
(31, ' \'INV-20250908-013', 110, 1, '2025-09-08', '', 'tunai', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:21:19', '2025-09-11 22:15:28'),
(32, ' \'INV-20250908-014', 112, 1, '2025-09-08', '', 'tunai', 1, 1000000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:29:01', '2025-09-11 22:12:34'),
(33, ' \'INV-20250908-015', 112, 1, '2025-09-08', '', '', 1, 8820000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:30:21', '2025-09-11 22:15:28'),
(34, ' \'INV-20250908-016', 25, 1, '2025-09-08', '', 'tunai', 1, 3511000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:38:38', '2025-09-11 22:15:28'),
(35, ' \'INV-20250908-017', 60, 1, '2025-09-08', '', '', 1, 9983000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:46:56', '2025-09-11 22:15:29'),
(36, ' \'INV-20250908-018', 116, 1, '2025-09-08', '', '', 1, 9674000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:57:36', '2025-09-11 22:15:28'),
(37, ' \'INV-20250908-019', 41, 1, '2025-09-08', '', 'tunai', 1, 12740000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 06:59:03', '2025-09-11 22:12:34'),
(38, ' \'INV-20250908-020', 73, 1, '2025-09-08', '', 'tunai', 1, 6211000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 07:01:52', '2025-09-11 22:15:28'),
(39, ' \'INV-20250908-021', 89, 1, '2025-09-08', '', '', 1, 11711000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 07:04:54', '2025-09-11 22:15:28'),
(40, ' \'INV-20250908-022', 113, 1, '2025-09-08', '', '', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 07:05:57', '2025-09-11 22:15:29'),
(41, ' \'INV-20250908-023', 117, 1, '2025-09-08', '', 'tunai', 1, 9800000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-08 07:08:45', '2025-09-11 22:15:28'),
(42, ' \'INV-20250909-001', 95, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 01:46:27', '2025-09-11 22:15:28'),
(43, ' \'INV-20250909-002', 144, 1, '2025-09-09', '', 'tunai', 1, 4650000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 01:50:24', '2025-09-11 22:09:54'),
(45, ' \'INV-20250909-004', 86, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 01:55:25', '2025-09-11 22:15:28'),
(46, ' \'INV-20250909-005', 82, 1, '2025-09-09', '', 'tunai', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:01:46', '2025-09-11 22:09:54'),
(48, ' \'INV-20250909-007', 84, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:06:11', '2025-09-11 22:15:28'),
(49, ' \'INV-20250909-008', 85, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:11:59', '2025-09-11 22:15:29'),
(50, ' \'INV-20250909-009', 35, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:13:40', '2025-09-11 22:15:29'),
(51, ' \'INV-20250909-010', 41, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:15:04', '2025-09-11 22:15:28'),
(52, ' \'INV-20250909-011', 88, 1, '2025-09-09', '', 'tunai', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:18:00', '2025-09-11 22:14:44'),
(53, ' \'INV-20250909-012', 89, 1, '2025-09-09', '', 'tunai', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' ', '2025-09-09 02:20:03', '2025-09-11 22:09:54'),
(54, ' \'INV-20250909-013', 90, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:21:00', '2025-09-11 22:15:28'),
(55, ' \'INV-20250909-014', 146, 1, '2025-09-09', '', '', 1, 8368000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:24:58', '2025-09-11 22:15:29'),
(56, ' \'INV-20250909-015', 92, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:36:18', '2025-09-11 22:15:29'),
(57, ' \'INV-20250909-016', 196, 1, '2025-09-09', '', '', 1, 7000000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:40:16', '2025-09-12 02:22:19'),
(58, ' \'INV-20250909-017', 196, 1, '2025-09-09', '', 'tunai', 1, 9000000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:42:28', '2025-09-12 02:22:19'),
(59, ' \'INV-20250909-018', 196, 1, '2025-09-09', '', 'tunai', 1, 11858000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:44:36', '2025-09-12 02:22:19'),
(60, ' \'INV-20250909-019', 196, 1, '2025-09-09', '', '', 1, 6201000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:47:03', '2025-09-12 02:22:19'),
(61, ' \'INV-20250909-020', 196, 1, '2025-09-09', '', 'tunai', 1, 4162000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:48:52', '2025-09-12 02:22:19'),
(62, ' \'INV-20250909-021', 93, 1, '2025-09-09', '', 'tunai', 1, 7000000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:51:13', '2025-09-11 22:13:44'),
(63, ' \'INV-20250909-022', 94, 1, '2025-09-09', '', '', 1, 6860000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 02:52:29', '2025-09-11 22:15:28'),
(64, ' \'INV-20250909-023', 146, 1, '2025-09-09', '', 'tunai', 1, 6871000.00, 0.00, 0.00, '0000-00-00', 'selesai', '', ' \'Tunai', '2025-09-09 03:09:15', '2025-09-11 22:13:44'),
(65, 'INV/2025/09/5762', 196, 1, '2025-09-12', 'barang', '', NULL, 10000.00, 0.00, 0.00, NULL, 'draft', '', 'test pembelian', '2025-09-12 02:43:52', '2025-09-12 02:43:52'),
(66, 'INV/2025/09/6068', 196, 1, '2025-09-12', 'barang', '', NULL, 150000.00, 0.00, 0.00, NULL, 'draft', '', 'test hutang', '2025-09-12 02:44:34', '2025-09-12 02:44:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_detail`
--

CREATE TABLE `transaksi_detail` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `layanan_id` int(11) DEFAULT NULL,
  `nama_item` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `transaksi_detail`
--

INSERT INTO `transaksi_detail` (`id`, `transaksi_id`, `produk_id`, `layanan_id`, `nama_item`, `quantity`, `harga_satuan`, `subtotal`, `catatan`) VALUES
(12, 13, 9, NULL, 'Pulpen Pilot', 3, 5000.00, 15000.00, NULL),
(13, 12, 8, NULL, 'Kertas A4 80gsm', 1, 55000.00, 55000.00, NULL),
(16, 15, 8, NULL, 'Kertas A4 80gsm', 2, 55000.00, 110000.00, NULL),
(17, 15, 4, NULL, 'Printer HP LaserJet', 1, 2500000.00, 2500000.00, NULL),
(20, 17, 3, NULL, 'Laptop Asus VivoBook 14', 1, 8500000.00, 8500000.00, NULL),
(21, 18, 14, NULL, 'paket cctv', 1, 10904000.00, 10904000.00, NULL),
(22, 19, NULL, 26, 'Pembuatan Aplikasi Pertanahan Web', 1, 3920000.00, 3920000.00, NULL),
(23, 20, NULL, 27, 'Domain', 1, 55000.00, 55000.00, NULL),
(24, 20, NULL, 28, 'Hosting', 1, 600000.00, 600000.00, NULL),
(26, 22, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(27, 23, NULL, 30, 'SID', 1, 9819000.00, 9819000.00, NULL),
(28, 24, NULL, 31, 'Update & Maintanance', 1, 2000000.00, 2000000.00, NULL),
(29, 25, NULL, 32, 'Website Desa', 1, 9800000.00, 9800000.00, NULL),
(31, 27, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(32, 28, NULL, 34, 'SID', 1, 10270000.00, 10270000.00, NULL),
(33, 29, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(34, 30, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(35, 31, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(36, 32, NULL, 35, 'Pengembangan SID', 1, 1000000.00, 1000000.00, NULL),
(37, 33, NULL, 36, 'Pengembangan SID', 1, 8820000.00, 8820000.00, NULL),
(40, 36, NULL, 37, 'SID', 1, 9674000.00, 9674000.00, NULL),
(41, 37, NULL, 38, 'SID', 1, 12740000.00, 12740000.00, NULL),
(43, 39, NULL, 39, 'SID', 1, 11711000.00, 11711000.00, NULL),
(44, 40, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(45, 41, NULL, 29, 'SID', 1, 9800000.00, 9800000.00, NULL),
(46, 42, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(49, 45, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(50, 46, NULL, 43, 'Paket Desa Jembangan', 1, 6860000.00, 6860000.00, NULL),
(52, 48, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(53, 49, NULL, 45, 'Pelatihan SID & DESAKTI', 1, 6860000.00, 6860000.00, NULL),
(54, 50, NULL, 45, 'Pelatihan SID & DESAKTI', 1, 6860000.00, 6860000.00, NULL),
(55, 51, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(56, 52, NULL, 46, 'Pengembangan SID', 1, 6860000.00, 6860000.00, NULL),
(57, 53, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(58, 54, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(59, 55, NULL, 47, 'Sewa Alat Kesenian', 1, 8368000.00, 8368000.00, NULL),
(60, 56, NULL, 45, 'Pelatihan SID & DESAKTI', 1, 6860000.00, 6860000.00, NULL),
(66, 62, NULL, 48, 'Iuran BIMTEK DESAKTI', 1, 7000000.00, 7000000.00, NULL),
(67, 63, NULL, 41, 'Kegiatan Pelatihan SID', 1, 6860000.00, 6860000.00, NULL),
(68, 64, NULL, 49, 'Paket Desa Prendengan', 1, 6871000.00, 6871000.00, NULL),
(69, 21, NULL, NULL, 'Paket Layanan Premium', 1, 81506000.00, 81506000.00, NULL),
(70, 34, NULL, NULL, 'Layanan Administrasi', 1, 3511000.00, 3511000.00, NULL),
(71, 35, NULL, NULL, 'Paket Layanan Standar', 1, 9983000.00, 9983000.00, NULL),
(72, 38, NULL, NULL, 'Paket Layanan Standar', 1, 6211000.00, 6211000.00, NULL),
(73, 43, NULL, NULL, 'Layanan Administrasi', 1, 4650000.00, 4650000.00, NULL),
(74, 57, NULL, NULL, 'Paket Layanan Standar', 1, 7000000.00, 7000000.00, NULL),
(75, 58, NULL, NULL, 'Paket Layanan Standar', 1, 9000000.00, 9000000.00, NULL),
(76, 59, NULL, NULL, 'Paket Layanan Premium', 1, 11858000.00, 11858000.00, NULL),
(77, 60, NULL, NULL, 'Paket Layanan Standar', 1, 6201000.00, 6201000.00, NULL),
(78, 61, NULL, NULL, 'Layanan Administrasi', 1, 4162000.00, 4162000.00, NULL),
(84, 65, 9, NULL, 'Pulpen Pilot', 2, 5000.00, 10000.00, NULL),
(85, 66, 7, NULL, 'Logitech Mouse Wireless', 1, 150000.00, 150000.00, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','akunting','supervisor','teknisi','programmer','sales','finance') NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `nama_lengkap`, `role`, `no_hp`, `foto_profil`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'admin@clasnet.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Arif Susilo', 'admin', '09898989000', 'img/profiles/profile_1_1757613806.png', 'aktif', '2025-08-23 13:30:13', '2025-09-13 08:48:56', '2025-09-13 08:48:56'),
(3, 'fika', 'fika@clasnet.id', '$2y$10$64ZSF/RfZBZL4o31kajjLeT9DG.xTSrWrjtfm3Il7bC4gTK9QjyMq', 'Fika Saraswati', 'akunting', '', 'user_3_1756426479.jpeg', 'aktif', '2025-08-24 13:38:29', '2025-09-11 21:07:15', '2025-09-11 21:07:15'),
(4, 'windy', 'windy@clasnet.id', '$2y$10$APgZ7vMCIbYVuEKfxVYWA.E0xNKYAgm9b2bATSSFpfM02t07OlBuC', 'Windy Suprobo', 'admin', '', 'img/profiles/profile_4_1757651193.jpg', 'aktif', '2025-08-24 13:39:25', '2025-09-13 02:39:04', '2025-09-13 02:39:04'),
(5, 'taqim', 'taqim@gmail.com', '$2y$10$we4Qi5cz6Ni8xIYK2bTvLewHeGRZPzeT3lidymWDhGitPBHnE5Nd.', 'Ilham Mustaqim', 'teknisi', '', 'user_5_1756426495.jpeg', 'aktif', '2025-08-24 23:09:57', '2025-08-29 00:14:55', NULL),
(6, 'rangga', 'rangga@jjjj.id', '$2y$10$ue8t0lifHqZWu.V7PfzAaejh1x06lt8GZeOVg7oixoXPYa/MqsWrG', 'Rangga Buana', 'teknisi', '', 'user_6_1757615355.png', 'aktif', '2025-08-24 23:31:25', '2025-09-11 18:29:15', NULL),
(10, 'teguh', 'teguh@gmail.com', '$2y$10$2WNK.N4Jqv9EdOLGbGSb/OB6UNaKlqCFRcOtprQd1aa7.TH4iRpUK', 'Teguh Giana', 'admin', '', 'user_10_1756426544.jpeg', 'aktif', '2025-08-27 23:22:19', '2025-08-29 00:15:44', NULL),
(11, 'nadia', 'nadia@gmail.com', '$2y$10$f8O8FM5rqFrIp65iRvHqQ.Dj9Yc/7W3Ubva1.VxnZ5zYRlRTviBMa', 'Nadia Salsabila', 'programmer', '', 'img/profiles/profile_11_1757613853.png', 'aktif', '2025-08-27 23:36:16', '2025-09-13 07:12:11', '2025-09-13 07:12:11'),
(12, 'denysha', 'denysha@gmail.com', '$2y$10$uc6W3aFPCS.aIi0TTSsjre5EsSFASafSX0pdwFgGGUITkFzJue/ru', 'Denysha', 'programmer', '', 'img/profiles/profile_12_1757622728.png', 'aktif', '2025-08-27 23:37:11', '2025-09-11 20:32:08', '2025-09-11 20:31:40'),
(13, 'tania', 'tania@clasnet.id', '$2y$10$88T/bkMxVQlfzxHxVTDgluvx3QVtDlaOml7umZ4tjEAHPZqLoaIGW', 'Tania', 'akunting', NULL, NULL, 'aktif', '2025-09-09 06:33:17', '2025-09-09 06:33:17', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `vendor`
--

CREATE TABLE `vendor` (
  `id` int(11) NOT NULL,
  `kode_vendor` varchar(20) NOT NULL,
  `nama_vendor` varchar(100) NOT NULL,
  `nama_perusahaan` varchar(150) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL,
  `provinsi` varchar(50) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `nama_kontak` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `jenis_vendor` enum('supplier','distributor','manufacturer','reseller') DEFAULT 'supplier',
  `kategori_produk` varchar(100) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `vendor`
--

INSERT INTO `vendor` (`id`, `kode_vendor`, `nama_vendor`, `nama_perusahaan`, `alamat`, `kota`, `provinsi`, `kode_pos`, `nama_kontak`, `no_hp`, `email`, `website`, `jenis_vendor`, `kategori_produk`, `rating`, `status`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 'GEN001', 'Generic', 'Generic Vendor', 'Yogyakarta saja', 'Yogyakarta', 'Yogyakarta', '11200', 'Generic Man', '098766543211', 'rafasya.shifan@gmail.com', '', 'supplier', 'Printer', 4.00, 'aktif', 'tidak ada', '2025-09-09 03:25:38', '2025-09-09 05:48:34'),
(2, 'VEN002', 'Toko Server', 'Tokopedia', 'Mangga Dua Jakarta', 'Jakarta', 'Jakarta', '1000', 'Admin Toko Server', '098765555555', 'toko@server.com', 'https://tokopedia.com/tokoserver', 'reseller', 'Server', 3.00, 'aktif', '', '2025-09-09 06:13:48', '2025-09-09 06:13:48'),
(3, 'VEN003', 'J-Media', 'J-Media', 'Jl. Menteri Supeno No. 9', 'Yogyakarta', '', '', 'Admin JMedia', '02745012999', '', '', 'supplier', 'CCTV', 3.00, 'aktif', '', '2025-09-09 09:55:45', '2025-09-09 09:57:45'),
(4, 'VEN004', 'PLATINUM PWT', 'PLATINUM', '', '', '', '', '', '', '', '', 'distributor', 'MEMORY', 0.00, 'aktif', '', '2025-09-11 23:14:15', '2025-09-11 23:14:15'),
(5, 'VEN005', 'ANANDAM YGY', '', '', '', '', '', '', '', '', '', 'supplier', 'KOMPUTER', 0.00, 'aktif', '', '2025-09-11 23:14:56', '2025-09-11 23:14:56'),
(6, 'VEN006', 'TOKOPEDIA', '', '', '', '', '', '', '', '', '', 'reseller', 'SEMUA ADA', 0.00, 'aktif', '', '2025-09-11 23:15:50', '2025-09-11 23:15:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `website_desa`
--

CREATE TABLE `website_desa` (
  `id` int(11) NOT NULL,
  `desa_id` int(11) DEFAULT NULL,
  `website_url` varchar(255) NOT NULL,
  `has_database` enum('ada','tidak_ada') DEFAULT 'tidak_ada',
  `news_active` enum('aktif','tidak_aktif') DEFAULT 'tidak_aktif',
  `developer_type` enum('clasnet','bukan_clasnet') DEFAULT 'bukan_clasnet',
  `opendata_sync` enum('sinkron','proses','tidak_sinkron') DEFAULT 'tidak_sinkron',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `website_desa`
--

INSERT INTO `website_desa` (`id`, `desa_id`, `website_url`, `has_database`, `news_active`, `developer_type`, `opendata_sync`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 29, 'https://binorong.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Binorong', '2025-08-25 04:41:10', '2025-08-25 04:41:10'),
(2, 31, 'https://blambangan.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Blambangan', '2025-08-25 04:41:10', '2025-08-25 04:41:10'),
(3, 52, 'https://gemuruh-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Gemuruh', '2025-08-25 04:41:11', '2025-08-25 04:59:02'),
(4, 53, 'https://gembongan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Gembongan', '2025-08-25 04:41:11', '2025-08-25 04:59:02'),
(5, 54, 'https://panawaren.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Panawaren', '2025-08-25 04:41:11', '2025-08-25 04:59:02'),
(6, 55, 'https://prigi-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Prigi', '2025-08-25 04:41:11', '2025-08-25 04:59:03'),
(7, 56, 'https://www.pringamba-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pringamba', '2025-08-25 04:41:11', '2025-08-25 04:59:03'),
(8, 57, 'https://wanacipta-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Wanacipta', '2025-08-25 04:41:11', '2025-08-25 04:59:03'),
(9, 16, 'https://banjarkulon-banjarmangu.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Banjarkulon', '2025-08-25 04:41:11', '2025-08-25 04:41:11'),
(10, 17, 'https://banjarmangu-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Banjarmangu', '2025-08-25 04:41:11', '2025-08-25 04:41:11'),
(11, 24, 'https://beji-banjarnegara.desa.id', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Beji', '2025-08-25 04:41:11', '2025-08-25 04:41:11'),
(12, 58, 'https://gripit-banjarnegara.desa.id', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Gripit', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(13, 59, 'https://jenggawur-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Jenggawur', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(14, 60, 'https://kalilunjar-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalilunjar', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(15, 143, 'https://kendaga.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kendaga', '2025-08-25 04:41:12', '2025-08-25 05:03:33'),
(16, 144, 'https://kesenet-banjarmangu.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Kesenet', '2025-08-25 04:41:12', '2025-08-25 05:03:39'),
(17, 140, 'https://majatengah-banjarmangu.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Majatengah', '2025-08-25 04:41:12', '2025-08-25 05:03:45'),
(18, 145, 'https://paseh.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Paseh', '2025-08-25 04:41:12', '2025-08-25 05:03:51'),
(19, 61, 'https://pekandangan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pekandangan', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(20, 146, 'https://prendengan-banjarmangu.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Prendengan', '2025-08-25 04:41:12', '2025-08-25 05:03:57'),
(21, 147, 'https://rejasari-banjarmangu.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Rejasari', '2025-08-25 04:41:12', '2025-08-25 05:04:05'),
(22, 148, 'https://sigeblog.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sigeblog', '2025-08-25 04:41:12', '2025-08-25 05:04:11'),
(23, NULL, 'https://sijenggung-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sijenggung', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(24, 63, 'https://sijeruk-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sijeruk', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(25, 64, 'https://sipedang-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sipedang', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(26, 20, 'https://bantarwaru-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Bantarwaru', '2025-08-25 04:41:12', '2025-08-25 04:41:12'),
(27, 33, 'https://blitar-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Blitar', '2025-08-25 04:41:12', '2025-08-25 04:41:12'),
(28, 39, 'https://clapar-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Clapar', '2025-08-25 04:41:12', '2025-08-25 04:41:12'),
(29, 44, 'https://dawuhan-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Dawuhan', '2025-08-25 04:41:12', '2025-08-25 04:41:12'),
(30, 149, 'https://gununggiana-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Gununggiana', '2025-08-25 04:41:12', '2025-08-25 05:04:18'),
(31, 150, 'https://kaliurip-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Kaliurip', '2025-08-25 04:41:12', '2025-08-25 05:04:32'),
(32, 73, 'https://karanganyar-madukara.sistemdata.id', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Karanganyar', '2025-08-25 04:41:12', '2025-08-25 05:04:38'),
(33, 65, 'https://kutayasa-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kutayasa', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(34, 66, 'https://limbangan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Limbangan', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(35, 67, 'https://madukara-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Madukara', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(36, 151, 'https://pagelak-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Pagelak', '2025-08-25 04:41:12', '2025-08-25 05:04:43'),
(37, 152, 'https://pakelen.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pakelen', '2025-08-25 04:41:12', '2025-08-25 05:04:52'),
(38, 68, 'https://pekauman-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pekauman', '2025-08-25 04:41:12', '2025-08-25 04:59:03'),
(39, 69, 'https://penawangan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Penawangan', '2025-08-25 04:41:12', '2025-08-25 04:59:04'),
(40, 153, 'https://petambakan-madukara.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Petambakan', '2025-08-25 04:41:12', '2025-08-25 05:05:01'),
(41, 154, 'https://rakitan.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Rakitan', '2025-08-25 04:41:13', '2025-08-25 05:05:06'),
(42, 70, 'https://sered-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sered', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(43, 155, 'https://talunamba-madukara.sistemdata.id', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Talunamba', '2025-08-25 04:41:13', '2025-08-25 05:05:12'),
(44, 71, 'https://www.gumiwang-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Gumiwang', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(45, 72, 'https://kalipelus-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalipelus', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(46, 73, 'https://karanganyar-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karanganyar', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(47, 156, 'http://merden.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Merden', '2025-08-25 04:41:13', '2025-08-25 05:05:18'),
(48, 74, 'https://mertasari-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Mertasari', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(49, 75, 'https://petir-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Petir', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(50, 76, 'https://pucungbedug-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pucungbedug', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(51, 157, 'https://kandangwangi-wanadadi.sistemdata.id/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Kandangwangi', '2025-08-25 04:41:13', '2025-08-25 05:05:26'),
(52, 158, 'https://karangjambe-wanadadi.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Karangjambe', '2025-08-25 04:41:13', '2025-08-25 05:05:33'),
(53, 77, 'https://karangkemiri-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangkemiri', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(54, 78, 'https://kasilib-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kasalib', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(55, 79, 'https://linggasari-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Linggasari', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(56, 159, 'https://medayu.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Medayu', '2025-08-25 04:41:13', '2025-08-25 05:05:40'),
(57, 80, 'https://tapen-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tapen', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(58, NULL, 'https://wanadadi.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Wanadadi', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(59, 9, 'https://badakarya-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Badakarya', '2025-08-25 04:41:13', '2025-08-25 04:41:13'),
(60, 35, 'https://bondolharjo-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Bondolharjo', '2025-08-25 04:41:13', '2025-08-25 04:41:13'),
(61, 41, 'https://danakerta-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Danakerta', '2025-08-25 04:41:13', '2025-08-25 04:41:13'),
(62, 82, 'https://jembangan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Jembangan', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(63, 83, 'https://karangsari-punggelan.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangsari', '2025-08-25 04:41:13', '2025-08-25 04:59:04'),
(64, 84, 'https://kecepit-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kecepit', '2025-08-25 04:41:13', '2025-08-25 04:59:05'),
(65, 85, 'https://klapa-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Klapa', '2025-08-25 04:41:13', '2025-08-25 04:59:05'),
(66, 86, 'https://mlaya-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Mlaya', '2025-08-25 04:41:13', '2025-08-25 04:59:05'),
(67, 87, 'https://petuguran-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Petuguran', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(68, 88, 'https://punggelan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Punggelan', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(69, 89, 'https://purwasana-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Purwasana', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(70, 90, 'https://sambong-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sambong', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(71, 91, 'https://sawangan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sawangan', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(72, 92, 'https://sidarata-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sidarata', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(73, 93, 'https://tanjungtirta-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tanjungtirta', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(74, 94, 'https://tlaga-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tlaga', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(75, 95, 'https://tribuana-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tribuana', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(76, 18, 'https://desabanjengan.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Banjengan', '2025-08-25 04:41:14', '2025-08-25 04:41:14'),
(77, 96, 'https://kebakalan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kebakalan', '2025-08-25 04:41:14', '2025-08-25 04:59:05'),
(78, 97, 'https://panggisari-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Panggisari', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(79, 98, 'https://kertayasa-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kertayasa', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(80, 32, 'https://www.desablimbing.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Blimbing', '2025-08-25 04:41:14', '2025-08-25 04:41:14'),
(81, 99, 'https://purwasaba-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Purwasaba', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(82, 160, 'https://desakebanaran.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kebanaran', '2025-08-25 04:41:14', '2025-08-25 05:05:48'),
(83, 161, 'https://www.desasomawangi.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Somawangi', '2025-08-25 04:41:14', '2025-08-25 05:05:54'),
(84, 4, 'https://www.ambal.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Ambal', '2025-08-25 04:41:14', '2025-08-25 04:41:14'),
(85, 100, 'https://jlegong-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Jlegong', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(86, 101, 'https://www.karanggondang-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karanggondang', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(87, 162, 'https://www.karangkobar.berdesa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangkobar', '2025-08-25 04:41:14', '2025-08-25 05:06:01'),
(88, 102, 'https://www.leksana-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Leksana', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(89, 103, 'http://paweden-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Paweden', '2025-08-25 04:41:14', '2025-08-25 04:59:06'),
(90, 163, 'https://sampang.berdesa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sampang', '2025-08-25 04:41:15', '2025-08-25 05:06:06'),
(91, 104, 'https://slatri-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Slatri', '2025-08-25 04:41:15', '2025-08-25 04:59:06'),
(92, 12, 'https://balun-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Balun', '2025-08-25 04:41:15', '2025-08-25 04:41:15'),
(93, 19, 'https://bantar-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Bantar', '2025-08-25 04:41:15', '2025-08-25 04:41:15'),
(94, 44, 'https://dawuhan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Dawuhan', '2025-08-25 04:41:15', '2025-08-25 04:41:15'),
(95, 105, 'https://jatilawang-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Jatilawang', '2025-08-25 04:41:15', '2025-08-25 04:59:06'),
(96, 106, 'https://karangtengah-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangtengah', '2025-08-25 04:41:15', '2025-08-25 04:59:06'),
(97, 107, 'https://kasimpar-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kasimpar', '2025-08-25 04:41:15', '2025-08-25 04:59:06'),
(98, 108, 'https://kubang-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kubang', '2025-08-25 04:41:15', '2025-08-25 04:59:06'),
(99, 109, 'https://legoksayem-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Legoksayem', '2025-08-25 04:41:15', '2025-08-25 04:59:06'),
(100, 110, 'https://pagergunung-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pagergunung', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(101, 111, 'https://pandansari-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pandansari', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(102, 112, 'https://penanggungan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Penanggungan', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(103, 113, 'https://pesantren-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pesantren', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(104, 164, 'https://susukan-wanayasa.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Susukan', '2025-08-25 04:41:15', '2025-08-25 05:06:12'),
(105, 114, 'https://suwidak-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Suwidak', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(106, 115, 'https://tempuran-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tempuran', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(107, 116, 'https://wanaraja-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Wanaraja', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(108, 117, 'https://wanayasa-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Wanayasa', '2025-08-25 04:41:15', '2025-08-25 04:59:07'),
(109, 165, 'https://purwareja.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Purwareja', '2025-08-25 04:41:15', '2025-08-25 05:06:22'),
(110, 166, 'https://kecitran.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kecitran', '2025-08-25 04:41:15', '2025-08-25 05:06:29'),
(111, 167, 'https://sirkandi.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sirkandi', '2025-08-25 04:41:15', '2025-08-25 05:06:35'),
(112, 168, 'https://pagak.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pagak', '2025-08-25 04:41:15', '2025-08-25 05:06:40'),
(113, 169, 'https://kalilandak.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalilandak', '2025-08-25 04:41:15', '2025-08-25 05:06:46'),
(114, NULL, 'https://klampok.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Klampok', '2025-08-25 04:41:15', '2025-08-25 05:06:52'),
(115, 171, 'https://kalimandi.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalimandi', '2025-08-25 04:41:15', '2025-08-25 05:06:57'),
(116, 172, 'https://kaliwinasuh.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kaliwinasuh', '2025-08-25 04:41:15', '2025-08-25 05:07:04'),
(117, 10, 'https://www.badamita-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Badamita', '2025-08-25 04:41:15', '2025-08-25 04:41:15'),
(118, 13, 'https://bandingan.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Bandingan', '2025-08-25 04:41:15', '2025-08-25 04:41:15'),
(119, 51, 'https://gelang-rakit.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Gelang', '2025-08-25 04:41:15', '2025-08-25 04:41:15'),
(120, 118, 'https://kincang-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kincang', '2025-08-25 04:41:16', '2025-08-25 04:59:07'),
(121, 119, 'https://lengkong-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Lengkong', '2025-08-25 04:41:16', '2025-08-25 04:59:07'),
(122, 120, 'https://luwung-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Luwung', '2025-08-25 04:41:16', '2025-08-25 04:59:07'),
(123, 121, 'https://pingit-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pingit', '2025-08-25 04:41:16', '2025-08-25 04:59:07'),
(124, 122, 'https://www.rakit-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Rakit', '2025-08-25 04:41:16', '2025-08-25 04:59:08'),
(125, 123, 'https://situwangi-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Situwangi', '2025-08-25 04:41:16', '2025-08-25 04:59:08'),
(126, 124, 'https://tanjunganom-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tanjunganom', '2025-08-25 04:41:16', '2025-08-25 04:59:08'),
(127, 173, 'https://piasawetan.layanandesa.cloud/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Piasa wetan', '2025-08-25 04:41:16', '2025-08-25 05:07:12'),
(128, 174, 'https://pekikiran.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Pekikiran', '2025-08-25 04:41:16', '2025-08-25 05:07:18'),
(129, 36, 'https://brengkok.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Brengkok', '2025-08-25 04:41:16', '2025-08-25 04:41:16'),
(130, 125, 'https://panerusankulon-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Panerusan kulon', '2025-08-25 04:41:16', '2025-08-25 04:59:08'),
(131, 175, 'https://panerusanwetan.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Panerusan wetan', '2025-08-25 04:41:16', '2025-08-25 05:07:26'),
(132, 176, 'https://gumelemkulon.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Gumelem kulon', '2025-08-25 04:41:16', '2025-08-25 05:07:34'),
(133, 177, 'https://gumelemwetan.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Gumelem wetan', '2025-08-25 04:41:16', '2025-08-25 05:07:42'),
(134, 47, 'https://derik.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Derik', '2025-08-25 04:41:16', '2025-08-25 04:41:16'),
(135, 27, 'https://berta.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Berta', '2025-08-25 04:41:16', '2025-08-25 04:41:16'),
(136, 178, 'https://karangjati.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Karangjati', '2025-08-25 04:41:16', '2025-08-25 05:07:48'),
(137, 179, 'https://kedawung.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Kedawung', '2025-08-25 04:41:16', '2025-08-25 05:07:54'),
(138, 48, 'https://dermasari.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Dermasari', '2025-08-25 04:41:16', '2025-08-25 04:41:16'),
(139, 164, 'https://susukan.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Susukan', '2025-08-25 04:41:16', '2025-08-25 05:07:59'),
(140, 180, 'https://kemranggon.layanandesa.cloud/index.php/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Kemranggon', '2025-08-25 04:41:16', '2025-08-25 05:08:09'),
(141, 126, 'https://karangsalam-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangsalam', '2025-08-25 04:41:16', '2025-08-25 04:59:08'),
(142, 40, 'https://condongcampur-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Condong Campur', '2025-08-25 04:41:16', '2025-08-25 04:59:08'),
(143, 43, 'https://darmayasa-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Darmayasa', '2025-08-25 04:41:16', '2025-08-25 04:41:16'),
(144, 127, 'http://giritirta-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Giritirta', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(145, 83, 'http://karangsari-banjarnegara.desa.id/index.php/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangsari', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(146, 128, 'https://pejawaran-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pejawaran', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(147, 129, 'https://sarwodadi-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sarwodadi', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(148, 130, 'https://semangkung-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Semangkung', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(149, 6, 'https://aribaya-pagentan.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa terkait: Aribaya', '2025-08-25 04:41:17', '2025-08-25 04:41:17'),
(150, 8, 'https://babadan.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Babadan', '2025-08-25 04:41:17', '2025-08-25 04:41:17'),
(151, 131, 'https://gumingsir-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Gumingsir', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(152, 181, 'https://kalitlaga.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalitlaga', '2025-08-25 04:41:17', '2025-08-25 05:08:15'),
(153, 182, 'https://karangnangka.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karangnangka', '2025-08-25 04:41:17', '2025-08-25 05:08:21'),
(154, 132, 'https://karekan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karekan', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(155, 133, 'https://kasmaran-banjarnegara.desa.id/index.php/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kasmaran', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(156, 183, 'https://kayuares.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kayuares', '2025-08-25 04:41:17', '2025-08-25 05:08:26'),
(157, 184, 'https://larangan.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Larangan', '2025-08-25 04:41:17', '2025-08-25 05:08:32'),
(158, 134, 'https://majasari-pagentan.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Majasari', '2025-08-25 04:41:17', '2025-08-25 04:59:08'),
(159, 185, 'https://nagasari.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Nagasari', '2025-08-25 04:41:17', '2025-08-25 05:08:37'),
(160, 135, 'https://pagentan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Pagentan', '2025-08-25 04:41:17', '2025-08-25 04:59:09'),
(161, 136, 'https://plumbungan-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Plumbungan', '2025-08-25 04:41:17', '2025-08-25 04:59:09'),
(162, 186, 'https://sokaraja.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sokaraja', '2025-08-25 04:41:17', '2025-08-25 05:08:42'),
(163, 187, 'https://tegaljeruk.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Tegaljeruk', '2025-08-25 04:41:17', '2025-08-25 05:08:48'),
(164, 11, 'https://bakal-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Bakal', '2025-08-25 04:41:17', '2025-08-25 04:41:17'),
(165, 21, 'https://batur-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Batur', '2025-08-25 04:41:17', '2025-08-25 04:41:17'),
(166, 49, 'https://www.dieng.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Dieng Kulon', '2025-08-25 04:41:17', '2025-08-25 04:41:17'),
(167, 137, 'https://www.sumberejo-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sumberejo', '2025-08-25 04:41:17', '2025-08-25 04:59:09'),
(168, 138, 'https://gununglangit-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Gununglangit', '2025-08-25 04:41:17', '2025-08-25 04:59:09'),
(169, 139, 'https://www.kalibening-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalibening', '2025-08-25 04:41:17', '2025-08-25 04:59:09'),
(170, 188, 'https://kalibombong.banjarnegara-desa.id/', 'tidak_ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Kalibombong', '2025-08-25 04:41:17', '2025-08-25 05:08:55'),
(171, 73, 'https://karanganyar-kalibening.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Karang Anyar', '2025-08-25 04:41:18', '2025-08-25 04:59:09'),
(172, 198, 'https://majatengah-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Majatengah', '2025-08-25 04:41:18', '2025-09-03 06:03:20'),
(173, 141, 'https://plorengan-banjarnegara.desa.id', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Plorengan', '2025-08-25 04:41:18', '2025-08-25 04:59:09'),
(174, 142, 'https://sembawa-banjarnegara.desa.id/', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa belum terdaftar: Sembawa', '2025-08-25 04:41:18', '2025-08-25 04:59:09'),
(175, 25, 'https://beji-pandanarum.desa.id', 'ada', 'aktif', 'bukan_clasnet', 'tidak_sinkron', 'Desa terkait: Beji', '2025-08-25 04:41:18', '2025-09-02 06:01:30'),
(176, 189, 'https://lawen-pandanarum.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Lawen', '2025-08-25 04:41:18', '2025-08-25 05:09:00'),
(177, 190, 'https://pandanarum.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Pandanarum', '2025-08-25 04:41:18', '2025-08-25 05:09:05'),
(178, 191, 'https://pasegeran-pandanarum.sistemdata.id', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Pasegeran', '2025-08-25 04:41:18', '2025-08-25 05:09:11'),
(179, 192, 'https://pingitlor-pandanarum.webdeva.io//', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Pingit Lor', '2025-08-25 04:41:18', '2025-08-25 05:09:16'),
(180, NULL, 'https://pringamba-pandanarum.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Pringamba', '2025-08-25 04:41:18', '2025-08-25 05:09:22'),
(181, 194, 'https://sinduaji-pandanarum.webdeva.io/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Sinduaji', '2025-08-25 04:41:18', '2025-08-25 05:09:29'),
(182, 195, 'https://sirongge-pandanrum.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'sinkron', 'Desa belum terdaftar: Sirongge', '2025-08-25 04:41:18', '2025-08-25 05:09:56'),
(183, 191, 'https://pasegeran-pandanarum.sistemdata.id/', 'ada', 'aktif', 'clasnet', 'proses', '', '2025-09-02 06:21:58', '2025-09-02 06:21:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `website_maintenance`
--

CREATE TABLE `website_maintenance` (
  `id` int(11) NOT NULL,
  `desa_id` int(11) DEFAULT NULL,
  `nama_desa` varchar(255) NOT NULL,
  `website_url` varchar(255) NOT NULL,
  `penanggung_jawab_id` int(11) DEFAULT NULL,
  `programmer_id` int(11) DEFAULT NULL,
  `penanggung_jawab` varchar(255) NOT NULL,
  `programmer` varchar(255) NOT NULL,
  `deadline` date NOT NULL,
  `assignment_type` enum('instalasi_sid','perbaikan_error_404_505','update_versi_aplikasi','perbaikan_ssl','pemindahan_hosting_server','maintenance_lainnya') DEFAULT 'instalasi_sid',
  `keterangan` text DEFAULT NULL,
  `status` enum('maintenance','pending_verification','completed') DEFAULT 'maintenance',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `website_maintenance`
--

INSERT INTO `website_maintenance` (`id`, `desa_id`, `nama_desa`, `website_url`, `penanggung_jawab_id`, `programmer_id`, `penanggung_jawab`, `programmer`, `deadline`, `assignment_type`, `keterangan`, `status`, `created_at`, `updated_at`) VALUES
(5, 27, 'Berta', 'https://berta.layanandesa.cloud/index.php/', 10, 11, '', '', '2025-09-04', 'instalasi_sid', 'Percobaan aplikasi ceklist maintenance.', 'maintenance', '2025-08-27 23:47:43', '2025-09-12 05:23:33'),
(6, 47, 'Derik', 'https://derik.layanandesa.cloud/index.php/', 4, 12, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-27 23:48:28', '2025-08-27 23:48:28'),
(7, 125, 'Panerusankulon', 'https://panerusankulon-banjarnegara.desa.id/', 10, 11, '', '', '2025-09-02', 'instalasi_sid', 'Setup website sesuai dengan checklist. Apabila ada kesulitan beritahu penanggung jawab. selamat bekerja.', 'maintenance', '2025-08-28 14:06:15', '2025-08-28 14:06:15'),
(8, 178, 'Karangjati', 'https://karangjati.layanandesa.cloud/index.php/', 10, 11, '', '', '2025-09-03', 'instalasi_sid', 'Dikerjakan sesuai dengan checklist dan bila ada pertanyaan bisa ditanyakan ke penanggung jawab. Selamat bekerja.', 'maintenance', '2025-08-28 14:17:36', '2025-08-28 14:17:36'),
(9, 126, 'Karangsalam', 'https://karangsalam-banjarnegara.desa.id/', 10, 11, '', '', '2025-09-04', 'instalasi_sid', 'Dikerjakan sesuai dengan checklist dan bila ada pertanyaan bisa ditanyakan ke penanggung jawab. Selamat bekerja.', 'maintenance', '2025-08-28 14:19:05', '2025-08-28 14:19:05'),
(10, 179, 'Kedawung', 'https://kedawung.layanandesa.cloud/index.php/', 10, 12, '', '', '2025-09-04', 'instalasi_sid', 'Dikerjakan sesuai dengan checklist dan bila ada pertanyaan bisa ditanyakan ke penanggung jawab. Selamat bekerja.', 'maintenance', '2025-08-28 14:20:15', '2025-08-28 14:20:15'),
(11, 48, 'Dermasari', 'https://dermasari.layanandesa.cloud/index.php/', 12, 12, '', '', '2025-09-04', 'instalasi_sid', 'Dikerjakan sesuai dengan checklist dan bila ada pertanyaan bisa ditanyakan ke penanggung jawab. Selamat bekerja.', 'maintenance', '2025-08-28 14:21:56', '2025-08-28 14:21:56'),
(12, 177, 'gumelemwetan', 'https://gumelemwetan.layanandesa.cloud/index.php/', 4, 12, '', '', '2025-09-04', 'instalasi_sid', 'Dikerjakan sesuai dengan checklist dan bila ada pertanyaan bisa ditanyakan ke penanggung jawab. Selamat bekerja.', 'maintenance', '2025-08-28 14:22:46', '2025-08-28 14:22:46'),
(13, 197, 'Susukan Susukan', 'https://susukan.layanandesa.cloud/', 4, 12, '', '', '2025-09-04', 'instalasi_sid', 'Dikerjakan sesuai dengan checklist dan bila ada pertanyaan bisa ditanyakan ke penanggung jawab. Selamat bekerja.', 'maintenance', '2025-08-28 14:24:21', '2025-08-28 14:24:21'),
(14, 180, 'kemranggon', 'https://kemranggon.layanandesa.cloud/index.php/', 4, 11, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-28 14:25:25', '2025-08-28 14:25:25'),
(15, 36, 'Brengkok', 'https://brengkok.layanandesa.cloud/index.php/', 4, 11, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-28 14:26:00', '2025-08-28 14:26:00'),
(16, 176, 'gumelemkulon', 'https://gumelemkulon.layanandesa.cloud/index.php/', 1, 11, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-28 14:32:08', '2025-08-28 14:32:08'),
(17, 174, 'pekikiran', 'https://pekikiran.layanandesa.cloud/index.php/', 1, 12, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-28 14:32:43', '2025-08-28 14:32:43'),
(18, 175, 'panerusanwetan', 'https://panerusanwetan.layanandesa.cloud/index.php/', 1, 12, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-28 14:33:43', '2025-08-28 14:33:43'),
(19, 173, 'piasawetan', 'https://piasawetan.layanandesa.cloud/', 10, 12, '', '', '2025-09-04', 'instalasi_sid', '', 'maintenance', '2025-08-28 14:35:00', '2025-08-28 14:35:00'),
(22, 25, 'Beji-Pandanarum', 'https://beji-pandanarum.desa.id', 10, 11, '', '', '2025-09-06', 'instalasi_sid', 'Cek website apakah sudah sesuai.', 'maintenance', '2025-09-02 06:03:14', '2025-09-02 06:03:14'),
(23, 191, 'Pasegeran', 'https://pasegeran-pandanarum.sistemdata.id', 6, 12, '', '', '2025-09-06', 'instalasi_sid', 'Data dilengkapi sesuai checklist', 'maintenance', '2025-09-02 06:23:11', '2025-09-11 19:31:24'),
(26, NULL, 'Kalisemi', 'https://kalisemi.desa.id', 1, 11, '', '', '2025-09-27', 'perbaikan_error_404_505', '', 'completed', '2025-09-11 18:39:57', '2025-09-11 18:50:00');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indeks untuk tabel `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_id` (`maintenance_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indeks untuk tabel `bank`
--
ALTER TABLE `bank`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_bank` (`kode_bank`),
  ADD KEY `idx_bank_status` (`status`),
  ADD KEY `idx_bank_jenis` (`jenis_bank`);

--
-- Indeks untuk tabel `biaya_operasional`
--
ALTER TABLE `biaya_operasional`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_biaya` (`kode_biaya`);

--
-- Indeks untuk tabel `desa`
--
ALTER TABLE `desa`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `desa_id` (`desa_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `hutang`
--
ALTER TABLE `hutang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pembelian_id` (`pembelian_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_hutang_jatuh_tempo` (`tanggal_jatuh_tempo`);

--
-- Indeks untuk tabel `jadwal_biaya`
--
ALTER TABLE `jadwal_biaya`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `biaya_operasional_id` (`biaya_operasional_id`);

--
-- Indeks untuk tabel `jadwal_kunjungan`
--
ALTER TABLE `jadwal_kunjungan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `desa_id` (`desa_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `teknisi_id` (`teknisi_id`);

--
-- Indeks untuk tabel `jadwal_peralatan`
--
ALTER TABLE `jadwal_peralatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `peralatan_id` (`peralatan_id`);

--
-- Indeks untuk tabel `jadwal_personal`
--
ALTER TABLE `jadwal_personal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `jadwal_produk`
--
ALTER TABLE `jadwal_produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `konsultasi`
--
ALTER TABLE `konsultasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `desa_id` (`desa_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indeks untuk tabel `layanan`
--
ALTER TABLE `layanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_layanan` (`kode_layanan`);

--
-- Indeks untuk tabel `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_time` (`login_time`);

--
-- Indeks untuk tabel `maintenance_checklist`
--
ALTER TABLE `maintenance_checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_id` (`maintenance_id`);

--
-- Indeks untuk tabel `maintenance_checklist_simple`
--
ALTER TABLE `maintenance_checklist_simple`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_id` (`maintenance_id`);

--
-- Indeks untuk tabel `mutasi_kas`
--
ALTER TABLE `mutasi_kas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_mutasi_kas_tanggal` (`tanggal_mutasi`),
  ADD KEY `idx_mutasi_kas_bank` (`bank_id`),
  ADD KEY `idx_mutasi_kas_jenis` (`jenis_mutasi`,`jenis_transaksi`);

--
-- Indeks untuk tabel `pelatihan`
--
ALTER TABLE `pelatihan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pelatihan` (`kode_pelatihan`),
  ADD KEY `desa_id` (`desa_id`),
  ADD KEY `trainer_id` (`trainer_id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `piutang_id` (`piutang_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `bank_id` (`bank_id`);

--
-- Indeks untuk tabel `pembayaran_pembelian`
--
ALTER TABLE `pembayaran_pembelian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pembelian_id` (`pembelian_id`),
  ADD KEY `hutang_id` (`hutang_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `bank_id` (`bank_id`);

--
-- Indeks untuk tabel `pembelian`
--
ALTER TABLE `pembelian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_po` (`nomor_po`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_pembelian_vendor` (`vendor_id`),
  ADD KEY `idx_pembelian_tanggal` (`tanggal_pembelian`),
  ADD KEY `idx_pembelian_status` (`status_pembelian`),
  ADD KEY `idx_pembelian_bank` (`bank_id`),
  ADD KEY `idx_pembelian_desa` (`desa_id`);

--
-- Indeks untuk tabel `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pembelian_id` (`pembelian_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indeks untuk tabel `penerimaan_barang`
--
ALTER TABLE `penerimaan_barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_penerimaan` (`nomor_penerimaan`),
  ADD KEY `pembelian_id` (`pembelian_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_penerimaan_tanggal` (`tanggal_terima`);

--
-- Indeks untuk tabel `penerimaan_detail`
--
ALTER TABLE `penerimaan_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penerimaan_id` (`penerimaan_id`),
  ADD KEY `pembelian_detail_id` (`pembelian_detail_id`);

--
-- Indeks untuk tabel `peralatan`
--
ALTER TABLE `peralatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_peralatan` (`kode_peralatan`);

--
-- Indeks untuk tabel `peserta_pelatihan`
--
ALTER TABLE `peserta_pelatihan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pelatihan_id` (`pelatihan_id`),
  ADD KEY `desa_id` (`desa_id`);

--
-- Indeks untuk tabel `piutang`
--
ALTER TABLE `piutang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `desa_id` (`desa_id`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_produk` (`kode_produk`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `idx_produk_vendor` (`vendor_id`);

--
-- Indeks untuk tabel `programmer_replies`
--
ALTER TABLE `programmer_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_message_id` (`admin_message_id`),
  ADD KEY `programmer_id` (`programmer_id`);

--
-- Indeks untuk tabel `saldo_bank`
--
ALTER TABLE `saldo_bank`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bank_periode` (`bank_id`,`periode_bulan`,`periode_tahun`),
  ADD KEY `idx_saldo_bank_periode` (`periode_tahun`,`periode_bulan`);

--
-- Indeks untuk tabel `stock_movement`
--
ALTER TABLE `stock_movement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produk_id` (`produk_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_tanggal` (`tanggal_movement`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indeks untuk tabel `stock_opname`
--
ALTER TABLE `stock_opname`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produk_id` (`produk_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tanggal_opname` (`tanggal_opname`),
  ADD KEY `idx_stock_opname_date_produk` (`tanggal_opname`,`produk_id`),
  ADD KEY `idx_stock_opname_selisih` (`selisih`);

--
-- Indeks untuk tabel `tiket_support`
--
ALTER TABLE `tiket_support`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_tiket` (`nomor_tiket`),
  ADD KEY `desa_id` (`desa_id`),
  ADD KEY `teknisi_id` (`teknisi_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_invoice` (`nomor_invoice`),
  ADD KEY `desa_id` (`desa_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_transaksi_bank` (`bank_id`);

--
-- Indeks untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `layanan_id` (`layanan_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `vendor`
--
ALTER TABLE `vendor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_vendor` (`kode_vendor`),
  ADD KEY `idx_vendor_status` (`status`),
  ADD KEY `idx_vendor_jenis` (`jenis_vendor`);

--
-- Indeks untuk tabel `website_desa`
--
ALTER TABLE `website_desa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `desa_id` (`desa_id`);

--
-- Indeks untuk tabel `website_maintenance`
--
ALTER TABLE `website_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `desa_id` (`desa_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `bank`
--
ALTER TABLE `bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `biaya_operasional`
--
ALTER TABLE `biaya_operasional`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `desa`
--
ALTER TABLE `desa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- AUTO_INCREMENT untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `hutang`
--
ALTER TABLE `hutang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `jadwal_biaya`
--
ALTER TABLE `jadwal_biaya`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `jadwal_kunjungan`
--
ALTER TABLE `jadwal_kunjungan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `jadwal_peralatan`
--
ALTER TABLE `jadwal_peralatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `jadwal_personal`
--
ALTER TABLE `jadwal_personal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `jadwal_produk`
--
ALTER TABLE `jadwal_produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `konsultasi`
--
ALTER TABLE `konsultasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `layanan`
--
ALTER TABLE `layanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT untuk tabel `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=194;

--
-- AUTO_INCREMENT untuk tabel `maintenance_checklist`
--
ALTER TABLE `maintenance_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `maintenance_checklist_simple`
--
ALTER TABLE `maintenance_checklist_simple`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `mutasi_kas`
--
ALTER TABLE `mutasi_kas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `pelatihan`
--
ALTER TABLE `pelatihan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembayaran_pembelian`
--
ALTER TABLE `pembayaran_pembelian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembelian`
--
ALTER TABLE `pembelian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `penerimaan_barang`
--
ALTER TABLE `penerimaan_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `penerimaan_detail`
--
ALTER TABLE `penerimaan_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `peralatan`
--
ALTER TABLE `peralatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `peserta_pelatihan`
--
ALTER TABLE `peserta_pelatihan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `piutang`
--
ALTER TABLE `piutang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT untuk tabel `programmer_replies`
--
ALTER TABLE `programmer_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `saldo_bank`
--
ALTER TABLE `saldo_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `stock_movement`
--
ALTER TABLE `stock_movement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `stock_opname`
--
ALTER TABLE `stock_opname`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unik stock opname', AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tiket_support`
--
ALTER TABLE `tiket_support`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `vendor`
--
ALTER TABLE `vendor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `website_desa`
--
ALTER TABLE `website_desa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT untuk tabel `website_maintenance`
--
ALTER TABLE `website_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `website_maintenance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  ADD CONSTRAINT `dokumen_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `dokumen_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `hutang`
--
ALTER TABLE `hutang`
  ADD CONSTRAINT `hutang_ibfk_1` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`),
  ADD CONSTRAINT `hutang_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`);

--
-- Ketidakleluasaan untuk tabel `jadwal_biaya`
--
ALTER TABLE `jadwal_biaya`
  ADD CONSTRAINT `jadwal_biaya_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kunjungan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_biaya_ibfk_2` FOREIGN KEY (`biaya_operasional_id`) REFERENCES `biaya_operasional` (`id`);

--
-- Ketidakleluasaan untuk tabel `jadwal_kunjungan`
--
ALTER TABLE `jadwal_kunjungan`
  ADD CONSTRAINT `jadwal_kunjungan_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `jadwal_kunjungan_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `jadwal_kunjungan_ibfk_3` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `jadwal_peralatan`
--
ALTER TABLE `jadwal_peralatan`
  ADD CONSTRAINT `jadwal_peralatan_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kunjungan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_peralatan_ibfk_2` FOREIGN KEY (`peralatan_id`) REFERENCES `peralatan` (`id`);

--
-- Ketidakleluasaan untuk tabel `jadwal_personal`
--
ALTER TABLE `jadwal_personal`
  ADD CONSTRAINT `jadwal_personal_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kunjungan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_personal_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `jadwal_produk`
--
ALTER TABLE `jadwal_produk`
  ADD CONSTRAINT `jadwal_produk_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kunjungan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_produk_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `konsultasi`
--
ALTER TABLE `konsultasi`
  ADD CONSTRAINT `konsultasi_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `konsultasi_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maintenance_checklist`
--
ALTER TABLE `maintenance_checklist`
  ADD CONSTRAINT `maintenance_checklist_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `website_maintenance` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maintenance_checklist_simple`
--
ALTER TABLE `maintenance_checklist_simple`
  ADD CONSTRAINT `maintenance_checklist_simple_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `website_maintenance` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mutasi_kas`
--
ALTER TABLE `mutasi_kas`
  ADD CONSTRAINT `mutasi_kas_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`),
  ADD CONSTRAINT `mutasi_kas_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pelatihan`
--
ALTER TABLE `pelatihan`
  ADD CONSTRAINT `pelatihan_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `pelatihan_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`),
  ADD CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`piutang_id`) REFERENCES `piutang` (`id`),
  ADD CONSTRAINT `pembayaran_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pembayaran_ibfk_4` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembayaran_pembelian`
--
ALTER TABLE `pembayaran_pembelian`
  ADD CONSTRAINT `pembayaran_pembelian_ibfk_1` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`),
  ADD CONSTRAINT `pembayaran_pembelian_ibfk_2` FOREIGN KEY (`hutang_id`) REFERENCES `hutang` (`id`),
  ADD CONSTRAINT `pembayaran_pembelian_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pembayaran_pembelian_ibfk_4` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembelian`
--
ALTER TABLE `pembelian`
  ADD CONSTRAINT `fk_pembelian_bank` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`),
  ADD CONSTRAINT `fk_pembelian_desa` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `pembelian_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`),
  ADD CONSTRAINT `pembelian_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  ADD CONSTRAINT `pembelian_detail_ibfk_1` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pembelian_detail_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `penerimaan_barang`
--
ALTER TABLE `penerimaan_barang`
  ADD CONSTRAINT `penerimaan_barang_ibfk_1` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`),
  ADD CONSTRAINT `penerimaan_barang_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `penerimaan_detail`
--
ALTER TABLE `penerimaan_detail`
  ADD CONSTRAINT `penerimaan_detail_ibfk_1` FOREIGN KEY (`penerimaan_id`) REFERENCES `penerimaan_barang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penerimaan_detail_ibfk_2` FOREIGN KEY (`pembelian_detail_id`) REFERENCES `pembelian_detail` (`id`);

--
-- Ketidakleluasaan untuk tabel `peserta_pelatihan`
--
ALTER TABLE `peserta_pelatihan`
  ADD CONSTRAINT `peserta_pelatihan_ibfk_1` FOREIGN KEY (`pelatihan_id`) REFERENCES `pelatihan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peserta_pelatihan_ibfk_2` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`);

--
-- Ketidakleluasaan untuk tabel `piutang`
--
ALTER TABLE `piutang`
  ADD CONSTRAINT `piutang_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`),
  ADD CONSTRAINT `piutang_ibfk_2` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`),
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `programmer_replies`
--
ALTER TABLE `programmer_replies`
  ADD CONSTRAINT `programmer_replies_ibfk_1` FOREIGN KEY (`admin_message_id`) REFERENCES `admin_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `programmer_replies_ibfk_2` FOREIGN KEY (`programmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `saldo_bank`
--
ALTER TABLE `saldo_bank`
  ADD CONSTRAINT `saldo_bank_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`);

--
-- Ketidakleluasaan untuk tabel `stock_opname`
--
ALTER TABLE `stock_opname`
  ADD CONSTRAINT `fk_stock_opname_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_opname_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tiket_support`
--
ALTER TABLE `tiket_support`
  ADD CONSTRAINT `tiket_support_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `tiket_support_ibfk_2` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD CONSTRAINT `transaksi_detail_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaksi_detail_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `transaksi_detail_ibfk_3` FOREIGN KEY (`layanan_id`) REFERENCES `layanan` (`id`);

--
-- Ketidakleluasaan untuk tabel `website_desa`
--
ALTER TABLE `website_desa`
  ADD CONSTRAINT `website_desa_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `website_maintenance`
--
ALTER TABLE `website_maintenance`
  ADD CONSTRAINT `website_maintenance_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
