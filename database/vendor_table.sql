-- Tabel Vendor untuk Sistem Pembelian
-- Created: 2025

-- Tabel Vendor
CREATE TABLE vendor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_vendor VARCHAR(20) UNIQUE NOT NULL,
    nama_vendor VARCHAR(100) NOT NULL,
    jenis_vendor ENUM('supplier_atk', 'supplier_it', 'supplier_umum', 'distributor') NOT NULL,
    alamat TEXT NOT NULL,
    kota VARCHAR(50) NOT NULL,
    provinsi VARCHAR(50) NOT NULL,
    kode_pos VARCHAR(10),
    nama_kontak VARCHAR(100) NOT NULL,
    jabatan_kontak VARCHAR(50),
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    no_rekening VARCHAR(30),
    nama_bank VARCHAR(50),
    atas_nama_rekening VARCHAR(100),
    npwp VARCHAR(20),
    alamat_npwp TEXT,
    term_pembayaran ENUM('tunai', 'tempo_7', 'tempo_14', 'tempo_30', 'tempo_45', 'tempo_60') DEFAULT 'tunai',
    limit_kredit DECIMAL(15,2) DEFAULT 0,
    rating ENUM('A', 'B', 'C', 'D') DEFAULT 'B' COMMENT 'Rating vendor berdasarkan performa',
    catatan TEXT,
    status ENUM('aktif', 'nonaktif', 'blacklist') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert data vendor sample
INSERT INTO vendor (kode_vendor, nama_vendor, jenis_vendor, alamat, kota, provinsi, nama_kontak, no_hp, email, term_pembayaran, status) VALUES
('VND001', 'PT Sinar Dunia', 'supplier_atk', 'Jl. Raya Industri No. 123', 'Jakarta', 'DKI Jakarta', 'Budi Santoso', '081234567890', 'budi@sinardunia.com', 'tempo_30', 'aktif'),
('VND002', 'CV Maju Bersama', 'supplier_it', 'Jl. Teknologi No. 45', 'Bandung', 'Jawa Barat', 'Sari Dewi', '081234567891', 'sari@majubersama.com', 'tempo_14', 'aktif'),
('VND003', 'Toko Berkah Jaya', 'supplier_umum', 'Jl. Perdagangan No. 67', 'Surabaya', 'Jawa Timur', 'Ahmad Rizki', '081234567892', 'ahmad@berkahjaya.com', 'tunai', 'aktif'),
('VND004', 'PT Global Supplies', 'distributor', 'Jl. Industri Raya No. 89', 'Medan', 'Sumatera Utara', 'Linda Sari', '081234567893', 'linda@globalsupplies.com', 'tempo_45', 'aktif'),
('VND005', 'CV Mandiri Office', 'supplier_atk', 'Jl. Perkantoran No. 12', 'Yogyakarta', 'DI Yogyakarta', 'Eko Prasetyo', '081234567894', 'eko@mandirioffice.com', 'tempo_30', 'aktif');

-- Index untuk performa
CREATE INDEX idx_vendor_status ON vendor(status);
CREATE INDEX idx_vendor_jenis ON vendor(jenis_vendor);
CREATE INDEX idx_vendor_kode ON vendor(kode_vendor);

SELECT 'Tabel vendor berhasil dibuat!' as status;