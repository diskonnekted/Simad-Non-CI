-- Migration untuk menambahkan tabel vendor dan modifikasi tabel produk
-- Tanggal: 2025

-- 1. Buat tabel vendor
CREATE TABLE vendor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_vendor VARCHAR(20) UNIQUE NOT NULL,
    nama_vendor VARCHAR(100) NOT NULL,
    nama_perusahaan VARCHAR(150),
    alamat TEXT,
    kota VARCHAR(50),
    provinsi VARCHAR(50),
    kode_pos VARCHAR(10),
    nama_kontak VARCHAR(100),
    no_hp VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(100),
    jenis_vendor ENUM('supplier', 'distributor', 'manufacturer', 'reseller') DEFAULT 'supplier',
    kategori_produk VARCHAR(100), -- kategori produk yang disediakan
    rating DECIMAL(3,2) DEFAULT 0.00, -- rating 0.00 - 5.00
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Insert vendor default "Generic"
INSERT INTO vendor (kode_vendor, nama_vendor, nama_perusahaan, jenis_vendor, status) 
VALUES ('GEN001', 'Generic', 'Generic Vendor', 'supplier', 'aktif');

-- 3. Tambahkan kolom vendor_id ke tabel produk
ALTER TABLE produk ADD COLUMN vendor_id INT DEFAULT 1 AFTER kategori_id;

-- 4. Tambahkan foreign key constraint
ALTER TABLE produk ADD CONSTRAINT fk_produk_vendor 
FOREIGN KEY (vendor_id) REFERENCES vendor(id);

-- 5. Update semua produk yang ada dengan vendor "Generic" (id = 1)
UPDATE produk SET vendor_id = 1 WHERE vendor_id IS NULL;

-- 6. Ubah kolom vendor_id menjadi NOT NULL
ALTER TABLE produk MODIFY vendor_id INT NOT NULL;

-- Index untuk performa
CREATE INDEX idx_vendor_status ON vendor(status);
CREATE INDEX idx_vendor_jenis ON vendor(jenis_vendor);
CREATE INDEX idx_produk_vendor ON produk(vendor_id);