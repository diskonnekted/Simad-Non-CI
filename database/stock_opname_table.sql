-- Tabel untuk mencatat riwayat stock opname
CREATE TABLE IF NOT EXISTS `stock_opname` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produk_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_opname` datetime NOT NULL,
  `stok_sistem` int(11) NOT NULL DEFAULT 0,
  `stok_fisik` int(11) NOT NULL DEFAULT 0,
  `selisih` int(11) NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_produk_id` (`produk_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_tanggal_opname` (`tanggal_opname`),
  CONSTRAINT `fk_stock_opname_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_opname_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index untuk optimasi query
CREATE INDEX `idx_stock_opname_date_produk` ON `stock_opname` (`tanggal_opname`, `produk_id`);
CREATE INDEX `idx_stock_opname_selisih` ON `stock_opname` (`selisih`);

-- Komentar tabel
ALTER TABLE `stock_opname` COMMENT = 'Tabel untuk mencatat riwayat stock opname produk';

-- Komentar kolom
ALTER TABLE `stock_opname` 
  MODIFY COLUMN `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID unik stock opname',
  MODIFY COLUMN `produk_id` int(11) NOT NULL COMMENT 'ID produk yang di-opname',
  MODIFY COLUMN `user_id` int(11) NOT NULL COMMENT 'ID user yang melakukan opname',
  MODIFY COLUMN `tanggal_opname` datetime NOT NULL COMMENT 'Tanggal dan waktu stock opname',
  MODIFY COLUMN `stok_sistem` int(11) NOT NULL DEFAULT 0 COMMENT 'Stok menurut sistem saat opname',
  MODIFY COLUMN `stok_fisik` int(11) NOT NULL DEFAULT 0 COMMENT 'Stok fisik yang ditemukan',
  MODIFY COLUMN `selisih` int(11) NOT NULL DEFAULT 0 COMMENT 'Selisih antara stok fisik dan sistem (fisik - sistem)',
  MODIFY COLUMN `keterangan` text DEFAULT NULL COMMENT 'Keterangan tambahan untuk stock opname',
  MODIFY COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu record dibuat',
  MODIFY COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu record terakhir diupdate';