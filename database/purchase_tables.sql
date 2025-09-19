-- Tabel untuk Sistem Pembelian ke Vendor
-- Created: 2025

-- Tabel Vendor (sudah ada, pastikan struktur sesuai)
-- CREATE TABLE vendor (...)

-- Tabel Pembelian (Purchase Orders)
CREATE TABLE pembelian (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nomor_po VARCHAR(20) UNIQUE NOT NULL COMMENT 'Purchase Order Number',
    vendor_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'User yang membuat PO',
    tanggal_pembelian DATE NOT NULL,
    tanggal_dibutuhkan DATE NULL COMMENT 'Tanggal barang dibutuhkan',
    metode_pembayaran ENUM('tunai', 'transfer', 'tempo') NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    dp_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Down Payment',
    sisa_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Sisa pembayaran',
    tanggal_jatuh_tempo DATE NULL,
    status_pembelian ENUM('draft', 'dikirim', 'diterima_sebagian', 'diterima_lengkap', 'dibatalkan') DEFAULT 'draft',
    status_pembayaran ENUM('belum_bayar', 'dp', 'lunas') DEFAULT 'belum_bayar',
    catatan TEXT,
    alamat_pengiriman TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendor(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Detail Pembelian
CREATE TABLE pembelian_detail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pembelian_id INT NOT NULL,
    produk_id INT NOT NULL,
    nama_item VARCHAR(100) NOT NULL,
    quantity_pesan INT NOT NULL COMMENT 'Jumlah yang dipesan',
    quantity_terima INT DEFAULT 0 COMMENT 'Jumlah yang sudah diterima',
    harga_satuan DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pembelian_id) REFERENCES pembelian(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id)
);

-- Tabel Penerimaan Barang (Goods Receipt)
CREATE TABLE penerimaan_barang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pembelian_id INT NOT NULL,
    nomor_penerimaan VARCHAR(20) UNIQUE NOT NULL,
    tanggal_terima DATE NOT NULL,
    user_id INT NOT NULL COMMENT 'User yang menerima barang',
    catatan TEXT,
    foto_bukti VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pembelian_id) REFERENCES pembelian(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Detail Penerimaan Barang
CREATE TABLE penerimaan_detail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    penerimaan_id INT NOT NULL,
    pembelian_detail_id INT NOT NULL,
    quantity_terima INT NOT NULL,
    kondisi ENUM('baik', 'rusak', 'cacat') DEFAULT 'baik',
    catatan TEXT,
    FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_barang(id) ON DELETE CASCADE,
    FOREIGN KEY (pembelian_detail_id) REFERENCES pembelian_detail(id)
);

-- Tabel Hutang (untuk pembelian dengan tempo)
CREATE TABLE hutang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pembelian_id INT NOT NULL,
    vendor_id INT NOT NULL,
    jumlah_hutang DECIMAL(15,2) NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    status ENUM('belum_jatuh_tempo', 'mendekati_jatuh_tempo', 'terlambat', 'lunas') DEFAULT 'belum_jatuh_tempo',
    denda DECIMAL(15,2) DEFAULT 0,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pembelian_id) REFERENCES pembelian(id),
    FOREIGN KEY (vendor_id) REFERENCES vendor(id)
);

-- Tabel Pembayaran Pembelian
CREATE TABLE pembayaran_pembelian (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pembelian_id INT NOT NULL,
    hutang_id INT NULL,
    jumlah_bayar DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    metode_bayar ENUM('tunai', 'transfer', 'cek', 'giro') NOT NULL,
    nomor_referensi VARCHAR(50) COMMENT 'Nomor transfer/cek/giro',
    bukti_pembayaran VARCHAR(255),
    catatan TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pembelian_id) REFERENCES pembelian(id),
    FOREIGN KEY (hutang_id) REFERENCES hutang(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Index untuk performa
CREATE INDEX idx_pembelian_vendor ON pembelian(vendor_id);
CREATE INDEX idx_pembelian_tanggal ON pembelian(tanggal_pembelian);
CREATE INDEX idx_pembelian_status ON pembelian(status_pembelian);
CREATE INDEX idx_hutang_jatuh_tempo ON hutang(tanggal_jatuh_tempo);
CREATE INDEX idx_penerimaan_tanggal ON penerimaan_barang(tanggal_terima);

-- Trigger untuk update stok otomatis saat penerimaan barang
DELIMITER //
CREATE TRIGGER update_stok_after_penerimaan 
AFTER INSERT ON penerimaan_detail
FOR EACH ROW
BEGIN
    DECLARE produk_id_var INT;
    
    -- Ambil produk_id dari pembelian_detail
    SELECT pd.produk_id INTO produk_id_var
    FROM pembelian_detail pd
    WHERE pd.id = NEW.pembelian_detail_id;
    
    -- Update stok produk jika kondisi baik
    IF NEW.kondisi = 'baik' THEN
        UPDATE produk 
        SET stok_tersedia = stok_tersedia + NEW.quantity_terima
        WHERE id = produk_id_var;
    END IF;
    
    -- Update quantity_terima di pembelian_detail
    UPDATE pembelian_detail 
    SET quantity_terima = quantity_terima + NEW.quantity_terima
    WHERE id = NEW.pembelian_detail_id;
END//
DELIMITER ;

SELECT 'Tabel pembelian berhasil dibuat!' as status;