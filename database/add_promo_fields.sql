-- Script untuk menambahkan field promo ke tabel produk dan layanan
-- Jalankan script ini jika field harga_diskon dan is_featured belum ada

USE manajemen_transaksi_desa;

-- Tambahkan field harga_diskon dan is_featured ke tabel produk
ALTER TABLE produk 
ADD COLUMN IF NOT EXISTS harga_diskon DECIMAL(15,2) NULL COMMENT 'Harga setelah diskon',
ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0 COMMENT '1 = produk unggulan, 0 = produk biasa';

-- Tambahkan field harga_diskon dan is_featured ke tabel layanan
ALTER TABLE layanan 
ADD COLUMN IF NOT EXISTS harga_diskon DECIMAL(15,2) NULL COMMENT 'Harga setelah diskon',
ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0 COMMENT '1 = layanan unggulan, 0 = layanan biasa';

-- Update beberapa produk contoh sebagai featured
UPDATE produk SET is_featured = 1 WHERE id IN (1, 2, 3) AND status = 'aktif';

-- Update beberapa layanan contoh sebagai featured
UPDATE layanan SET is_featured = 1 WHERE id IN (1, 2) AND status = 'aktif';

SELECT 'Field promo berhasil ditambahkan!' as status;