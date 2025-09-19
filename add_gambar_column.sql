-- Script untuk menambahkan kolom gambar ke tabel layanan
-- Jalankan script ini untuk memperbaiki struktur database

ALTER TABLE layanan ADD COLUMN gambar VARCHAR(255) NULL AFTER deskripsi;

-- Update existing records to have NULL gambar value
UPDATE layanan SET gambar = NULL WHERE gambar IS NULL;

SELECT 'Kolom gambar berhasil ditambahkan ke tabel layanan!' as status;