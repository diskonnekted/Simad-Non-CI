-- Script untuk mengupdate struktur tabel maintenance
-- Mengubah kolom penanggung_jawab dan programmer menjadi foreign key ke tabel users

USE manajemen_transaksi_desa;

-- Backup data existing jika ada
CREATE TABLE IF NOT EXISTS website_maintenance_backup AS SELECT * FROM website_maintenance;
CREATE TABLE IF NOT EXISTS maintenance_checklist_backup AS SELECT * FROM maintenance_checklist;

-- Drop foreign key constraints jika ada
ALTER TABLE maintenance_checklist DROP FOREIGN KEY IF EXISTS fk_maintenance_checklist_maintenance;

-- Update struktur tabel website_maintenance
ALTER TABLE website_maintenance 
DROP COLUMN penanggung_jawab,
DROP COLUMN programmer;

ALTER TABLE website_maintenance 
ADD COLUMN penanggung_jawab_id INT NULL AFTER website_url,
ADD COLUMN programmer_id INT NULL AFTER penanggung_jawab_id;

-- Tambahkan foreign key constraints
ALTER TABLE website_maintenance 
ADD CONSTRAINT fk_website_maintenance_penanggung_jawab 
FOREIGN KEY (penanggung_jawab_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_website_maintenance_programmer 
FOREIGN KEY (programmer_id) REFERENCES users(id) ON DELETE SET NULL;

-- Update struktur tabel maintenance_checklist
ALTER TABLE maintenance_checklist 
DROP COLUMN verified_by;

ALTER TABLE maintenance_checklist 
ADD COLUMN verified_by_id INT NULL AFTER verified_at;

-- Tambahkan foreign key constraint untuk verified_by
ALTER TABLE maintenance_checklist 
ADD CONSTRAINT fk_maintenance_checklist_verified_by 
FOREIGN KEY (verified_by_id) REFERENCES users(id) ON DELETE SET NULL;

-- Restore foreign key untuk maintenance_id
ALTER TABLE maintenance_checklist 
ADD CONSTRAINT fk_maintenance_checklist_maintenance 
FOREIGN KEY (maintenance_id) REFERENCES website_maintenance(id) ON DELETE CASCADE;

-- Insert sample data dengan user IDs
INSERT INTO website_maintenance (desa_id, nama_desa, website_url, penanggung_jawab_id, programmer_id, deadline, keterangan, status) VALUES
(1, 'Desa Sukamaju', 'https://sukamaju.desa.id', 1, 1, '2024-02-15', 'Maintenance rutin bulanan', 'maintenance'),
(2, 'Desa Makmur', 'https://makmur.desa.id', 1, 1, '2024-02-20', 'Update sistem dan konten', 'pending_verification'),
(3, 'Desa Sejahtera', 'https://sejahtera.desa.id', 1, 1, '2024-02-25', 'Migrasi ke server baru', 'completed');

-- Insert sample checklist data
INSERT INTO maintenance_checklist (maintenance_id, install_website, setup_info_desa, import_database, menu_standar, foto_gambar, berita_dummy, no_404_page, no_505_page, sinkron_opendata, domain_resmi_kominfo, submitted_for_verification, verified_by_admin, verified_by_id) VALUES
(1, TRUE, TRUE, TRUE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, NULL),
(2, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, NULL),
(3, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, 1);

-- Verifikasi struktur tabel yang sudah diupdate
DESCRIBE website_maintenance;
DESCRIBE maintenance_checklist;

SELECT 'Update struktur tabel maintenance berhasil!' as status;