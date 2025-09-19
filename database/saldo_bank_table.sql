-- Tabel Saldo Bank untuk Sistem Kas dan Dana
-- Created: 2025

-- Tabel Saldo Bank
CREATE TABLE saldo_bank (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bank_id INT NOT NULL,
    saldo_awal DECIMAL(15,2) DEFAULT 0 COMMENT 'Saldo awal periode',
    saldo_masuk DECIMAL(15,2) DEFAULT 0 COMMENT 'Total pemasukan',
    saldo_keluar DECIMAL(15,2) DEFAULT 0 COMMENT 'Total pengeluaran',
    saldo_akhir DECIMAL(15,2) DEFAULT 0 COMMENT 'Saldo akhir (awal + masuk - keluar)',
    periode_bulan INT NOT NULL COMMENT 'Bulan periode (1-12)',
    periode_tahun INT NOT NULL COMMENT 'Tahun periode',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_id) REFERENCES bank(id),
    UNIQUE KEY unique_bank_periode (bank_id, periode_bulan, periode_tahun)
);

-- Tabel Mutasi Kas untuk tracking semua transaksi kas
CREATE TABLE mutasi_kas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bank_id INT NOT NULL,
    jenis_mutasi ENUM('masuk', 'keluar') NOT NULL,
    jenis_transaksi ENUM('penjualan', 'pembelian', 'pembayaran_piutang', 'pembayaran_hutang', 'lainnya') NOT NULL,
    referensi_id INT NULL COMMENT 'ID transaksi/pembelian/pembayaran terkait',
    referensi_tabel VARCHAR(50) NULL COMMENT 'Nama tabel referensi',
    jumlah DECIMAL(15,2) NOT NULL,
    keterangan TEXT,
    tanggal_mutasi DATE NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_id) REFERENCES bank(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert saldo awal untuk setiap bank (contoh saldo awal)
INSERT INTO saldo_bank (bank_id, saldo_awal, saldo_akhir, periode_bulan, periode_tahun) 
SELECT 
    id as bank_id,
    CASE 
        WHEN jenis_bank = 'cash' THEN 10000000.00  -- 10 juta untuk kas
        WHEN jenis_bank = 'bkk' THEN 50000000.00   -- 50 juta untuk BKK
        WHEN jenis_bank = 'bank_umum' THEN 100000000.00 -- 100 juta untuk bank umum
        ELSE 5000000.00
    END as saldo_awal,
    CASE 
        WHEN jenis_bank = 'cash' THEN 10000000.00
        WHEN jenis_bank = 'bkk' THEN 50000000.00
        WHEN jenis_bank = 'bank_umum' THEN 100000000.00
        ELSE 5000000.00
    END as saldo_akhir,
    MONTH(CURDATE()) as periode_bulan,
    YEAR(CURDATE()) as periode_tahun
FROM bank 
WHERE status = 'aktif';

-- Index untuk performa
CREATE INDEX idx_saldo_bank_periode ON saldo_bank(periode_tahun, periode_bulan);
CREATE INDEX idx_mutasi_kas_tanggal ON mutasi_kas(tanggal_mutasi);
CREATE INDEX idx_mutasi_kas_bank ON mutasi_kas(bank_id);
CREATE INDEX idx_mutasi_kas_jenis ON mutasi_kas(jenis_mutasi, jenis_transaksi);

-- Trigger untuk update saldo bank otomatis saat ada mutasi kas
DELIMITER //
CREATE TRIGGER update_saldo_after_mutasi 
AFTER INSERT ON mutasi_kas
FOR EACH ROW
BEGIN
    DECLARE current_periode_bulan INT;
    DECLARE current_periode_tahun INT;
    
    SET current_periode_bulan = MONTH(NEW.tanggal_mutasi);
    SET current_periode_tahun = YEAR(NEW.tanggal_mutasi);
    
    -- Insert atau update saldo bank untuk periode tersebut
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
END//
DELIMITER ;

SELECT 'Tabel saldo_bank dan mutasi_kas berhasil dibuat!' as status;