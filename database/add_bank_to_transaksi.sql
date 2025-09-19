-- Menambahkan kolom bank_id ke tabel transaksi
-- Created: 2025

-- Tambahkan kolom bank_id ke tabel transaksi
ALTER TABLE transaksi ADD COLUMN bank_id INT NULL AFTER metode_pembayaran;
ALTER TABLE transaksi ADD FOREIGN KEY (bank_id) REFERENCES bank(id);

-- Update transaksi yang sudah ada untuk menggunakan Cash sebagai default
UPDATE transaksi SET bank_id = (SELECT id FROM bank WHERE kode_bank = 'CASH01' LIMIT 1) WHERE bank_id IS NULL;

-- Index untuk performa
CREATE INDEX idx_transaksi_bank ON transaksi(bank_id);

SELECT 'Kolom bank_id berhasil ditambahkan ke tabel transaksi!' as status;