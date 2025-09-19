-- Menambahkan kolom bank_id ke tabel pembelian
-- Created: 2025

-- Tambah kolom bank_id ke tabel pembelian
ALTER TABLE pembelian 
ADD COLUMN bank_id INT NULL AFTER metode_pembayaran,
ADD CONSTRAINT fk_pembelian_bank FOREIGN KEY (bank_id) REFERENCES bank(id);

-- Update data pembelian yang sudah ada dengan bank default (ID 1 - Kas)
UPDATE pembelian SET bank_id = 1 WHERE bank_id IS NULL;

-- Buat kolom bank_id menjadi NOT NULL setelah update
ALTER TABLE pembelian MODIFY COLUMN bank_id INT NOT NULL;

-- Tambah index untuk performa
CREATE INDEX idx_pembelian_bank ON pembelian(bank_id);

SELECT 'Kolom bank_id berhasil ditambahkan ke tabel pembelian!' as status;