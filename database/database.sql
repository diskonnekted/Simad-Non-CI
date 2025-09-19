-- Database untuk Aplikasi Manajemen Transaksi Desa
-- Created: 2025

-- Note: Database creation and USE statement removed for hosting compatibility
-- The database should already exist and be selected by the installer

-- Tabel Users (Admin, Sales, Teknisi, Finance)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'akunting', 'supervisor', 'teknisi', 'programmer') NOT NULL,
    no_hp VARCHAR(20),
    foto_profil VARCHAR(255),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Desa (Klien)
CREATE TABLE desa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_desa VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    kecamatan VARCHAR(50) NOT NULL,
    kabupaten VARCHAR(50) NOT NULL,
    provinsi VARCHAR(50) NOT NULL,
    kode_pos VARCHAR(10),
    nama_kepala_desa VARCHAR(100),
    no_hp_kepala_desa VARCHAR(20),
    nama_sekdes VARCHAR(100),
    no_hp_sekdes VARCHAR(20),
    nama_admin_it VARCHAR(100),
    no_hp_admin_it VARCHAR(20),
    email_desa VARCHAR(100),
    kategori ENUM('baru', 'rutin', 'prioritas') DEFAULT 'baru',
    tingkat_digitalisasi ENUM('rendah', 'sedang', 'tinggi') DEFAULT 'rendah',
    limit_kredit DECIMAL(15,2) DEFAULT 0,
    foto_kantor VARCHAR(255),
    catatan_khusus TEXT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Kategori Produk
CREATE TABLE kategori_produk (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Produk (Barang IT & ATK)
CREATE TABLE produk (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_produk VARCHAR(20) UNIQUE NOT NULL,
    nama_produk VARCHAR(100) NOT NULL,
    kategori_id INT,
    jenis ENUM('barang_it', 'atk', 'layanan') NOT NULL,
    deskripsi TEXT,
    spesifikasi TEXT,
    harga_satuan DECIMAL(15,2) NOT NULL,
    harga_grosir DECIMAL(15,2),
    satuan VARCHAR(20) NOT NULL,
    stok_minimal INT DEFAULT 0,
    stok_tersedia INT DEFAULT 0,
    gambar VARCHAR(255),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori_produk(id)
);

-- Tabel Layanan
CREATE TABLE layanan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_layanan VARCHAR(20) UNIQUE NOT NULL,
    nama_layanan VARCHAR(100) NOT NULL,
    jenis_layanan ENUM('maintenance', 'pelatihan', 'instalasi', 'konsultasi', 'pengembangan') NOT NULL,
    deskripsi TEXT,
    harga DECIMAL(15,2) NOT NULL,
    durasi_hari INT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Transaksi
CREATE TABLE transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nomor_invoice VARCHAR(20) UNIQUE NOT NULL,
    desa_id INT NOT NULL,
    user_id INT NOT NULL,
    tanggal_transaksi DATE NOT NULL,
    jenis_transaksi ENUM('barang', 'layanan', 'campuran') NOT NULL,
    metode_pembayaran ENUM('tunai', 'dp_pelunasan', 'tempo') NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    dp_amount DECIMAL(15,2) DEFAULT 0,
    sisa_amount DECIMAL(15,2) DEFAULT 0,
    tanggal_jatuh_tempo DATE NULL,
    status_transaksi ENUM('draft', 'diproses', 'dikirim', 'selesai') DEFAULT 'draft',
    status_pembayaran ENUM('belum_bayar', 'dp', 'lunas') DEFAULT 'belum_bayar',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Detail Transaksi
CREATE TABLE transaksi_detail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT NOT NULL,
    produk_id INT NULL,
    layanan_id INT NULL,
    nama_item VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    harga_satuan DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    catatan TEXT,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id),
    FOREIGN KEY (layanan_id) REFERENCES layanan(id)
);

-- Tabel Piutang
CREATE TABLE piutang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT NOT NULL,
    desa_id INT NOT NULL,
    jumlah_piutang DECIMAL(15,2) NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    status ENUM('belum_jatuh_tempo', 'mendekati_jatuh_tempo', 'terlambat') DEFAULT 'belum_jatuh_tempo',
    denda DECIMAL(15,2) DEFAULT 0,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id),
    FOREIGN KEY (desa_id) REFERENCES desa(id)
);

-- Tabel Pembayaran
CREATE TABLE pembayaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT NOT NULL,
    piutang_id INT NULL,
    jumlah_bayar DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    metode_bayar ENUM('tunai', 'transfer', 'qris') NOT NULL,
    bukti_transfer VARCHAR(255),
    catatan TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id),
    FOREIGN KEY (piutang_id) REFERENCES piutang(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Jadwal Kunjungan
CREATE TABLE jadwal_kunjungan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    desa_id INT NOT NULL,
    user_id INT NOT NULL,
    jenis_kunjungan ENUM('atk_keliling', 'maintenance', 'pelatihan', 'instalasi', 'support', 'lainnya') NOT NULL,
    keperluan TEXT,
    tanggal_kunjungan DATE NOT NULL,
    waktu_mulai TIME,
    waktu_selesai TIME,
    estimasi_durasi INT, -- dalam menit
    urgensi ENUM('rendah', 'normal', 'tinggi', 'urgent') DEFAULT 'normal',
    status ENUM('dijadwalkan', 'sedang_berlangsung', 'selesai', 'dibatalkan') DEFAULT 'dijadwalkan',
    catatan_kunjungan TEXT,
    foto_kunjungan VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Tiket Support
CREATE TABLE tiket_support (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nomor_tiket VARCHAR(20) UNIQUE NOT NULL,
    desa_id INT NOT NULL,
    teknisi_id INT NULL,
    judul VARCHAR(200) NOT NULL,
    deskripsi TEXT NOT NULL,
    prioritas ENUM('rendah', 'sedang', 'tinggi') DEFAULT 'sedang',
    status ENUM('baru', 'diproses', 'selesai', 'ditutup') DEFAULT 'baru',
    tanggal_selesai DATE NULL,
    catatan_teknisi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id),
    FOREIGN KEY (teknisi_id) REFERENCES users(id)
);

-- Tabel Pelatihan
CREATE TABLE pelatihan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_pelatihan VARCHAR(20) UNIQUE NOT NULL,
    nama_pelatihan VARCHAR(100) NOT NULL,
    topik VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    tanggal_pelatihan DATE NOT NULL,
    waktu_mulai TIME NOT NULL,
    waktu_selesai TIME NOT NULL,
    lokasi VARCHAR(200),
    desa_id INT NULL,
    trainer_id INT NOT NULL,
    max_peserta INT DEFAULT 20,
    status ENUM('dijadwalkan', 'berlangsung', 'selesai', 'dibatalkan') DEFAULT 'dijadwalkan',
    materi_pelatihan VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id),
    FOREIGN KEY (trainer_id) REFERENCES users(id)
);

-- Tabel Peserta Pelatihan
CREATE TABLE peserta_pelatihan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pelatihan_id INT NOT NULL,
    nama_peserta VARCHAR(100) NOT NULL,
    jabatan VARCHAR(50),
    desa_id INT NOT NULL,
    no_hp VARCHAR(20),
    email VARCHAR(100),
    status_kehadiran ENUM('hadir', 'tidak_hadir', 'terlambat') NULL,
    nilai_evaluasi INT NULL,
    sertifikat VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pelatihan_id) REFERENCES pelatihan(id) ON DELETE CASCADE,
    FOREIGN KEY (desa_id) REFERENCES desa(id)
);

-- Tabel Dokumen
CREATE TABLE dokumen (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_dokumen VARCHAR(100) NOT NULL,
    jenis_dokumen ENUM('kontrak', 'sop', 'panduan', 'manual', 'laporan') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    desa_id INT NULL,
    user_id INT NOT NULL,
    tanggal_upload DATE NOT NULL,
    tanggal_berlaku DATE NULL,
    tanggal_berakhir DATE NULL,
    status ENUM('aktif', 'expired', 'draft') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert data kategori produk default
INSERT INTO kategori_produk (nama_kategori, deskripsi) VALUES
('Komputer & Laptop', 'Perangkat komputer, laptop, dan aksesorisnya'),
('Printer & Scanner', 'Perangkat printer, scanner, dan consumables'),
('Jaringan & Internet', 'Router, switch, kabel, dan perangkat jaringan'),
('ATK Umum', 'Alat tulis kantor umum seperti pulpen, kertas, map'),
('ATK Khusus', 'Alat tulis kantor khusus seperti stempel, tinta, formulir'),
('Furniture Kantor', 'Meja, kursi, lemari, dan furniture kantor lainnya');

-- Insert data user default (admin)
INSERT INTO users (username, email, password, nama_lengkap, role, status) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'aktif');
-- Password default: password

-- Insert data layanan default
-- Tabel Peralatan Kerja
CREATE TABLE peralatan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_peralatan VARCHAR(20) UNIQUE NOT NULL,
    nama_peralatan VARCHAR(100) NOT NULL,
    kategori ENUM('elektronik', 'tools', 'kendaraan', 'lainnya') NOT NULL,
    deskripsi TEXT,
    kondisi ENUM('baik', 'rusak_ringan', 'rusak_berat', 'maintenance') DEFAULT 'baik',
    lokasi_penyimpanan VARCHAR(100),
    tanggal_beli DATE,
    harga_beli DECIMAL(15,2),
    masa_garansi INT COMMENT 'dalam bulan',
    status ENUM('tersedia', 'digunakan', 'maintenance', 'hilang') DEFAULT 'tersedia',
    foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Biaya Operasional
CREATE TABLE biaya_operasional (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_biaya VARCHAR(20) UNIQUE NOT NULL,
    nama_biaya VARCHAR(100) NOT NULL,
    kategori ENUM('transportasi', 'akomodasi', 'konsumsi', 'komunikasi', 'lainnya') NOT NULL,
    deskripsi TEXT,
    tarif_standar DECIMAL(15,2) NOT NULL,
    satuan VARCHAR(20) NOT NULL COMMENT 'per km, per hari, per orang, dll',
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Jadwal Produk (relasi many-to-many antara jadwal dan produk)
CREATE TABLE jadwal_produk (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jadwal_id INT NOT NULL,
    produk_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_kunjungan(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id)
);

-- Tabel Jadwal Personal (relasi many-to-many antara jadwal dan users)
CREATE TABLE jadwal_personal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jadwal_id INT NOT NULL,
    user_id INT NOT NULL,
    role_dalam_kunjungan ENUM('teknisi_utama', 'teknisi_pendamping', 'sales', 'supervisor') NOT NULL,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_kunjungan(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Jadwal Peralatan (relasi many-to-many antara jadwal dan peralatan)
CREATE TABLE jadwal_peralatan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jadwal_id INT NOT NULL,
    peralatan_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    kondisi_awal ENUM('baik', 'rusak_ringan', 'rusak_berat') DEFAULT 'baik',
    kondisi_akhir ENUM('baik', 'rusak_ringan', 'rusak_berat') NULL,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_kunjungan(id) ON DELETE CASCADE,
    FOREIGN KEY (peralatan_id) REFERENCES peralatan(id)
);

-- Tabel Jadwal Biaya (relasi many-to-many antara jadwal dan biaya operasional)
CREATE TABLE jadwal_biaya (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jadwal_id INT NOT NULL,
    biaya_operasional_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(15,2) NOT NULL,
    total_biaya DECIMAL(15,2) NOT NULL,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_kunjungan(id) ON DELETE CASCADE,
    FOREIGN KEY (biaya_operasional_id) REFERENCES biaya_operasional(id)
);

INSERT INTO layanan (kode_layanan, nama_layanan, jenis_layanan, deskripsi, harga, durasi_hari) VALUES
('MNT001', 'Maintenance Aplikasi Desa Bulanan', 'maintenance', 'Maintenance rutin aplikasi desa setiap bulan', 1500000, 1),
('MNT002', 'Maintenance Aplikasi Desa Tahunan', 'maintenance', 'Maintenance aplikasi desa paket tahunan', 15000000, 365),
('TRN001', 'Pelatihan SIMDes Dasar', 'pelatihan', 'Pelatihan penggunaan Sistem Informasi Manajemen Desa', 2000000, 1),
('TRN002', 'Pelatihan Siskeudes', 'pelatihan', 'Pelatihan penggunaan Sistem Keuangan Desa', 2500000, 2),
('INS001', 'Instalasi Sistem Baru', 'instalasi', 'Instalasi dan setup sistem aplikasi desa baru', 5000000, 3),
('KON001', 'Konsultasi IT', 'konsultasi', 'Konsultasi dan troubleshooting masalah IT', 500000, 1);

-- Insert data peralatan default
INSERT INTO peralatan (kode_peralatan, nama_peralatan, kategori, deskripsi, kondisi, lokasi_penyimpanan, status) VALUES
('PRL001', 'Laptop Asus VivoBook', 'elektronik', 'Laptop untuk presentasi dan demo aplikasi', 'baik', 'Gudang Utama', 'tersedia'),
('PRL002', 'Proyektor Epson', 'elektronik', 'Proyektor untuk pelatihan dan presentasi', 'baik', 'Gudang Utama', 'tersedia'),
('PRL003', 'Kabel HDMI 5m', 'elektronik', 'Kabel penghubung laptop ke proyektor', 'baik', 'Gudang Utama', 'tersedia'),
('PRL004', 'Extension Cable 10m', 'elektronik', 'Kabel ekstensi listrik', 'baik', 'Gudang Utama', 'tersedia'),
('PRL005', 'Toolkit Komputer', 'tools', 'Set peralatan untuk maintenance komputer', 'baik', 'Gudang Utama', 'tersedia'),
('PRL006', 'UPS 1000VA', 'elektronik', 'Uninterruptible Power Supply', 'baik', 'Gudang Utama', 'tersedia');

-- Insert data biaya operasional default
INSERT INTO biaya_operasional (kode_biaya, nama_biaya, kategori, deskripsi, tarif_standar, satuan) VALUES
('BOP001', 'Bensin Motor', 'transportasi', 'Biaya bahan bakar motor untuk kunjungan', 15000, 'per liter'),
('BOP002', 'Bensin Mobil', 'transportasi', 'Biaya bahan bakar mobil untuk kunjungan', 18000, 'per liter'),
('BOP003', 'Tol', 'transportasi', 'Biaya tol perjalanan', 25000, 'per trip'),
('BOP004', 'Parkir', 'transportasi', 'Biaya parkir kendaraan', 5000, 'per lokasi'),
('BOP005', 'Makan Siang', 'konsumsi', 'Biaya makan siang tim', 25000, 'per orang'),
('BOP006', 'Snack', 'konsumsi', 'Biaya snack untuk tim', 10000, 'per orang'),
('BOP007', 'Hotel Budget', 'akomodasi', 'Biaya menginap hotel budget', 200000, 'per malam'),
('BOP008', 'Hotel Standard', 'akomodasi', 'Biaya menginap hotel standard', 350000, 'per malam'),
('BOP009', 'Pulsa Internet', 'komunikasi', 'Biaya pulsa internet untuk koordinasi', 50000, 'per hari');

-- Tabel Promo Banners
CREATE TABLE promo_banners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    gambar VARCHAR(255) NOT NULL,
    posisi ENUM('1', '2') NOT NULL COMMENT 'Posisi card promo (1 atau 2)',
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    tanggal_mulai DATE NULL,
    tanggal_berakhir DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert data promo banner default
INSERT INTO promo_banners (judul, deskripsi, posisi, status) VALUES
('Promo Spesial', 'Dapatkan penawaran terbaik untuk produk pilihan', '1', 'aktif'),
('Penawaran Khusus', 'Hemat lebih banyak hari ini dengan diskon menarik', '2', 'aktif');