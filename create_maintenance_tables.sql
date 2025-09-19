-- Tabel untuk menyimpan data website yang sedang maintenance
CREATE TABLE IF NOT EXISTS website_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    desa_id INT,
    nama_desa VARCHAR(255) NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    penanggung_jawab VARCHAR(255) NOT NULL,
    programmer VARCHAR(255) NOT NULL,
    deadline DATE NOT NULL,
    keterangan TEXT,
    status ENUM('maintenance', 'pending_verification', 'completed') DEFAULT 'maintenance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id) ON DELETE SET NULL
);

-- Tabel untuk menyimpan checklist maintenance setiap website
CREATE TABLE IF NOT EXISTS maintenance_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT NOT NULL,
    install_website BOOLEAN DEFAULT FALSE,
    setup_info_desa BOOLEAN DEFAULT FALSE,
    import_database BOOLEAN DEFAULT FALSE,
    menu_standar BOOLEAN DEFAULT FALSE,
    foto_gambar BOOLEAN DEFAULT FALSE,
    berita_dummy BOOLEAN DEFAULT FALSE,
    no_404_page BOOLEAN DEFAULT FALSE,
    no_505_page BOOLEAN DEFAULT FALSE,
    sinkron_opendata BOOLEAN DEFAULT FALSE,
    domain_resmi_kominfo BOOLEAN DEFAULT FALSE,
    submitted_for_verification BOOLEAN DEFAULT FALSE,
    verified_by_admin BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    verified_by VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (maintenance_id) REFERENCES website_maintenance(id) ON DELETE CASCADE
);

-- Insert sample data untuk testing
INSERT INTO website_maintenance (desa_id, nama_desa, website_url, penanggung_jawab, programmer, deadline, keterangan, status) VALUES
(1, 'Desa Sukamaju', 'https://sukamaju.desa.id', 'Budi Santoso', 'Ahmad Developer', '2025-02-15', 'Maintenance rutin tahunan', 'maintenance'),
(2, 'Desa Makmur', 'https://makmur.desa.id', 'Siti Rahayu', 'Rina Coder', '2025-02-20', 'Update sistem dan migrasi data', 'maintenance'),
(3, 'Desa Sejahtera', 'https://sejahtera.desa.id', 'Joko Widodo', 'Dani Programmer', '2025-02-10', 'Perbaikan bug dan optimasi', 'pending_verification');

-- Insert sample checklist data
INSERT INTO maintenance_checklist (maintenance_id, install_website, setup_info_desa, import_database, menu_standar, foto_gambar, berita_dummy, no_404_page, no_505_page, sinkron_opendata, domain_resmi_kominfo) VALUES
(1, TRUE, TRUE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE),
(2, TRUE, TRUE, TRUE, TRUE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE),
(3, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, FALSE);