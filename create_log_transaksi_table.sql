-- Script untuk membuat tabel log_transaksi
-- Tabel ini akan menyimpan catatan transaksi yang dihapus

CREATE TABLE IF NOT EXISTS log_transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    nomor_invoice VARCHAR(50) NOT NULL,
    desa_id INT,
    nama_desa VARCHAR(255),
    user_id INT,
    nama_user VARCHAR(255),
    tanggal_transaksi DATE,
    jenis_transaksi VARCHAR(50),
    metode_pembayaran VARCHAR(50),
    bank_id INT,
    nama_bank VARCHAR(255),
    dp_amount DECIMAL(15,2),
    tanggal_jatuh_tempo DATE,
    total_amount DECIMAL(15,2) NOT NULL,
    catatan TEXT,
    status_transaksi VARCHAR(50),
    data_transaksi_json TEXT COMMENT 'Backup lengkap data transaksi dalam format JSON',
    alasan_hapus TEXT,
    deleted_by INT NOT NULL,
    deleted_by_name VARCHAR(255) NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_transaksi_id (transaksi_id),
    INDEX idx_nomor_invoice (nomor_invoice),
    INDEX idx_deleted_by (deleted_by),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambahkan komentar pada tabel
ALTER TABLE log_transaksi COMMENT = 'Log untuk menyimpan catatan transaksi yang dihapus';