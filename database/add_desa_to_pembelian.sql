-- Menambahkan kolom desa_id ke tabel pembelian
-- Created: 2025

-- Tambah kolom desa_id ke tabel pembelian
ALTER TABLE pembelian 
ADD COLUMN desa_id INT NULL AFTER vendor_id,
ADD CONSTRAINT fk_pembelian_desa FOREIGN KEY (desa_id) REFERENCES desa(id);

-- Tambah index untuk performa
CREATE INDEX idx_pembelian_desa ON pembelian(desa_id);

SELECT 'Kolom desa_id berhasil ditambahkan ke tabel pembelian!' as status;