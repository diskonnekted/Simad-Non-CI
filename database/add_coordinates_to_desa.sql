-- Menambahkan kolom koordinat latitude dan longitude ke tabel desa
-- Created: 2025

-- Tambah kolom latitude dan longitude ke tabel desa
ALTER TABLE desa 
ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER provinsi,
ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude;

-- Tambah index untuk performa pencarian geografis
CREATE INDEX idx_desa_coordinates ON desa(latitude, longitude);

-- Update data koordinat untuk desa-desa di Kabupaten Banjarnegara
-- Koordinat berdasarkan lokasi kecamatan (data dummy untuk demo)
UPDATE desa SET latitude = -7.3549, longitude = 109.6426 WHERE kecamatan = 'Banjarnegara';
UPDATE desa SET latitude = -7.2890, longitude = 109.7234 WHERE kecamatan = 'Rakit';
UPDATE desa SET latitude = -7.4123, longitude = 109.5678 WHERE kecamatan = 'Karangkobar';
UPDATE desa SET latitude = -7.3890, longitude = 109.8123 WHERE kecamatan = 'Pagentan';
UPDATE desa SET latitude = -7.2567, longitude = 109.6890 WHERE kecamatan = 'Kalibening';
UPDATE desa SET latitude = -7.4567, longitude = 109.7456 WHERE kecamatan = 'Punggelan';
UPDATE desa SET latitude = -7.1890, longitude = 109.8567 WHERE kecamatan = 'Batur';
UPDATE desa SET latitude = -7.3234, longitude = 109.5234 WHERE kecamatan = 'Wanayasa';
UPDATE desa SET latitude = -7.4890, longitude = 109.6789 WHERE kecamatan = 'Bawang';
UPDATE desa SET latitude = -7.2123, longitude = 109.7890 WHERE kecamatan = 'Sigaluh';
UPDATE desa SET latitude = -7.3678, longitude = 109.5890 WHERE kecamatan = 'Banjarmangu';
UPDATE desa SET latitude = -7.4234, longitude = 109.8234 WHERE kecamatan = 'Mandiraja';
UPDATE desa SET latitude = -7.2890, longitude = 109.6234 WHERE kecamatan = 'Madukara';

-- Tambahkan koordinat random untuk desa dalam kecamatan yang sama (variasi kecil)
UPDATE desa SET 
    latitude = latitude + (RAND() - 0.5) * 0.02,
    longitude = longitude + (RAND() - 0.5) * 0.02
WHERE latitude IS NOT NULL AND longitude IS NOT NULL;

SELECT 'Kolom koordinat berhasil ditambahkan ke tabel desa!' as status;