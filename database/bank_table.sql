-- Tabel Bank untuk Sistem Pembayaran Transaksi
-- Created: 2025

-- Tabel Bank
CREATE TABLE bank (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_bank VARCHAR(10) UNIQUE NOT NULL,
    nama_bank VARCHAR(100) NOT NULL,
    jenis_bank ENUM('bkk', 'bank_umum', 'cash') NOT NULL,
    deskripsi TEXT,
    nomor_rekening VARCHAR(50),
    atas_nama VARCHAR(100),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert data bank default sesuai permintaan
INSERT INTO bank (kode_bank, nama_bank, jenis_bank, deskripsi, status) VALUES
('BKK001', 'BKK Clasnet', 'bkk', 'Bank Kredit Kecamatan Clasnet untuk transaksi desa', 'aktif'),
('BKK002', 'BKK BBS', 'bkk', 'Bank Kredit Kecamatan BBS untuk transaksi desa', 'aktif'),
('BJC001', 'Bank Jateng Clasnet', 'bank_umum', 'Bank Jawa Tengah cabang Clasnet', 'aktif'),
('CASH01', 'Cash di Kantor', 'cash', 'Pembayaran tunai langsung di kantor', 'aktif');

-- Alter tabel pembayaran untuk menambahkan referensi ke bank
ALTER TABLE pembayaran ADD COLUMN bank_id INT NULL AFTER metode_bayar;
ALTER TABLE pembayaran ADD FOREIGN KEY (bank_id) REFERENCES bank(id);

-- Alter tabel pembayaran_pembelian untuk menambahkan referensi ke bank
ALTER TABLE pembayaran_pembelian ADD COLUMN bank_id INT NULL AFTER metode_bayar;
ALTER TABLE pembayaran_pembelian ADD FOREIGN KEY (bank_id) REFERENCES bank(id);

-- Index untuk performa
CREATE INDEX idx_bank_status ON bank(status);
CREATE INDEX idx_bank_jenis ON bank(jenis_bank);

SELECT 'Tabel bank berhasil dibuat dengan 4 pilihan bank!' as status;